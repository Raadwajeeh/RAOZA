<?php
namespace App\Domain\Commerce\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class OrderStatusHistory extends Model
{
    public $timestamps=false;
    protected $table='order_status_history';
    protected $fillable=['order_id','domain','from_status','to_status','reason','actor_user_id','created_at'];
    protected function casts(): array { return ['created_at'=>'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class,'actor_user_id'); }
}
