<?php
namespace App\Domain\Fulfillment\Models;
use App\Domain\Commerce\Models\Order;
use App\Domain\Fulfillment\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Shipment extends Model {protected $fillable=['order_id','shipping_method_id','provider','provider_shipment_id','service_code','status','tracking_number','tracking_url','shipping_cost','currency','label_path','shipped_at','delivered_at'];protected function casts():array{return ['status'=>ShipmentStatus::class,'shipping_cost'=>'integer','shipped_at'=>'datetime','delivered_at'=>'datetime'];}public function order():BelongsTo{return $this->belongsTo(Order::class);}public function shippingMethod():BelongsTo{return $this->belongsTo(ShippingMethod::class);}public function items():HasMany{return $this->hasMany(ShipmentItem::class);}public function events():HasMany{return $this->hasMany(ShipmentEvent::class);}}
