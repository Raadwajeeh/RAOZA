<?php
namespace App\Domain\Commerce\Models;
use App\Domain\Commerce\Enums\CartStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Cart extends Model
{
    protected $fillable = ['token','user_id','status','currency','expires_at'];
    protected function casts(): array { return ['status'=>CartStatus::class,'expires_at'=>'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(CartItem::class); }
}
