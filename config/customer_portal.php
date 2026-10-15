<?php

$returnWindow = env('CUSTOMER_RETURN_WINDOW_DAYS');

return [
    'returns_enabled' => filter_var(env('CUSTOMER_RETURNS_ENABLED', true), FILTER_VALIDATE_BOOL),
    // Null deliberately means no time window has been approved/configured.
    'return_window_days' => is_numeric($returnWindow) ? max(1, min(365, (int) $returnWindow)) : null,
    'return_reasons' => [
        'changed_mind' => 'Changed my mind',
        'wrong_size' => 'Wrong size or fit',
        'damaged' => 'Arrived damaged',
        'wrong_item' => 'Wrong item received',
        'other' => 'Other',
    ],
];
