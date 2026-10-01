<?php
return [
 'currency'=>'EUR',
 // Consumer-facing demo prices are VAT-inclusive. Tax is extracted for display/accounting, not added again.
 'vat_rate_basis_points'=>(int)env('COMMERCE_VAT_RATE_BPS',2100),
 'demo_mode'=>(bool)env('RAOZA_DEMO_MODE',false),
 'pending_order_reservation_minutes'=>(int)env('PENDING_ORDER_RESERVATION_MINUTES',30),
];
