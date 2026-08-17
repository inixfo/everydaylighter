EverydayLighter
Practical tools for a life that feels lighter.

Purchase confirmed

Your purchase is ready, {{ $order->customer_name }}.

Order: {{ $order->order_number }}
Order placed: {{ $orderPlacedAt }}
@if ($paymentConfirmedAt)
Payment confirmed: {{ $paymentConfirmedAt }}
@endif
Total: {{ $total }}

Your purchase:
@foreach ($items as $item)
- {{ $item['name'] }} x {{ $item['quantity'] }} - {{ $item['total'] }}
@endforeach

Access your purchase:
{{ $accessUrl }}

@if (count($communities) > 0)
Community access:
@foreach ($communities as $community)
- {{ $community['name'] }}: {{ $community['url'] }}
@endforeach

@endif
Need help?
Reply to this email or contact {{ $supportEmail }}.

{{ $frontendUrl }}

This transactional email was sent because a purchase was completed with EverydayLighter.

Copyright {{ $year }} EverydayLighter
