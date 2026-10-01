<?php

namespace Tests\Feature\Returns;

use App\Domain\Commerce\Enums\FulfillmentStatus;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Fulfillment\Services\FulfillmentService;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\Returns\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCommerceFixtures;
use Tests\Support\FakePaymentProvider;
use Tests\TestCase;

class RefundFulfillmentStateTest extends TestCase
{
    use BuildsCommerceFixtures, RefreshDatabase;
    private FakePaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new FakePaymentProvider;
        $this->app->instance(PaymentProvider::class,$this->provider);
    }

    public function test_partial_refund_before_or_during_fulfillment_remains_fulfillable(): void
    {
        foreach ([FulfillmentStatus::Processing,FulfillmentStatus::Printing] as $state) {
            $fixture = $this->paidFixture();
            $fixture['order']->update(['fulfillment_status'=>$state]);
            $this->completeRefund($fixture['order']->fresh(),400,'partial-'.$state->value);
            $order = $fixture['order']->fresh();
            $this->assertSame(PaymentStatus::PartiallyRefunded,$order->payment_status);
            $target = $state===FulfillmentStatus::Processing ? FulfillmentStatus::Printing : FulfillmentStatus::ReadyToShip;
            $this->assertSame($target,app(FulfillmentService::class)->transition($order,$target)->fulfillment_status);
        }
    }

    public function test_full_refund_before_fulfillment_cancels_operations_without_restocking(): void
    {
        $fixture = $this->paidFixture();
        $onHand = $fixture['inventories'][0]->fresh()->quantity_on_hand;
        $this->completeRefund($fixture['order']->fresh(),$fixture['order']->total_amount,'full-before');
        $order = $fixture['order']->fresh();
        $this->assertSame(PaymentStatus::Refunded,$order->payment_status);
        $this->assertSame(OrderStatus::Cancelled,$order->order_status);
        $this->assertSame(FulfillmentStatus::Cancelled,$order->fulfillment_status);
        $this->assertSame($onHand,$fixture['inventories'][0]->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('order_status_history',['order_id'=>$order->id,'reason'=>'full_refund_verified']);
    }

    public function test_refund_after_fulfillment_preserves_historical_fulfillment(): void
    {
        $fixture = $this->paidFixture();
        $fixture['order']->update(['fulfillment_status'=>FulfillmentStatus::Fulfilled]);
        $this->completeRefund($fixture['order']->fresh(),$fixture['order']->total_amount,'full-after');
        $order = $fixture['order']->fresh();
        $this->assertSame(PaymentStatus::Refunded,$order->payment_status);
        $this->assertSame(OrderStatus::Confirmed,$order->order_status);
        $this->assertSame(FulfillmentStatus::Fulfilled,$order->fulfillment_status);
    }

    private function paidFixture(): array
    {
        $fixture=$this->checkoutOrder([['price'=>1000,'stock'=>2]]);
        $this->provider->id='tr_'.strtolower(\Illuminate\Support\Str::random(12));
        $this->provider->status='open';
        $payment=app(PaymentService::class)->createAttempt($fixture['order'],'https://store.test/return','https://store.test/webhook');
        $this->provider->status='paid';
        app(PaymentService::class)->sync($payment,'test_paid');
        return $fixture;
    }

    private function completeRefund($order,int $amount,string $key): void
    {
        $this->provider->refundStatus='refunded';
        $this->provider->refundId='re_'.hash('sha256',$key);
        $refund=app(RefundService::class)->request($order,$amount,idempotencyKey:$key);
        app(RefundService::class)->submit($refund);
    }
}
