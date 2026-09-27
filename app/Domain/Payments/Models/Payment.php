<?php
namespace App\Domain\Payments\Models;
use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Returns\Models\Refund;
class Payment extends Model {
 protected $fillable=['order_id','provider','provider_payment_id','method','currency','amount','status','provider_created_at','paid_at','failed_at','cancelled_at','expired_at','metadata'];
 protected function casts():array{return ['amount'=>'integer','status'=>PaymentAttemptStatus::class,'provider_created_at'=>'datetime','paid_at'=>'datetime','failed_at'=>'datetime','cancelled_at'=>'datetime','expired_at'=>'datetime','metadata'=>'array'];}
 public function order():BelongsTo{return $this->belongsTo(Order::class);} public function events():HasMany{return $this->hasMany(PaymentEvent::class);} public function refunds():HasMany{return $this->hasMany(Refund::class);}
}
