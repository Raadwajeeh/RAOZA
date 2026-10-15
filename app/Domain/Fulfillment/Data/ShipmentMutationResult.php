<?php

namespace App\Domain\Fulfillment\Data;

use App\Domain\Fulfillment\Models\Shipment;

final readonly class ShipmentMutationResult
{
    public function __construct(public Shipment $shipment, public bool $changed) {}
}
