<?php

return [
    // Alpha SMS (sms.net.bd) bulk SMS gateway; the "SMS members" admin page is disabled until the key is set.
    'api_key' => env('SMS_API_KEY'),

    'base_url' => env('SMS_BASE_URL', 'https://api.sms.net.bd'),

    // Optional: an approved masking name (e.g. HARIMOHAN12) and approved bulk campaign content ID.
    'sender_id' => env('SMS_SENDER_ID'),
    'content_id' => env('SMS_CONTENT_ID'),

    // Taka per SMS part, only for the cost estimate shown before sending (Alpha SMS Basic, non-masking).
    'rate' => (float) env('SMS_RATE', 0.40),

    'timeout' => (int) env('SMS_TIMEOUT', 20),
];
