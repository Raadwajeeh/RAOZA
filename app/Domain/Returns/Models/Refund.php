<?php
namespace App\Domain\Returns\Models;
use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Returns\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Refund extends Model {
 protected $fillable=['payment_id','order_id','return_id','provider_refund_id','amount','currency','reason','status','idempotency_key','requested_at','processed_at','metadata'];
 protected function casts():array{return ['amount'=>'integer','status'=>RefundStatus::class,'requested_at'=>'datetime','processed_at'=>'datetime','metadata'=>'array'];}
 public function payment():BelongsTo{return $this->belongsTo(Payment::class);} public function order():BelongsTo{return $this->belongsTo(Order::class);} public function returnRequest():BelongsTo{return $this->belongsTo(ReturnRequest::class,'return_id');}
}
