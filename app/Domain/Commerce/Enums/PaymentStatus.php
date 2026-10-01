<?php
namespace App\Domain\Commerce\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::PartiallyRefunded, self::Refunded], true);
    }

    public function canAcceptPayment(): bool
    {
        return in_array($this, [self::Unpaid, self::Pending, self::Failed], true);
    }

    public function allowsFulfillment(): bool
    {
        return in_array($this, [self::Paid, self::PartiallyRefunded], true);
    }
}
