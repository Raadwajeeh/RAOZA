<?php

namespace App\Domain\Payments\Data;

final readonly class ProviderRefundRequest
{
    public function __construct(
        public string $paymentId,
        public int $amount,
        public string $currency,
        public string $idempotencyKey,
        public string $description,
        public int $internalRefundId,
    ) {}
}
