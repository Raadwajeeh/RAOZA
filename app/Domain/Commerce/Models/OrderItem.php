<?php
namespace App\Domain\Commerce\Models;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Fulfillment\Models\ShipmentItem;
use App\Domain\Returns\Models\ReturnItem;
class OrderItem extends Model
{
    protected $fillable=['order_id','product_id','variant_id','product_name','variant_options','sku','unit_price','quantity','subtotal_amount','discount_amount','tax_amount','total_amount','product_snapshot'];
    protected function casts(): array { return ['variant_options'=>'array','product_snapshot'=>'array','unit_price'=>'integer','quantity'=>'integer','subtotal_amount'=>'integer','discount_amount'=>'integer','tax_amount'=>'integer','total_amount'=>'integer']; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class,'variant_id'); }
    public function shipmentItems(): HasMany { return $this->hasMany(ShipmentItem::class); }
    public function returnItems(): HasMany { return $this->hasMany(ReturnItem::class); }
}
