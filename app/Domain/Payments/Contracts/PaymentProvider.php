<?php
namespace App\Domain\Payments\Contracts;
use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Data\ProviderPayment;
use App\Domain\Payments\Data\ProviderRefund;
use App\Domain\Payments\Data\ProviderRefundRequest;
interface PaymentProvider {
 public function name():string;
 public function create(Order $order, string $redirectUrl, string $webhookUrl, string $idempotencyKey):ProviderPayment;
 public function fetch(string $providerPaymentId):ProviderPayment;
 public function createRefund(ProviderRefundRequest $request):ProviderRefund;
 public function fetchRefund(string $providerPaymentId,string $providerRefundId):ProviderRefund;
 public function findRefund(string $providerPaymentId,string $reference):?ProviderRefund;
}
