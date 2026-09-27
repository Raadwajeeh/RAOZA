<?php
namespace App\Domain\Commerce\Models;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CartItem extends Model
{
    protected $fillable = ['cart_id','variant_id','quantity'];
    protected function casts(): array { return ['quantity'=>'integer']; }
    public function cart(): BelongsTo { return $this->belongsTo(Cart::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
}
