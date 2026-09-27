<?php
namespace App\Domain\Catalog\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class ProductOptionValue extends Model
{
    protected $fillable = ['product_option_id','value','metadata','position'];
    protected function casts(): array { return ['metadata'=>'array','position'=>'integer']; }
    public function option(): BelongsTo { return $this->belongsTo(ProductOption::class, 'product_option_id'); }
    public function variants(): BelongsToMany { return $this->belongsToMany(ProductVariant::class, 'variant_option_values', 'option_value_id', 'variant_id'); }
}
