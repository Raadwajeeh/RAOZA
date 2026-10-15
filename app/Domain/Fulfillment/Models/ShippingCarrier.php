<?php

namespace App\Domain\Fulfillment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingCarrier extends Model
{
    protected $fillable = ['name', 'code', 'integration_key', 'tracking_url_template', 'active', 'position', 'notes'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'position' => 'integer'];
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
