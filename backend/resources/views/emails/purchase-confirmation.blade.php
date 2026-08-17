<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Your EverydayLighter purchase is ready</title>
</head>
<body style="margin:0; padding:0; background:#f6f0eb; color:#30252f; font-family:Arial, Helvetica, sans-serif;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; line-height:1px;">{{ $preheader }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f0eb; margin:0; padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; max-width:640px; background:#fffdf9; border-radius:20px; overflow:hidden; border:1px solid #eadfd9;">
    <tr><td style="padding:28px; border-bottom:1px solid #eadfd9;">
        <div style="font-family:Georgia, serif; font-size:25px; font-weight:700; color:#5e314f;">EverydayLighter</div>
        <div style="margin-top:5px; font-size:13px; color:#897680;">Practical tools for a life that feels lighter.</div>
    </td></tr>

    <tr><td style="padding:28px;">
        <div style="display:inline-block; padding:7px 11px; border-radius:999px; background:#e9f2e8; color:#35603c; font-size:12px; font-weight:700;">Purchase confirmed</div>
        <h1 style="margin:18px 0 8px; font-family:Georgia, serif; font-size:30px; line-height:37px; color:#3a2835;">Your purchase is ready, {{ $order->customer_name }}.</h1>
        <p style="margin:0; font-size:15px; line-height:24px; color:#75656f;">Your payment was confirmed and your digital product is ready to access.</p>
    </td></tr>

    <tr><td style="padding:0 28px 24px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eadfd9; border-radius:15px;">
            <tr>
                <td style="padding:18px; border-bottom:1px solid #f0e8e3;"><div style="font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#a08e98; font-weight:700;">Order</div><div style="margin-top:6px; font-size:15px; color:#3a2835; font-weight:700;">{{ $order->order_number }}</div></td>
                <td style="padding:18px; border-bottom:1px solid #f0e8e3;"><div style="font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#a08e98; font-weight:700;">Total</div><div style="margin-top:6px; font-size:17px; color:#3a2835; font-weight:800;">{{ $total }}</div></td>
            </tr>
            <tr>
                <td style="padding:18px;"><div style="font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#a08e98; font-weight:700;">Purchased</div><div style="margin-top:6px; font-size:14px; color:#594953; font-weight:600;">{{ $orderPlacedAt }}</div></td>
                <td style="padding:18px;"><div style="font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#a08e98; font-weight:700;">Payment</div><div style="margin-top:6px; font-size:14px; color:#35603c; font-weight:800;">Paid</div></td>
            </tr>
        </table>
    </td></tr>

    <tr><td style="padding:0 28px 24px;">
        <h2 style="margin:0 0 14px; font-family:Georgia, serif; font-size:20px; color:#3a2835;">Your purchase</h2>
        @foreach ($items as $item)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px; border:1px solid #eadfd9; border-radius:14px;">
                <tr>
                    <td width="82" style="padding:14px; vertical-align:top;">
                        @if ($item['cover'])
                            <img src="{{ $item['cover'] }}" alt="{{ $item['name'] }}" width="60" height="72" style="display:block; width:60px; height:72px; object-fit:cover; border-radius:9px; border:1px solid #eadfd9;">
                        @else
                            <div style="width:60px; height:72px; border-radius:9px; background:#f0e4eb; color:#6e3a5d; text-align:center; line-height:72px; font-weight:800;">EL</div>
                        @endif
                    </td>
                    <td style="padding:14px 14px 14px 0; vertical-align:top;">
                        <div style="font-size:16px; line-height:22px; color:#3a2835; font-weight:800;">{{ $item['name'] }}</div>
                        <div style="margin-top:4px; font-size:13px; color:#897680;">{{ $item['type'] }} × {{ $item['quantity'] }}</div>
                        <div style="margin-top:9px; font-size:13px; color:#3a2835; font-weight:700;">{{ $item['total'] }}</div>
                    </td>
                </tr>
            </table>
        @endforeach

        <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:16px;"><tr><td style="border-radius:999px; background:#6e3a5d;"><a href="{{ $accessUrl }}" style="display:inline-block; padding:14px 24px; color:#ffffff; text-decoration:none; font-size:15px; font-weight:800;">Access your purchase</a></td></tr></table>
    </td></tr>

    @if (count($communities) > 0)
    <tr><td style="padding:0 28px 24px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf5f8; border:1px solid #ead7e2; border-radius:15px;"><tr><td style="padding:22px;">
            <h2 style="margin:0; font-family:Georgia, serif; font-size:19px; color:#3a2835;">Included community access</h2>
            @foreach ($communities as $community)
                <p style="margin:14px 0 0;"><a href="{{ $community['url'] }}" style="color:#6e3a5d; font-weight:700; text-decoration:none;">{{ $community['name'] }}</a></p>
            @endforeach
        </td></tr></table>
    </td></tr>
    @endif

    <tr><td style="padding:0 28px 28px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#4a3545; border-radius:15px;"><tr><td style="padding:21px;">
            <div style="font-size:16px; color:#ffffff; font-weight:800;">Need help?</div>
            <p style="margin:7px 0 0; font-size:14px; line-height:22px; color:#eadfe6;">Reply to this email or contact <a href="mailto:{{ $supportEmail }}" style="color:#ffffff;">{{ $supportEmail }}</a>.</p>
        </td></tr></table>
    </td></tr>

    <tr><td style="padding:22px 28px 28px; background:#faf6f2; border-top:1px solid #eadfd9; text-align:center;">
        <div style="font-family:Georgia, serif; font-size:16px; color:#5e314f; font-weight:700;">EverydayLighter</div>
        <div style="margin-top:7px;"><a href="{{ $frontendUrl }}" style="color:#6e3a5d; font-size:12px; text-decoration:none;">everydaylighter.com</a></div>
        <p style="margin:12px 0 0; font-size:11px; line-height:18px; color:#a08e98;">This transactional email was sent because a purchase was completed with EverydayLighter.</p>
        <p style="margin:7px 0 0; font-size:11px; color:#a08e98;">&copy; {{ $year }} EverydayLighter</p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
