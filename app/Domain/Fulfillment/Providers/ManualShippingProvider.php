<?php
namespace App\Domain\Fulfillment\Providers;
use App\Domain\Fulfillment\Contracts\ShippingProvider;
use App\Domain\Fulfillment\Data\ProviderShipment;
use App\Domain\Fulfillment\Models\Shipment;
use RuntimeException;
class ManualShippingProvider implements ShippingProvider
{
    public function name():string{return 'manual';}
    public function createShipment(Shipment $shipment):ProviderShipment{return new ProviderShipment('manual-'.$shipment->getKey(),$shipment->status->value,$shipment->tracking_number,$shipment->tracking_url);}
    public function fetch(string $providerShipmentId):ProviderShipment
    {
        $shipment=Shipment::query()->where('provider','manual')->where('provider_shipment_id',$providerShipmentId)->first();
        if(!$shipment)throw new RuntimeException('Manual shipment was not found locally.');
        return new ProviderShipment($providerShipmentId,$shipment->status->value,$shipment->tracking_number,$shipment->tracking_url);
    }
    public function cancel(Shipment $shipment):void
    {
        if($shipment->provider!=='manual')throw new RuntimeException('The manual provider cannot cancel a remote shipment.');
    }
}
