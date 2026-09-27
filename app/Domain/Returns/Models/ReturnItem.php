<?php
namespace App\Domain\Returns\Models;
use App\Domain\Commerce\Models\OrderItem;
use App\Domain\Returns\Enums\ReturnCondition;
use App\Domain\Returns\Enums\ReturnResolution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ReturnItem extends Model {
 protected $fillable=['return_id','order_item_id','quantity','reason_code','reason_text','condition','resolution','restocked_at'];
 protected function casts():array{return ['quantity'=>'integer','condition'=>ReturnCondition::class,'resolution'=>ReturnResolution::class,'restocked_at'=>'datetime'];}
 public function returnRequest():BelongsTo{return $this->belongsTo(ReturnRequest::class,'return_id');} public function orderItem():BelongsTo{return $this->belongsTo(OrderItem::class);}
}
