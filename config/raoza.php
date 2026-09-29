<?php

return [
    'brand' => [
        'name' => 'RAOZA',
        'domain' => 'raoza.nl',
        'market' => 'NL',
        'currency' => 'EUR',
    ],
    'store' => [
        'brand_name' => 'RAOZA',
        'company_name' => 'RAOZA Studio',
        'contact_email' => 'hello@raoza.nl',
        'support_email' => 'hello@raoza.nl',
        'contact_phone' => null,
        'street' => null,
        'house_number' => null,
        'addition' => null,
        'postal_code' => null,
        'city' => 'Amsterdam',
        'country' => 'Netherlands',
        'country_code' => 'NL',
        'registration_number' => null,
        'vat_number' => null,
        'currency' => 'EUR',
        'locale' => 'en-NL',
        'shipping_origin' => 'Netherlands',
        'customer_service' => 'Email support is available for orders, delivery and returns. We aim to reply within two business days.',
        'instagram_url' => null,
    ],
    'colors' => [
        'primary' => '#330313',
        'secondary' => '#602032',
        'cream' => '#FFF6E6',
        'gold' => '#D1AF6F',
        'black' => '#111111',
    ],
    'typography' => [
        'display' => 'Playfair Display',
        'ui' => 'Plus Jakarta Sans',
    ],
    'consent' => ['policy_version' => env('CONSENT_POLICY_VERSION', '2026-09-v1')],
    'analytics' => ['provider' => env('ANALYTICS_PROVIDER', 'none')],
];
