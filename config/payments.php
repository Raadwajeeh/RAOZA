<?php

return [
    'default' => env('PAYMENT_PROVIDER', env('APP_ENV') === 'local' ? 'demo' : 'mollie'),
    'creation_retry_minutes' => (int) env('PAYMENT_CREATION_RETRY_MINUTES', 45),
    'creation_claim_minutes' => (int) env('PAYMENT_CREATION_CLAIM_MINUTES', 2),
    'mollie' => ['api_key' => env('MOLLIE_API_KEY'), 'base_url' => env('MOLLIE_API_BASE_URL', 'https://api.mollie.com/v2')],
];
