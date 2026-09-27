<?php
namespace App\Domain\Catalog\Models;
use App\Domain\Catalog\Enums\VariantStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Domain\Inventory\Models\Inventory;
class ProductVariant extends Model
{
    protected $fillable = ['product_id','sku','price_override','status','option_signature'];
    protected function casts(): array { return ['price_override'=>'integer','status'=>VariantStatus::class]; }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function images(): HasMany { return $this->hasMany(ProductImage::class, 'variant_id')->orderBy('position'); }
    public function inventory(): HasOne { return $this->hasOne(Inventory::class, 'variant_id'); }
    public function optionValues(): BelongsToMany { return $this->belongsToMany(ProductOptionValue::class, 'variant_option_values', 'variant_id', 'option_value_id'); }
    public function effectivePrice(): int { return $this->price_override ?? $this->product->base_price; }
}
