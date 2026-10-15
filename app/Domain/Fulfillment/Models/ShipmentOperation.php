<?php

namespace App\Domain\Fulfillment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentOperation extends Model
{
    public $timestamps = false;
    protected $fillable = ['shipment_id', 'operation_key', 'action', 'payload_hash', 'actor_user_id', 'created_at'];
    protected function casts(): array { return ['created_at' => 'datetime']; }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
}
