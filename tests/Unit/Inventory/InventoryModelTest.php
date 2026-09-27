<?php
namespace Tests\Unit\Inventory;
use App\Domain\Inventory\Models\Inventory;
use LogicException;
use PHPUnit\Framework\TestCase;
class InventoryModelTest extends TestCase
{
    public function test_available_quantity_is_on_hand_minus_reserved(): void
    {
        $inventory = new Inventory(['quantity_on_hand'=>12,'quantity_reserved'=>4]);
        $this->assertSame(8, $inventory->availableQuantity());
        $inventory->assertConsistent();
    }
    public function test_reserved_cannot_exceed_on_hand(): void
    {
        $this->expectException(LogicException::class);
        (new Inventory(['quantity_on_hand'=>2,'quantity_reserved'=>3]))->assertConsistent();
    }
}
