<?php

namespace App\Domain\Commerce\Services;

use InvalidArgumentException;

final class MoneyService
{
    public function extractIncludedTax(int $grossAmount, int $rateBasisPoints): int
    {
        if ($grossAmount < 0 || $rateBasisPoints < 0) {
            throw new InvalidArgumentException('Money amounts and tax rates cannot be negative.');
        }

        if ($grossAmount === 0 || $rateBasisPoints === 0) {
            return 0;
        }

        $denominator = 10_000 + $rateBasisPoints;

        return intdiv(($grossAmount * $rateBasisPoints) + intdiv($denominator, 2), $denominator);
    }

    /**
     * Allocate an integer amount proportionally using largest remainders.
     * Equal remainders are resolved by the original input order.
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    public function allocate(int $amount, array $weights): array
    {
        if ($amount < 0 || collect($weights)->contains(fn (int $weight): bool => $weight < 0)) {
            throw new InvalidArgumentException('Allocation amounts and weights cannot be negative.');
        }

        $weightTotal = array_sum($weights);

        if ($amount === 0 || $weightTotal === 0) {
            return array_fill(0, count($weights), 0);
        }

        $allocated = [];
        $remainders = [];

        foreach ($weights as $index => $weight) {
            $product = $amount * $weight;
            $allocated[$index] = intdiv($product, $weightTotal);
            $remainders[$index] = $product % $weightTotal;
        }

        $remaining = $amount - array_sum($allocated);
        $indices = array_keys($weights);
        usort($indices, fn (int $left, int $right): int => $remainders[$right] <=> $remainders[$left] ?: $left <=> $right);

        for ($position = 0; $position < $remaining; $position++) {
            $allocated[$indices[$position]]++;
        }

        ksort($allocated);

        return array_values($allocated);
    }
}
