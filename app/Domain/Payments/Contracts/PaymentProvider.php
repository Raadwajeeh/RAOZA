<?php
namespace App\Domain\Payments\Contracts;
use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Data\ProviderPayment;
interface PaymentProvider { public function name():string; public function create(Order $order, string $redirectUrl, string $webhookUrl):ProviderPayment; public function fetch(string $providerPaymentId):ProviderPayment; }
