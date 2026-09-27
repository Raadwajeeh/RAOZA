<?php
namespace App\Domain\Catalog\Models;
use App\Domain\Catalog\Enums\CollectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Collection extends Model
{
    protected $fillable = ['name','slug','description','seo_title','seo_description','canonical_url','og_image','indexable','status','published_at'];
    protected function casts(): array { return ['status'=>CollectionStatus::class,'indexable'=>'boolean','published_at'=>'immutable_datetime']; }
    public function products(): BelongsToMany { return $this->belongsToMany(Product::class)->withPivot('position'); }
}
