<?php

namespace Tests\Support;

use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\ProviderPayment;
use App\Domain\Payments\Data\ProviderRefund;
use App\Domain\Payments\Data\ProviderRefundRequest;
use App\Domain\Payments\Exceptions\ProviderOperationException;
use App\Domain\Returns\Models\Refund;

final class FakePaymentProvider implements PaymentProvider
{
    public string $status = 'open';
    public string $id = 'tr_test_payment';
    public ?int $amount = null;
    public ?string $currency = null;
    public ?string $orderNumber = null;
    public int $createCalls = 0;
    public array $creationKeys = [];
    public ?ProviderOperationException $paymentCreateException = null;
    public int $fetchCalls = 0;
    public string $refundStatus = 'pending';
    public string $refundId = 're_test_refund';
    public ?int $refundAmount = null;
    public ?string $refundCurrency = null;
    public ?string $refundPaymentId = null;
    public ?string $refundReference = null;
    public int $createRefundCalls = 0;
    public int $fetchRefundCalls = 0;
    public int $findRefundCalls = 0;
    public ?ProviderOperationException $refundCreateException = null;
    public ?ProviderOperationException $refundFetchException = null;
    /** @var array<string, ProviderRefund> */
    public array $remoteRefunds = [];

    public function name(): string
    {
        return 'mollie';
    }

    public function create(Order $order, string $redirectUrl, string $webhookUrl, string $idempotencyKey): ProviderPayment
    {
        $this->createCalls++;
        $this->creationKeys[] = $idempotencyKey;
        if ($this->paymentCreateException) throw $this->paymentCreateException;

        return $this->payment($order);
    }

    public function fetch(string $providerPaymentId): ProviderPayment
    {
        $this->fetchCalls++;
        $order = Order::query()->whereHas('payments', fn ($query) => $query->where('provider_payment_id', $providerPaymentId))->firstOrFail();

        return $this->payment($order);
    }

    public function createRefund(ProviderRefundRequest $request): ProviderRefund
    {
        $this->createRefundCalls++;
        if ($this->refundCreateException) {
            throw $this->refundCreateException;
        }
        $remote = new ProviderRefund(
            $this->refundId,
            $this->refundPaymentId ?? $request->paymentId,
            $this->refundStatus,
            $this->refundAmount ?? $request->amount,
            $this->refundCurrency ?? $request->currency,
            now()->toIso8601String(),
            $this->refundReference ?? $request->idempotencyKey,
        );
        $this->remoteRefunds[$request->idempotencyKey] = $remote;

        return $remote;
    }

    public function fetchRefund(string $providerPaymentId, string $providerRefundId): ProviderRefund
    {
        $this->fetchRefundCalls++;
        if ($this->refundFetchException) {
            throw $this->refundFetchException;
        }
        $refund = Refund::query()->where('provider_refund_id', $providerRefundId)->firstOrFail();
        $reference = $refund->metadata['provider_reference'] ?? null;

        return new ProviderRefund(
            $providerRefundId,
            $this->refundPaymentId ?? $providerPaymentId,
            $this->refundStatus,
            $this->refundAmount ?? $refund->amount,
            $this->refundCurrency ?? $refund->currency,
            now()->toIso8601String(),
            $this->refundReference ?? $reference,
        );
    }

    public function findRefund(string $providerPaymentId, string $reference): ?ProviderRefund
    {
        $this->findRefundCalls++;

        return $this->remoteRefunds[$reference] ?? null;
    }

    private function payment(Order $order): ProviderPayment
    {
        return new ProviderPayment(
            $this->id,
            $this->status,
            $this->amount ?? $order->total_amount,
            $this->currency ?? $order->currency,
            'https://payments.example.test/'.$this->id,
            'ideal',
            $this->orderNumber ?? $order->order_number,
        );
    }
}
