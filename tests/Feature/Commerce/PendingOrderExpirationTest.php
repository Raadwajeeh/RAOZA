<?php

namespace Tests\Feature\Commerce;

use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Services\PendingOrderExpirationService;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;

class PendingOrderExpirationTest extends TestCase
{
    use BuildsCommerceFixtures, RefreshDatabase;

    public function test_old_unpaid_order_releases_each_reservation_once(): void
    {
        $fixture = $this->checkoutOrder([['price'=>1000,'quantity'=>2],['price'=>1500,'quantity'=>3]]);
        $fixture['order']->update(['placed_at'=>now()->subHour()]);
        $service = app(PendingOrderExpirationService::class);

        $this->assertSame(1, $service->expire()['expired']);
        $this->assertSame(0, $service->expire()['expired']);
        $this->assertSame(OrderStatus::Cancelled, $fixture['order']->fresh()->order_status);
        $this->assertSame(PaymentStatus::Failed, $fixture['order']->fresh()->payment_status);
        $this->assertSame([0,0], collect($fixture['inventories'])->map(fn ($inventory) => $inventory->fresh()->quantity_reserved)->all());
        $this->assertDatabaseHas('order_status_history', ['order_id'=>$fixture['order']->id,'reason'=>'reservation_window_expired']);
    }

    public function test_young_paid_confirmed_and_already_cancelled_orders_are_not_expired(): void
    {
        $young = $this->checkoutOrder();
        $paid = $this->checkoutOrder();
        $paid['order']->update(['placed_at'=>now()->subHour(),'order_status'=>OrderStatus::Confirmed,'payment_status'=>PaymentStatus::Paid]);
        $cancelled = $this->checkoutOrder();
        $cancelled['order']->update(['placed_at'=>now()->subHour(),'order_status'=>OrderStatus::Cancelled,'payment_status'=>PaymentStatus::Failed]);

        $this->assertSame(0, app(PendingOrderExpirationService::class)->expire()['expired']);
        $this->assertSame(1, $young['inventories'][0]->fresh()->quantity_reserved);
        $this->assertSame(1, $paid['inventories'][0]->fresh()->quantity_reserved);
    }

    public function test_active_provider_attempt_requires_reconciliation_and_is_not_expired(): void
    {
        $fixture = $this->checkoutOrder();
        $fixture['order']->update(['placed_at'=>now()->subHour(),'payment_status'=>PaymentStatus::Pending]);
        Payment::query()->create(['order_id'=>$fixture['order']->id,'provider'=>'mollie','provider_payment_id'=>'tr_live_pending','currency'=>'EUR','amount'=>$fixture['order']->total_amount,'status'=>PaymentAttemptStatus::Open]);

        $this->assertSame(0, app(PendingOrderExpirationService::class)->expire()['expired']);
        $this->assertSame(1, $fixture['inventories'][0]->fresh()->quantity_reserved);
    }

    public function test_stale_unsubmitted_or_terminal_attempt_can_expire(): void
    {
        foreach ([PaymentAttemptStatus::Created, PaymentAttemptStatus::Failed, PaymentAttemptStatus::Cancelled, PaymentAttemptStatus::Expired] as $status) {
            $fixture = $this->checkoutOrder();
            $fixture['order']->update(['placed_at'=>now()->subHour()]);
            Payment::query()->create(['order_id'=>$fixture['order']->id,'provider'=>'mollie','provider_payment_id'=>null,'currency'=>'EUR','amount'=>$fixture['order']->total_amount,'status'=>$status]);
        }

        $this->assertSame(4, app(PendingOrderExpirationService::class)->expire()['expired']);
    }
}
