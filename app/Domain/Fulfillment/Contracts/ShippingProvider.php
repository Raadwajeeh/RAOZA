<?php
namespace App\Domain\Fulfillment\Contracts;
use App\Domain\Fulfillment\Data\ProviderShipment;
use App\Domain\Fulfillment\Models\Shipment;
interface ShippingProvider {public function name():string;public function createShipment(Shipment $shipment):ProviderShipment;public function fetch(string $providerShipmentId):ProviderShipment;public function cancel(Shipment $shipment):void;}
