<?php
namespace App\Domain\Catalog\Models;
use App\Domain\Catalog\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Product extends Model
{
    protected $fillable = ['name','slug','description','status','base_price','seo_title','seo_description','canonical_url','og_image','indexable','published_at'];
    protected function casts(): array { return ['status'=>ProductStatus::class,'base_price'=>'integer','indexable'=>'boolean','published_at'=>'immutable_datetime']; }
    public function options(): HasMany { return $this->hasMany(ProductOption::class)->orderBy('position'); }
    public function variants(): HasMany { return $this->hasMany(ProductVariant::class); }
    public function images(): HasMany { return $this->hasMany(ProductImage::class)->orderBy('position'); }
    public function categories(): BelongsToMany { return $this->belongsToMany(Category::class)->withPivot('position'); }
    public function collections(): BelongsToMany { return $this->belongsToMany(Collection::class)->withPivot('position'); }
}
