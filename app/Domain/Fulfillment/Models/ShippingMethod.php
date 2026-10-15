<?php
namespace App\Domain\Fulfillment\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ShippingMethod extends Model {protected $fillable=['name','code','provider','price','currency','active','position','configuration','pending_configuration','pending_hash'];protected function casts():array{return ['price'=>'integer','active'=>'boolean','position'=>'integer','configuration'=>'array','pending_configuration'=>'array'];}public function shipments():HasMany{return $this->hasMany(Shipment::class);}}
