<?php
namespace App\Domain\Fulfillment\Services;
use App\Domain\Fulfillment\Contracts\ShippingProvider;
use App\Domain\Fulfillment\Providers\DhlShippingProvider;
use App\Domain\Fulfillment\Providers\ManualShippingProvider;
use App\Domain\Fulfillment\Providers\PostNlShippingProvider;
use RuntimeException;
final class ShippingProviderRegistry
{
    public function __construct(private ManualShippingProvider $manual,private PostNlShippingProvider $postnl,private DhlShippingProvider $dhl){}
    public function driver(string $name):ShippingProvider{return match($name){'manual'=>$this->manual,'postnl'=>$this->postnl,'dhl'=>$this->dhl,default=>throw new RuntimeException("Unknown shipping integration: {$name}.")};}
}
