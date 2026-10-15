<?php
namespace App\Domain\Fulfillment\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ShipmentEvent extends Model {public $timestamps=false;protected $fillable=['shipment_id','external_event_id','status','description','actor_user_id','metadata','occurred_at','created_at'];protected function casts():array{return ['metadata'=>'array','occurred_at'=>'datetime','created_at'=>'datetime'];}public function shipment():BelongsTo{return $this->belongsTo(Shipment::class);}}
