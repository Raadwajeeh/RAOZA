<?php
namespace App\Domain\Inventory\Services;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
class InventoryService
{
    public function adjust(ProductVariant $variant, int $delta, InventoryMovementType $type, ?string $note = null, ?string $referenceType = null, int|string|null $referenceId = null): Inventory
    {
        if ($delta === 0) throw new InvalidArgumentException('Inventory adjustment cannot be zero.');
        return DB::transaction(function () use ($variant, $delta, $type, $note, $referenceType, $referenceId) {
            $inventory = Inventory::query()->where('variant_id', $variant->getKey())->lockForUpdate()->firstOrFail();
            $next = $inventory->quantity_on_hand + $delta;
            if ($next < $inventory->quantity_reserved) throw new RuntimeException('Adjustment would reduce on-hand stock below reserved stock.');
            $inventory->quantity_on_hand = $next;
            $inventory->assertConsistent();
            $inventory->save();
            InventoryMovement::query()->create([
                'variant_id'=>$variant->getKey(),'type'=>$type,'quantity'=>$delta,
                'reference_type'=>$referenceType,'reference_id'=>$referenceId === null ? null : (string) $referenceId,
                'note'=>$note,'created_at'=>now(),
            ]);
            return $inventory->refresh();
        });
    }
    public function reserve(ProductVariant $variant, int $quantity): Inventory
    {
        if ($quantity <= 0) throw new InvalidArgumentException('Reservation quantity must be positive.');
        return DB::transaction(function () use ($variant, $quantity) {
            $inventory = Inventory::query()->where('variant_id', $variant->getKey())->lockForUpdate()->firstOrFail();
            if ($inventory->availableQuantity() < $quantity) throw new RuntimeException('Insufficient available stock.');
            $inventory->quantity_reserved += $quantity;
            $inventory->assertConsistent();
            $inventory->save();
            return $inventory->refresh();
        });
    }
    public function release(ProductVariant $variant, int $quantity): Inventory
    {
        if ($quantity <= 0) throw new InvalidArgumentException('Release quantity must be positive.');
        return DB::transaction(function () use ($variant, $quantity) {
            $inventory = Inventory::query()->where('variant_id', $variant->getKey())->lockForUpdate()->firstOrFail();
            if ($inventory->quantity_reserved < $quantity) throw new RuntimeException('Cannot release more stock than is reserved.');
            $inventory->quantity_reserved -= $quantity;
            $inventory->assertConsistent();
            $inventory->save();
            return $inventory->refresh();
        });
    }
}
