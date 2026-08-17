<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\PaymentCompletionService;
use App\Services\PaymentRefundService;
use App\Services\PipraPayGateway;
use App\Services\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentCompletionService $payments,
        private readonly PipraPayGateway $piprapay,
        private readonly StripeGateway $stripe,
        private readonly PaymentRefundService $refunds
    ) {}

    public function initiateStripe(Request $request)
    {
        $data = $request->validate(['order_number' => ['required', 'string']]);
        $order = Order::where('order_number', $data['order_number'])->firstOrFail();
        abort_if($order->payment_status !== 'pending', 422, 'Only pending orders can be sent to payment.');
        return response()->json(['data' => $this->stripe->initiate($order)]);
    }

    public function verifyStripe(Request $request)
    {
        $data = $request->validate(['session_id' => ['required', 'string', 'max:255']]);
        $provider = $this->stripe->verify($data['session_id']);
        $order = $this->orderFromStripe($provider);
        $verified = $this->stripe->normalizeVerified($order, $provider);
        $paid = $this->payments->markPaid($order, 'stripe', 'verify:'.$data['session_id'], $verified);

        return response()->json(['data' => [
            'order' => $paid,
            'order_number' => $paid->order_number,
            'payment_status' => $paid->payment_status,
            'session_id' => $data['session_id'],
        ]]);
    }

    public function stripeWebhook(Request $request)
    {
        $event = $this->stripe->parseWebhook($request->getContent(), $request->header('Stripe-Signature'));
        $type = (string) ($event['type'] ?? '');
        $provider = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];

        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            && strtolower((string) ($provider['payment_status'] ?? '')) === 'paid') {
            $order = $this->orderFromStripe($provider);
            $verified = $this->stripe->normalizeVerified($order, $provider);
            $this->payments->markPaid($order, 'stripe', 'webhook:'.(string) $event['id'], $verified);
        }

        if ($type === 'checkout.session.expired' && ! empty($provider['id'])) {
            $transaction = PaymentTransaction::where('gateway', 'stripe')
                ->where('provider_reference', (string) $provider['id'])
                ->first();
            if ($transaction && $transaction->status !== 'paid') {
                $transaction->forceFill([
                    'status' => 'failed',
                    'normalized_state' => 'expired',
                    'failed_at' => now(),
                ])->save();
            }
        }

        return response()->json(['data' => ['received' => true]]);
    }

    /* Legacy PipraPay endpoints are kept so this conversion can be applied without
       breaking old migrations/tests. Keep PIPRAPAY_ENABLED=false internationally. */
    public function initiatePipraPay(Request $request)
    {
        $data = $request->validate(['order_number' => ['required', 'string']]);
        $order = Order::where('order_number', $data['order_number'])->firstOrFail();
        abort_if($order->payment_status !== 'pending', 422, 'Only pending orders can be sent to payment.');
        return response()->json(['data' => $this->piprapay->initiate($order)]);
    }

    public function success(Request $request)
    {
        $ppId = (string) $request->input('transaction_ref', $request->input('pp_id', $request->input('invoice_id', '')));
        try {
            $provider = $this->piprapay->verify($ppId);
            $this->piprapay->assertPaymentIdMatches($provider, $ppId);
            $order = $this->orderFromProvider($provider, $request->input('order'));
            $verified = $this->piprapay->normalizeVerified($order, $provider);
            $paid = $this->payments->markPaid($order, 'piprapay', 'redirect:'.$verified['provider_transaction_id'], $verified);
        } catch (ValidationException $exception) {
            if ($request->isMethod('get')) return redirect($this->frontendCheckoutResultUrl(null, $ppId, 'unconfirmed'));
            throw $exception;
        }
        if ($request->isMethod('get')) return redirect($this->frontendCheckoutResultUrl($order, $verified['provider_transaction_id'], 'paid'));
        return response()->json(['data' => $paid]);
    }

    public function webhook(Request $request)
    {
        abort_unless(str_contains((string) $request->header('content-type'), 'application/json'), 415);
        $payload = $this->piprapay->validateWebhook($request->all());
        $provider = $this->piprapay->verify((string) $payload['pp_id']);
        $this->piprapay->assertPaymentIdMatches($provider, (string) $payload['pp_id']);
        $order = $this->orderFromProvider($provider);
        $verified = $this->piprapay->normalizeVerified($order, $provider);
        $paid = $this->payments->markPaid($order, 'piprapay', 'webhook:'.$verified['provider_transaction_id'], $verified);
        return response()->json(['data' => $paid]);
    }

    public function failed(Request $request)
    {
        $order = Order::where('order_number', $request->input('order', $request->input('order_number')))->firstOrFail();
        if ($order->payment_status === 'pending') {
            $order->forceFill(['payment_status' => 'failed', 'order_status' => 'failed'])->save();
            PaymentTransaction::where('order_id', $order->id)->where('gateway', 'piprapay')->update([
                'status' => 'failed', 'normalized_state' => 'cancelled', 'failed_at' => now(),
            ]);
        }
        return response()->json(['data' => $order]);
    }

    public function refund(Request $request, Order $order)
    {
        $request->validate(['confirm' => ['accepted']]);
        return response()->json(['data' => $this->refunds->fullRefund($order, $request)]);
    }

    private function orderFromStripe(array $provider): Order
    {
        $metadata = $this->stripe->metadata($provider);
        $orderNumber = $metadata['order_number'] ?? $provider['client_reference_id'] ?? null;
        if (! $orderNumber && ! empty($provider['id'])) {
            $transaction = PaymentTransaction::where('gateway', 'stripe')
                ->where('provider_reference', (string) $provider['id'])->first();
            $orderNumber = $transaction?->order?->order_number;
        }
        if (! $orderNumber) {
            throw ValidationException::withMessages(['metadata' => ['Unable to correlate Stripe payment to an order.']]);
        }
        return Order::where('order_number', $orderNumber)->firstOrFail();
    }

    private function orderFromProvider(array $provider, ?string $fallbackOrderNumber = null): Order
    {
        $metadata = $this->piprapay->metadata($provider);
        $orderNumber = $metadata['order_number'] ?? $fallbackOrderNumber;
        if (! $orderNumber) {
            $ppId = (string) ($provider['pp_id'] ?? $provider['transaction_id'] ?? '');
            $transaction = PaymentTransaction::where('gateway', 'piprapay')->where('provider_transaction_id', $ppId)->first();
            $orderNumber = $transaction?->order?->order_number;
        }
        if (! $orderNumber) {
            throw ValidationException::withMessages(['metadata' => ['Unable to correlate PipraPay payment to an order.']]);
        }
        return Order::where('order_number', $orderNumber)->firstOrFail();
    }

    private function frontendCheckoutResultUrl(?Order $order, ?string $ppId, string $status): string
    {
        $query = array_filter(['order' => $order?->order_number, 'pp_id' => $ppId, 'payment_status' => $status]);
        return rtrim((string) env('FRONTEND_URL', url('/')), '/').'/checkout/success?'.http_build_query($query);
    }
}
