<?php
namespace App\Domain\Returns\Models;
use App\Domain\Commerce\Models\Order;
use App\Domain\Returns\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ReturnRequest extends Model {
 protected $table='returns';
 protected $fillable=['return_number','order_id','status','reason_summary','requested_at','approved_at','received_at','completed_at','cancelled_at','customer_note','admin_note'];
 protected function casts():array{return ['status'=>ReturnStatus::class,'requested_at'=>'datetime','approved_at'=>'datetime','received_at'=>'datetime','completed_at'=>'datetime','cancelled_at'=>'datetime'];}
 public function order():BelongsTo{return $this->belongsTo(Order::class);} public function items():HasMany{return $this->hasMany(ReturnItem::class,'return_id');} public function refunds():HasMany{return $this->hasMany(Refund::class,'return_id');}
}
