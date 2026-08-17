<?php

return [
    'postmark' => ['key' => env('POSTMARK_API_KEY')],
    'resend' => ['key' => env('RESEND_API_KEY')],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'slack' => ['notifications' => [
        'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
        'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
    ]],
    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', true),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'api_base' => env('STRIPE_API_BASE', 'https://api.stripe.com/v1'),
        'website' => env('STRIPE_WEBSITE', 'everydaylighter.com'),
        'payment_method_types' => array_values(array_filter(array_map('trim', explode(',', env('STRIPE_PAYMENT_METHOD_TYPES', 'card'))))),
        'automatic_tax' => env('STRIPE_AUTOMATIC_TAX', false),
        'billing_address_collection' => env('STRIPE_BILLING_ADDRESS_COLLECTION', 'auto'),
        'webhook_tolerance' => env('STRIPE_WEBHOOK_TOLERANCE_SECONDS', 300),
    ],
    'piprapay' => [
        'enabled' => env('PIPRAPAY_ENABLED', false),
        'base_url' => env('PIPRAPAY_BASE_URL'),
        'api_key' => env('PIPRAPAY_API_KEY'),
        'currency' => env('PIPRAPAY_CURRENCY', 'BDT'),
        'webhook_url' => env('PIPRAPAY_WEBHOOK_URL'),
        'return_url' => env('PIPRAPAY_RETURN_URL'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],
    'meta' => [
        'pixel_enabled' => env('META_PIXEL_ENABLED', false),
        'pixel_id' => env('META_PIXEL_ID'),
        'capi_enabled' => env('META_CAPI_ENABLED', false),
        'capi_access_token' => env('META_CAPI_ACCESS_TOKEN'),
        'graph_api_version' => env('META_GRAPH_API_VERSION', 'v25.0'),
        'capi_test_event_code' => env('META_CAPI_TEST_EVENT_CODE'),
        'capi_timeout_seconds' => env('META_CAPI_TIMEOUT_SECONDS', 5),
        'require_marketing_consent' => env('META_MARKETING_CONSENT_REQUIRED', false),
        'allow_local_pixel' => env('META_PIXEL_ALLOW_LOCALHOST', false),
    ],
];
