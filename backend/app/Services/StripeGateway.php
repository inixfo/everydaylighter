<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StripeGateway implements PaymentGateway
{
    public function initiate(Order $order): array
    {
        if (! config('services.stripe.enabled', true)) {
            throw ValidationException::withMessages(['gateway' => ['Stripe is disabled.']]);
        }

        if ((int) $order->total_minor <= 0) {
            throw ValidationException::withMessages(['gateway' => ['Stripe checkout requires a positive order total.']]);
        }

        $order->loadMissing('items');
        $item = $order->items->first();
        if (! $item) {
            throw ValidationException::withMessages(['order' => ['This order does not contain a purchasable item.']]);
        }

        $attemptUuid = (string) Str::uuid();
        $metadata = $this->checkoutMetadata($order);
        $payload = [
            'mode' => 'payment',
            'client_reference_id' => $order->order_number,
            'customer_email' => $order->customer_email,
            'success_url' => $this->successUrl($order),
            'cancel_url' => $this->cancelUrl($order),
            'locale' => 'auto',
            'billing_address_collection' => (string) config('services.stripe.billing_address_collection', 'auto'),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower((string) $order->currency),
                    'unit_amount' => (int) $order->total_minor,
                    'product_data' => ['name' => $item->product_name],
                ],
            ]],
            'metadata' => $metadata,
            'payment_intent_data' => ['metadata' => $metadata],
        ];

        $paymentMethods = array_values(array_filter((array) config('services.stripe.payment_method_types', ['card'])));
        if ($paymentMethods !== []) {
            $payload['payment_method_types'] = $paymentMethods;
        }
        if ((bool) config('services.stripe.automatic_tax', false)) {
            $payload['automatic_tax'] = ['enabled' => true];
        }

        $transaction = PaymentTransaction::updateOrCreate(
            ['order_id' => $order->id, 'gateway' => $this->name()],
            [
                'uuid' => $attemptUuid,
                'provider_transaction_id' => null,
                'provider_reference' => null,
                'amount_minor' => $order->total_minor,
                'currency' => strtoupper((string) $order->currency),
                'status' => 'initiated',
                'normalized_state' => 'initiated',
                'initiated_at' => now(),
                'raw_response' => ['request_metadata' => $metadata],
            ]
        );

        $response = Http::timeout(20)
            ->acceptJson()
            ->asForm()
            ->withBasicAuth($this->secretKey(), '')
            ->post($this->endpoint('/checkout/sessions'), $payload);

        if (! $response->successful()) {
            $this->logProviderFailure('checkout_session', $response->status(), $response->json(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
            $transaction->forceFill([
                'status' => 'failed',
                'normalized_state' => 'initiate_failed',
                'failed_at' => now(),
                'raw_response' => $this->safePayload($response->json() ?: ['message' => $response->body()]),
            ])->save();
            throw ValidationException::withMessages(['gateway' => ['Stripe could not start checkout. Please try again.']]);
        }

        $session = $response->json();
        if (! is_array($session) || blank($session['id'] ?? null) || blank($session['url'] ?? null)) {
            throw ValidationException::withMessages(['gateway' => ['Stripe returned an invalid Checkout Session.']]);
        }

        $transaction->forceFill([
            'provider_reference' => (string) $session['id'],
            'status' => 'pending',
            'normalized_state' => 'pending',
            'raw_response' => $this->safePayload($session),
        ])->save();

        return [
            'gateway' => $this->name(),
            'order_number' => $order->order_number,
            'payment_attempt_uuid' => $attemptUuid,
            'provider_payment_id' => $session['id'],
            'checkout_session_id' => $session['id'],
            'checkout_url' => $session['url'],
            'currency' => strtoupper((string) $order->currency),
            'amount_minor' => (int) $order->total_minor,
        ];
    }

    public function verify(string $providerPaymentId): array
    {
        if ($providerPaymentId === '' || ! str_starts_with($providerPaymentId, 'cs_')) {
            throw ValidationException::withMessages(['session_id' => ['A valid Stripe Checkout Session ID is required.']]);
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->withBasicAuth($this->secretKey(), '')
            ->get($this->endpoint('/checkout/sessions/'.rawurlencode($providerPaymentId)));

        if (! $response->successful()) {
            $this->logProviderFailure('checkout_session_verify', $response->status(), $response->json(), ['session_id' => $providerPaymentId]);
            throw ValidationException::withMessages(['gateway' => ['Stripe could not verify this payment yet.']]);
        }

        $session = $response->json();
        if (! is_array($session)) {
            throw ValidationException::withMessages(['gateway' => ['Stripe returned an invalid verification response.']]);
        }
        return $session;
    }

    public function normalizeVerified(Order $order, array $provider): array
    {
        $sessionId = (string) ($provider['id'] ?? '');
        $status = strtolower((string) ($provider['status'] ?? ''));
        $paymentStatus = strtolower((string) ($provider['payment_status'] ?? ''));
        $metadata = $this->metadata($provider);
        $amountMinor = (int) ($provider['amount_total'] ?? -1);
        $currency = strtoupper((string) ($provider['currency'] ?? ''));
        $paymentIntent = is_string($provider['payment_intent'] ?? null) ? (string) $provider['payment_intent'] : '';

        if (! str_starts_with($sessionId, 'cs_')) {
            throw ValidationException::withMessages(['session_id' => ['Stripe verification response did not include a Checkout Session ID.']]);
        }
        if ($status !== 'complete' || $paymentStatus !== 'paid') {
            throw ValidationException::withMessages(['status' => ['Stripe payment is not complete and paid.']]);
        }

        $this->assertMatchesOrder($order, $metadata, $amountMinor, $currency);
        if ($paymentIntent === '') {
            throw ValidationException::withMessages(['payment_intent' => ['Stripe did not return a PaymentIntent for this paid order.']]);
        }

        return [
            'state' => 'paid',
            'valid' => true,
            'order_number' => $order->order_number,
            'provider_transaction_id' => $paymentIntent,
            'validation_id' => $sessionId,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'payment_method' => 'stripe',
            'metadata' => $metadata,
            'raw' => $this->safePayload($provider),
        ];
    }

    public function normalizeFailedPayload(Order $order, array $payload, string $state): array
    {
        return [
            'state' => $state,
            'valid' => false,
            'order_number' => $order->order_number,
            'provider_transaction_id' => $payload['payment_intent'] ?? null,
            'validation_id' => $payload['id'] ?? null,
            'amount_minor' => (int) ($payload['amount_total'] ?? $order->total_minor),
            'currency' => strtoupper((string) ($payload['currency'] ?? $order->currency)),
            'metadata' => $this->metadata($payload),
            'raw' => $this->safePayload($payload),
        ];
    }

    public function validateWebhook(array $payload, ?string $receivedApiKey = null): array
    {
        if (! isset($payload['id'], $payload['type'], $payload['data']['object'])) {
            throw ValidationException::withMessages(['webhook' => ['Stripe webhook payload is incomplete.']]);
        }
        return $payload;
    }

    public function parseWebhook(string $rawPayload, ?string $signatureHeader): array
    {
        $secret = (string) config('services.stripe.webhook_secret');
        if ($secret === '') {
            throw ValidationException::withMessages(['webhook' => ['Stripe webhook secret is not configured.']]);
        }
        if (! is_string($signatureHeader) || $signatureHeader === '') {
            throw ValidationException::withMessages(['webhook' => ['Stripe-Signature header is missing.']]);
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't' && ctype_digit((string) $value)) $timestamp = (int) $value;
            if ($key === 'v1' && is_string($value) && $value !== '') $signatures[] = $value;
        }

        $tolerance = max(0, (int) config('services.stripe.webhook_tolerance', 300));
        if (! $timestamp || $signatures === [] || ($tolerance > 0 && abs(time() - $timestamp) > $tolerance)) {
            throw ValidationException::withMessages(['webhook' => ['Stripe webhook signature is invalid or expired.']]);
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawPayload, $secret);
        $matched = collect($signatures)->contains(fn (string $signature) => hash_equals($expected, $signature));
        if (! $matched) {
            throw ValidationException::withMessages(['webhook' => ['Stripe webhook signature verification failed.']]);
        }

        $event = json_decode($rawPayload, true);
        if (! is_array($event)) {
            throw ValidationException::withMessages(['webhook' => ['Stripe webhook JSON is invalid.']]);
        }
        return $this->validateWebhook($event);
    }

    public function refund(Order $order, string $providerPaymentId): array
    {
        if ($providerPaymentId === '' || ! str_starts_with($providerPaymentId, 'pi_')) {
            throw ValidationException::withMessages(['payment_intent' => ['Stripe refund requires a PaymentIntent ID.']]);
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->asForm()
            ->withBasicAuth($this->secretKey(), '')
            ->withHeaders(['Idempotency-Key' => 'full-refund-'.$order->uuid])
            ->post($this->endpoint('/refunds'), [
                'payment_intent' => $providerPaymentId,
                'amount' => (int) $order->total_minor,
                'metadata' => [
                    'order_number' => $order->order_number,
                    'order_uuid' => $order->uuid,
                    'brand' => 'EverydayLighter',
                ],
            ]);

        if (! $response->successful()) {
            $this->logProviderFailure('refund', $response->status(), $response->json(), ['order_number' => $order->order_number]);
            throw ValidationException::withMessages(['gateway' => ['Stripe could not confirm the refund. Please try again.']]);
        }

        $refund = $response->json();
        if (! is_array($refund) || ($refund['status'] ?? null) !== 'succeeded') {
            throw ValidationException::withMessages(['gateway' => ['Stripe refund has not completed yet.']]);
        }

        return [
            'provider_refund_id' => (string) ($refund['id'] ?? ''),
            'provider_payment_id' => $providerPaymentId,
            'status' => 'refunded',
            'amount_minor' => (int) ($refund['amount'] ?? $order->total_minor),
            'currency' => strtoupper((string) ($refund['currency'] ?? $order->currency)),
            'raw' => $this->safePayload($refund),
        ];
    }

    public function name(): string { return 'stripe'; }
    public function metadata(array $payload): array { return is_array($payload['metadata'] ?? null) ? $payload['metadata'] : []; }

    private function checkoutMetadata(Order $order): array
    {
        $orderMetadata = is_array($order->metadata) ? $order->metadata : [];
        $attribution = is_array($orderMetadata['order_attribution'] ?? null) ? $orderMetadata['order_attribution'] : [];
        $lastTouch = is_array($attribution['last_touch'] ?? null) ? $attribution['last_touch'] : [];
        $item = $order->items->first();
        return array_filter([
            'order_id' => (string) $order->uuid,
            'order_uuid' => (string) $order->uuid,
            'order_number' => (string) $order->order_number,
            'brand' => 'EverydayLighter',
            'website' => (string) config('services.stripe.website', 'everydaylighter.com'),
            'product_key' => (string) ($item?->product_slug ?? 'digital-product'),
            'landing_page_id' => isset($orderMetadata['landing_page_id']) ? (string) $orderMetadata['landing_page_id'] : null,
            'offer_key' => isset($orderMetadata['offer_key']) ? (string) $orderMetadata['offer_key'] : null,
            'utm_source' => $this->metadataString($lastTouch['source'] ?? $lastTouch['utm_source'] ?? null),
            'utm_campaign' => $this->metadataString($lastTouch['campaign'] ?? $lastTouch['utm_campaign'] ?? null),
            'utm_content' => $this->metadataString($lastTouch['content'] ?? $lastTouch['utm_content'] ?? null),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function assertMatchesOrder(Order $order, array $metadata, int $amountMinor, string $currency): void
    {
        $metadataOrderId = $metadata['order_id'] ?? $metadata['order_uuid'] ?? null;
        if (! is_string($metadataOrderId) || ! hash_equals((string) $order->uuid, $metadataOrderId)) {
            throw ValidationException::withMessages(['metadata' => ['Stripe metadata does not match this order.']]);
        }
        if (($metadata['order_number'] ?? null) !== $order->order_number) {
            throw ValidationException::withMessages(['metadata' => ['Stripe order number does not match this order.']]);
        }
        if ($amountMinor !== (int) $order->total_minor) {
            throw ValidationException::withMessages(['amount' => ['Stripe payment amount does not match the server-side order total.']]);
        }
        if ($currency !== strtoupper((string) $order->currency)) {
            throw ValidationException::withMessages(['currency' => ['Stripe payment currency does not match the order currency.']]);
        }
    }

    private function successUrl(Order $order): string
    {
        return $this->frontendUrl().'/checkout/success?order='.rawurlencode($order->order_number).'&session_id={CHECKOUT_SESSION_ID}';
    }

    private function cancelUrl(Order $order): string
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $query = ['payment' => 'cancelled'];
        if (! empty($metadata['landing_page_id'])) {
            $page = LandingPage::find($metadata['landing_page_id']);
            if ($page) {
                $query['lp'] = $page->slug;
                $query['offer'] = $metadata['offer_key'] ?? 'single';
            }
        }
        if (! isset($query['lp'])) {
            $item = $order->items->first();
            if ($item?->product_id) $query['product_id'] = $item->product_id;
            elseif ($item?->bundle_id) $query['bundle_id'] = $item->bundle_id;
        }
        return $this->frontendUrl().'/checkout?'.http_build_query($query);
    }

    private function frontendUrl(): string { return rtrim((string) env('FRONTEND_URL', config('app.url')), '/'); }

    private function secretKey(): string
    {
        $secret = (string) config('services.stripe.secret_key');
        if ($secret === '' || ! str_starts_with($secret, 'sk_')) {
            throw ValidationException::withMessages(['gateway' => ['Stripe secret key is not configured.']]);
        }
        return $secret;
    }

    private function endpoint(string $path): string { return rtrim((string) config('services.stripe.api_base', 'https://api.stripe.com/v1'), '/').$path; }
    private function metadataString(mixed $value): ?string { return is_scalar($value) ? Str::limit(trim((string) $value), 450, '') : null; }

    private function logProviderFailure(string $endpoint, int $status, mixed $body, array $context = []): void
    {
        $payload = is_array($body) ? $body : [];
        Log::warning('Stripe provider request failed.', array_merge($context, [
            'endpoint' => $endpoint,
            'http_status' => $status,
            'provider_error_code' => data_get($payload, 'error.code'),
            'provider_error_type' => data_get($payload, 'error.type'),
            'provider_error_message' => data_get($payload, 'error.message'),
        ]));
    }

    private function safePayload(array $payload): array
    {
        return array_filter([
            'id' => $payload['id'] ?? null,
            'object' => $payload['object'] ?? null,
            'status' => $payload['status'] ?? null,
            'payment_status' => $payload['payment_status'] ?? null,
            'payment_intent' => $payload['payment_intent'] ?? null,
            'amount_total' => $payload['amount_total'] ?? $payload['amount'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
        ], fn ($value) => $value !== null);
    }
}
