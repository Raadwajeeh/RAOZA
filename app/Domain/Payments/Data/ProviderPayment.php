<?php
namespace App\Domain\Payments\Data;
final readonly class ProviderPayment {
 public function __construct(public string $id, public string $status, public int $amount, public string $currency, public ?string $checkoutUrl=null, public ?string $method=null, public ?string $orderNumber=null, public array $raw=[]) {}
}
