<?php
namespace App\Domain\Fulfillment\Data;
final readonly class ProviderShipment {public function __construct(public string $id,public string $status,public ?string $trackingNumber=null,public ?string $trackingUrl=null,public ?string $labelPath=null){}}
