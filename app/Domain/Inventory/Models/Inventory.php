<?php
namespace App\Domain\Inventory\Models;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
class Inventory extends Model
{
    protected $table = 'inventories';
    protected $fillable = ['variant_id','quantity_on_hand','quantity_reserved'];
    protected function casts(): array { return ['quantity_on_hand'=>'integer','quantity_reserved'=>'integer']; }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
    public function movements(): HasMany { return $this->hasMany(InventoryMovement::class, 'variant_id', 'variant_id')->latest('id'); }
    public function availableQuantity(): int { return $this->quantity_on_hand - $this->quantity_reserved; }
    public function assertConsistent(): void
    {
        if ($this->quantity_on_hand < 0 || $this->quantity_reserved < 0 || $this->quantity_reserved > $this->quantity_on_hand) {
            throw new LogicException('Inventory quantities are inconsistent.');
        }
    }
}
