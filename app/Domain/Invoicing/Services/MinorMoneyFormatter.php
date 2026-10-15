<?php

namespace App\Domain\Invoicing\Services;

final class MinorMoneyFormatter
{
    public function format(int $amount, string $currency): string
    {
        $sign = $amount < 0 ? '-' : '';
        $absolute = abs($amount);
        $major = intdiv($absolute, 100);
        $minor = $absolute % 100;

        return $sign.strtoupper($currency).' '.number_format($major, 0, '.', ',').'.'.str_pad((string) $minor, 2, '0', STR_PAD_LEFT);
    }
}
