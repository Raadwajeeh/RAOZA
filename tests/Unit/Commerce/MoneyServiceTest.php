<?php

namespace Tests\Unit\Commerce;

use App\Domain\Commerce\Services\MoneyService;
use PHPUnit\Framework\TestCase;

class MoneyServiceTest extends TestCase
{
    public function test_it_extracts_included_vat_with_integer_half_up_rounding(): void
    {
        $money = new MoneyService;

        $this->assertSame(21, $money->extractIncludedTax(121, 2100));
        $this->assertSame(1, $money->extractIncludedTax(3, 2100));
        $this->assertSame(0, $money->extractIncludedTax(2, 2100));
        $this->assertSame(0, $money->extractIncludedTax(999, 0));
    }

    public function test_largest_remainder_allocation_is_exact_and_deterministic(): void
    {
        $money = new MoneyService;

        $this->assertSame([1, 0], $money->allocate(1, [1, 1]));
        $this->assertSame([16, 16, 15], $money->allocate(47, [90, 90, 90]));
        $this->assertSame(47, array_sum($money->allocate(47, [90, 90, 90])));
    }
}
