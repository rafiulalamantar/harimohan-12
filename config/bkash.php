<?php

return [
    // true = sandbox, false = live merchant account
    'sandbox' => env('BKASH_SANDBOX', true),

    'base_url' => env('BKASH_SANDBOX', true)
        ? env('BKASH_SANDBOX_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta')
        : env('BKASH_LIVE_URL', 'https://tokenized.pay.bka.sh/v1.2.0-beta'),

    // Credentials from bKash merchant onboarding (PGW Tokenized Checkout)
    'username' => env('BKASH_USERNAME'),
    'password' => env('BKASH_PASSWORD'),
    'app_key' => env('BKASH_APP_KEY'),
    'app_secret' => env('BKASH_APP_SECRET'),

    'timeout' => (int) env('BKASH_TIMEOUT', 30),
];
