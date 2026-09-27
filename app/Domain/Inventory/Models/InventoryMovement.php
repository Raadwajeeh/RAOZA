<?php
namespace App\Domain\Inventory\Models;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class InventoryMovement extends Model
{
    public $timestamps = false;
    protected $fillable = ['variant_id','type','quantity','reference_type','reference_id','note','created_at'];
    protected function casts(): array { return ['type'=>InventoryMovementType::class,'quantity'=>'integer','created_at'=>'immutable_datetime']; }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
}
