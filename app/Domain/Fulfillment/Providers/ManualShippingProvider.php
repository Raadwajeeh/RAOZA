<?php
namespace App\Domain\Fulfillment\Providers;
use App\Domain\Fulfillment\Contracts\ShippingProvider;
use App\Domain\Fulfillment\Data\ProviderShipment;
use App\Domain\Fulfillment\Models\Shipment;
use RuntimeException;
class ManualShippingProvider implements ShippingProvider {public function name():string{return 'manual';}public function createShipment(Shipment $shipment):ProviderShipment{throw new RuntimeException('No external shipping provider is configured. Use manual shipment details or configure an adapter.');}public function fetch(string $providerShipmentId):ProviderShipment{throw new RuntimeException('Manual shipping has no remote shipment.');}public function cancel(Shipment $shipment):void{}}
