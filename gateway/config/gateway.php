<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | All amounts are stored and exchanged as integers in the currency's smallest
    | unit in use. The Sudanese pound is used in whole pounds, so 18500 means
    | SDG 18,500 and there are no decimals.
    |
    */

    'currency' => env('GATEWAY_CURRENCY', 'SDG'),

    /*
    |--------------------------------------------------------------------------
    | Support contact shown to merchants
    |--------------------------------------------------------------------------
    */

    'documents_disk' => env('GATEWAY_DOCUMENTS_DISK', 'local'),

    'support_email' => env('GATEWAY_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', 'support@example.com')),

    'currency_decimals' => (int) env('GATEWAY_CURRENCY_DECIMALS', 0),

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        // The smallest payment worth processing: below it card costs exceed the fee.
        'min_amount' => (int) env('GATEWAY_MIN_AMOUNT', 100),
        'max_products' => 100,
        'max_product_quantity' => 100,
        'max_unit_amount' => 5_000_000_000,
        'max_total_amount' => (int) env('GATEWAY_MAX_TOTAL_AMOUNT', 50_000_000),
        'max_metadata_keys' => 50,
        'max_page_size' => 100,
    ],

    'checkout' => [
        'default_expiry_minutes' => 1440,
        'min_expiry_minutes' => 30,
        'max_expiry_minutes' => 10080,
        'max_challenge_attempts' => 3,
    ],

    'payment_intents' => [
        'default_expiry_minutes' => 30,
        'min_expiry_minutes' => 30,
        'max_expiry_minutes' => 1440,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default merchant pricing
    |--------------------------------------------------------------------------
    |
    | Basis points (250 = 2.5%) plus a fixed fee in pounds. Admins can override
    | both values per merchant.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Settlements
    |--------------------------------------------------------------------------
    |
    | Captured payments are held this many days before they can be paid out,
    | leaving room for refunds and fraud checks (T+2 by default).
    |
    */

    'settlements' => [
        'hold_days' => (int) env('GATEWAY_SETTLEMENT_HOLD_DAYS', 2),
    ],

    'fees' => [
        'rate_bps' => (int) env('GATEWAY_FEE_RATE_BPS', 250),
        'fixed' => (int) env('GATEWAY_FEE_FIXED', 0),
        'minimum' => (int) env('GATEWAY_FEE_MINIMUM', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Card processors
    |--------------------------------------------------------------------------
    |
    | Test mode always runs through the sandbox processor. Live mode uses the
    | driver named here; "unconfigured" refuses every live charge until a real
    | acquirer driver is registered in PaymentProcessorResolver.
    |
    */

    'processors' => [
        'live' => env('GATEWAY_LIVE_PROCESSOR', 'unconfigured'),
    ],

    'sandbox' => [
        'otp_code' => '1234',

        // "direct": the card form is on our payment page. "hosted": the cardholder is sent to the
        // simulated bank page, exactly like a hosted-page acquirer integration in live mode.
        'flow' => env('GATEWAY_SANDBOX_FLOW', 'direct'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */

    'webhooks' => [
        'timeout_seconds' => 10,
        'max_attempts' => 9,
        'signature_header' => 'matmoom-signature',
        'timestamp_header' => 'matmoom-timestamp',
        'allow_private_hosts' => (bool) env('GATEWAY_WEBHOOKS_ALLOW_PRIVATE_HOSTS', false),
        'retention_days' => (int) env('GATEWAY_WEBHOOK_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */

    'api' => [
        'key_header' => 'matmoom-api-key',
        'rate_limit_per_minute' => (int) env('GATEWAY_API_RATE_LIMIT', 120),
        'ip_rate_limit_per_minute' => (int) env('GATEWAY_API_IP_RATE_LIMIT', 600),
        'idempotency_ttl_hours' => 24,
    ],

];
