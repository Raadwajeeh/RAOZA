<?php

namespace App\Domain\Payments\Data;

use App\Domain\Payments\Models\Payment;

final readonly class PaymentSyncResult
{
    public function __construct(
        public Payment $payment,
        public bool $becamePaid,
        public bool $eventProcessed,
    ) {}
}
