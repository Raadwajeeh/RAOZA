<?php
namespace App\Domain\Fulfillment\Models;
use App\Domain\Commerce\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ShipmentItem extends Model {protected $fillable=['shipment_id','order_item_id','quantity'];protected function casts():array{return ['quantity'=>'integer'];}public function shipment():BelongsTo{return $this->belongsTo(Shipment::class);}public function orderItem():BelongsTo{return $this->belongsTo(OrderItem::class);}}
