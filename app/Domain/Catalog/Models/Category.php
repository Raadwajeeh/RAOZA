<?php
namespace App\Domain\Catalog\Models;
use App\Domain\Catalog\Enums\CategoryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Category extends Model
{
    protected $fillable = ['parent_id','name','slug','description','seo_title','seo_description','canonical_url','indexable','status','position'];
    protected function casts(): array { return ['status'=>CategoryStatus::class,'position'=>'integer','indexable'=>'boolean']; }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('position'); }
    public function products(): BelongsToMany { return $this->belongsToMany(Product::class)->withPivot('position'); }
}
