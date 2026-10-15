<?php
namespace App\Domain\Fulfillment\Providers;
use App\Domain\Fulfillment\Contracts\ShippingProvider;
use App\Domain\Fulfillment\Data\ProviderShipment;
use App\Domain\Fulfillment\Models\Shipment;
use RuntimeException;
class DhlShippingProvider implements ShippingProvider
{
    public function name():string{return 'dhl';}
    public function createShipment(Shipment $shipment):ProviderShipment{throw new RuntimeException('DHL API integration is not configured. Use manual fulfillment.');}
    public function fetch(string $providerShipmentId):ProviderShipment{throw new RuntimeException('DHL API integration is not configured.');}
    public function cancel(Shipment $shipment):void{throw new RuntimeException('DHL API integration is not configured.');}
}
