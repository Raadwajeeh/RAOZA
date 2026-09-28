<?php

namespace Tests\Support;

use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\ProviderPayment;

final class FakePaymentProvider implements PaymentProvider
{
    public string $status = 'open';
    public string $id = 'tr_test_payment';
    public ?int $amount = null;
    public ?string $currency = null;
    public ?string $orderNumber = null;
    public int $createCalls = 0;
    public int $fetchCalls = 0;

    public function name(): string
    {
        return 'mollie';
    }

    public function create(Order $order, string $redirectUrl, string $webhookUrl): ProviderPayment
    {
        $this->createCalls++;

        return $this->payment($order);
    }

    public function fetch(string $providerPaymentId): ProviderPayment
    {
        $this->fetchCalls++;
        $order = Order::query()->whereHas('payments', fn ($query) => $query->where('provider_payment_id', $providerPaymentId))->firstOrFail();

        return $this->payment($order);
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
