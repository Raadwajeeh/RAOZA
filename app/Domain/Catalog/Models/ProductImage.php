<?php
namespace App\Domain\Catalog\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductImage extends Model
{
    protected $fillable = ['product_id','variant_id','path','alt_text','position','width','height'];
    protected function casts(): array { return ['position'=>'integer','width'=>'integer','height'=>'integer']; }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
}
