<?php
namespace App\Domain\Payments\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PaymentEvent extends Model { public $timestamps=false; protected $fillable=['payment_id','event_key','event_type','provider_status','payload','processed_at','created_at']; protected function casts():array{return ['payload'=>'array','processed_at'=>'datetime','created_at'=>'datetime'];} public function payment():BelongsTo{return $this->belongsTo(Payment::class);} }
