<?php

namespace App\Domain\Payments\Data;

final readonly class ProviderRefund
{
    public function __construct(
        public string $id,
        public string $paymentId,
        public string $status,
        public int $amount,
        public string $currency,
        public ?string $createdAt = null,
        public ?string $reference = null,
    ) {}
}
