<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Store-policy compliance
    |--------------------------------------------------------------------------
    | Real-money gateways are restricted to *physical goods / merchant orders*
    | only (App Store / Google Play policy). Digital coins, gifts, VIP, and any
    | virtual currency MUST continue to use in-app purchase / offline recharge
    | and are never routed through these gateways. The payable allow-list below
    | is enforced server-side in PaymentService::assertPayableAllowed().
    */
    'allowed_payable_types' => [
        \App\Features\LiveShopping\Models\ShopOrder::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default gateway + currency
    |--------------------------------------------------------------------------
    */
    'default_gateway' => env('PAYMENTS_DEFAULT_GATEWAY', 'stripe'),
    'default_currency' => strtoupper((string) env('PAYMENTS_DEFAULT_CURRENCY', 'USD')),

    /*
    |--------------------------------------------------------------------------
    | Pricing bridge
    |--------------------------------------------------------------------------
    | Merchant orders are denominated internally in coins. For real-money
    | settlement we convert coins -> fiat minor units (e.g. cents / fils).
    | amount_minor = round(total_coins * coin_to_minor_units[currency]).
    */
    'pricing' => [
        'coin_to_minor_units' => [
            'USD' => (float) env('PAYMENTS_RATE_USD', 1.0),
            'EUR' => (float) env('PAYMENTS_RATE_EUR', 0.92),
            'IQD' => (float) env('PAYMENTS_RATE_IQD', 1310.0),
            'KWD' => (float) env('PAYMENTS_RATE_KWD', 0.31),
        ],
        // Currencies whose minor unit factor is 1 (no decimals), e.g. IQD.
        'zero_decimal_currencies' => ['IQD', 'JPY', 'KRW'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax
    |--------------------------------------------------------------------------
    | Fallback tax rate in basis points (100 bps = 1%). Country/region specific
    | rates live in the `tax_rates` table and override this default.
    */
    'tax' => [
        'default_rate_bps' => (int) env('PAYMENTS_TAX_DEFAULT_BPS', 0),
        'prices_include_tax' => filter_var(env('PAYMENTS_TAX_INCLUSIVE', false), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Revenue sharing
    |--------------------------------------------------------------------------
    | Default split in basis points. platform + merchant (+ agency) must sum to
    | 10000. Seller/category specific overrides live in `revenue_share_rules`.
    | If the seller belongs to an agency, agency_bps is carved out of the
    | merchant share.
    */
    'revenue_share' => [
        'platform_bps' => (int) env('PAYMENTS_REVSHARE_PLATFORM_BPS', 2000),
        'agency_bps' => (int) env('PAYMENTS_REVSHARE_AGENCY_BPS', 500),
        // merchant receives the remainder (10000 - platform - agency).
        'auto_settle' => filter_var(env('PAYMENTS_REVSHARE_AUTO_SETTLE', false), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoicing
    |--------------------------------------------------------------------------
    */
    'invoice' => [
        'number_prefix' => env('PAYMENTS_INVOICE_PREFIX', 'CIV'),
        'seller_name' => env('PAYMENTS_SELLER_NAME', 'Civin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateways
    |--------------------------------------------------------------------------
    | `enabled` toggles availability. `mode` is live|sandbox. Credentials are
    | pulled from env. Never commit real secrets.
    */
    'gateways' => [
        'stripe' => [
            'enabled' => filter_var(env('STRIPE_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('STRIPE_MODE', 'sandbox'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
        ],
        'paypal' => [
            'enabled' => filter_var(env('PAYPAL_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
            'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com'),
        ],
        'apple_pay' => [
            'enabled' => filter_var(env('APPLE_PAY_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('APPLE_PAY_MODE', 'sandbox'),
            'bundle_id' => env('APPLE_BUNDLE_ID'),
            'shared_secret' => env('APPLE_SHARED_SECRET'),
            'verify_url' => env('APPLE_VERIFY_URL', 'https://sandbox.itunes.apple.com/verifyReceipt'),
            'verify_url_prod' => env('APPLE_VERIFY_URL_PROD', 'https://buy.itunes.apple.com/verifyReceipt'),
        ],
        'google_play' => [
            'enabled' => filter_var(env('GOOGLE_PLAY_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('GOOGLE_PLAY_MODE', 'sandbox'),
            'package_name' => env('GOOGLE_PLAY_PACKAGE'),
            'service_account_json' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON'),
            'base_url' => env('GOOGLE_PLAY_BASE_URL', 'https://androidpublisher.googleapis.com'),
        ],
        'myfatoorah' => [
            'enabled' => filter_var(env('MYFATOORAH_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('MYFATOORAH_MODE', 'sandbox'),
            'api_key' => env('MYFATOORAH_API_KEY'),
            'base_url' => env('MYFATOORAH_BASE_URL', 'https://apitest.myfatoorah.com'),
        ],
        'zaincash' => [
            'enabled' => filter_var(env('ZAINCASH_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('ZAINCASH_MODE', 'sandbox'),
            'merchant_id' => env('ZAINCASH_MERCHANT_ID'),
            'secret' => env('ZAINCASH_SECRET'),
            'msisdn' => env('ZAINCASH_MSISDN'),
            'base_url' => env('ZAINCASH_BASE_URL', 'https://test.zaincash.iq'),
        ],
        'fastpay' => [
            'enabled' => filter_var(env('FASTPAY_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('FASTPAY_MODE', 'sandbox'),
            'store_id' => env('FASTPAY_STORE_ID'),
            'store_password' => env('FASTPAY_STORE_PASSWORD'),
            'base_url' => env('FASTPAY_BASE_URL', 'https://dev-ws.fast-pay.iq'),
        ],
        'qi_card' => [
            'enabled' => filter_var(env('QI_CARD_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('QI_CARD_MODE', 'sandbox'),
            'terminal_id' => env('QI_CARD_TERMINAL_ID'),
            'merchant_id' => env('QI_CARD_MERCHANT_ID'),
            'secret' => env('QI_CARD_SECRET'),
            'base_url' => env('QI_CARD_BASE_URL', 'https://uat-sandbox.qi.iq'),
        ],
        'fib' => [
            'enabled' => filter_var(env('FIB_ENABLED', false), FILTER_VALIDATE_BOOL),
            'mode' => env('FIB_MODE', 'sandbox'),
            'client_id' => env('FIB_CLIENT_ID'),
            'client_secret' => env('FIB_CLIENT_SECRET'),
            'base_url' => env('FIB_BASE_URL', 'https://fib.stage.fib.iq'),
        ],
    ],
];
