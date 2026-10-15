<?php
namespace App\Domain\Fulfillment\Models;
use App\Domain\Commerce\Models\Order;
use App\Domain\Fulfillment\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Shipment extends Model
{
    protected $fillable=['order_id','shipping_method_id','shipping_carrier_id','provider','provider_shipment_id','carrier_name','carrier_code','idempotency_key','request_fingerprint','created_by','service_code','status','tracking_number','tracking_url','shipping_cost','currency','label_path','shipped_at','handed_over_by','delivered_at','delivered_by','cancelled_at','cancelled_by','cancellation_reason'];
    protected function casts():array{return ['status'=>ShipmentStatus::class,'shipping_cost'=>'integer','shipped_at'=>'datetime','delivered_at'=>'datetime','cancelled_at'=>'datetime'];}
    public function order():BelongsTo{return $this->belongsTo(Order::class);}
    public function shippingMethod():BelongsTo{return $this->belongsTo(ShippingMethod::class);}
    public function carrier():BelongsTo{return $this->belongsTo(ShippingCarrier::class,'shipping_carrier_id');}
    public function items():HasMany{return $this->hasMany(ShipmentItem::class);}
    public function events():HasMany{return $this->hasMany(ShipmentEvent::class);}
    public function operations():HasMany{return $this->hasMany(ShipmentOperation::class);}
}
