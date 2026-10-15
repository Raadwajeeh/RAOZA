<?php

return [
    // Official issuance remains fail-closed until the owner/accountant approves every policy below.
    'issuance_enabled' => (bool) env('INVOICE_ISSUANCE_ENABLED', false),
    'accounting_policy_approved' => (bool) env('INVOICE_ACCOUNTING_POLICY_APPROVED', false),
    'seller_profile_approved' => (bool) env('INVOICE_SELLER_PROFILE_APPROVED', false),
    'numbering' => [
        'approved' => (bool) env('INVOICE_NUMBERING_APPROVED', false),
        'scope' => env('INVOICE_NUMBERING_SCOPE'),
        'invoice_prefix' => env('INVOICE_NUMBER_PREFIX'),
        'credit_note_prefix' => env('CREDIT_NOTE_NUMBER_PREFIX'),
        'padding' => (int) env('INVOICE_NUMBER_PADDING', 6),
    ],
    'credit_notes' => [
        'allocation_method' => env('CREDIT_NOTE_ALLOCATION_METHOD'),
    ],
    'disk' => env('INVOICE_FILESYSTEM_DISK', 'local'),
];
