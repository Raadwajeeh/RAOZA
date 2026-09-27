<?php
namespace Tests\Unit\Catalog;
use App\Domain\Catalog\VariantSignature;
use PHPUnit\Framework\TestCase;
class VariantSignatureTest extends TestCase
{
    public function test_signature_is_stable_regardless_of_input_order(): void
    {
        $this->assertSame('2-8-14', VariantSignature::fromOptionValueIds([14, 2, 8]));
    }
    public function test_duplicate_values_do_not_change_signature(): void
    {
        $this->assertSame('2-8', VariantSignature::fromOptionValueIds([8, 2, 8]));
    }
}
