<?php

namespace Tests\Feature;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;

class AdminOrderCancellationTest extends TestCase
{
    use BuildsCommerceFixtures, RefreshDatabase;

    public function test_order_manager_can_cancel_unpaid_order_with_reason_and_audit(): void
    {
        $fixture = $this->checkoutOrder();
        $actor = User::factory()->create(['role'=>AdminRole::OrderManager]);
        $this->actingAs($actor)->post("/admin/orders/{$fixture['order']->order_number}/cancel", ['reason'=>'Customer requested cancellation'])
            ->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Cancelled, $fixture['order']->fresh()->order_status);
        $this->assertSame(0, $fixture['inventories'][0]->fresh()->quantity_reserved);
        $this->assertDatabaseHas('audit_logs', ['actor_id'=>$actor->id,'action'=>'order.unpaid_cancelled','subject_id'=>(string)$fixture['order']->id]);
    }

    public function test_support_is_forbidden_and_paid_order_is_rejected(): void
    {
        $unpaid = $this->checkoutOrder();
        $support = User::factory()->create(['role'=>AdminRole::Support]);
        $this->actingAs($support)->post("/admin/orders/{$unpaid['order']->order_number}/cancel", ['reason'=>'Not authorized'])->assertForbidden();

        $paid = $this->checkoutOrder();
        $paid['order']->update(['order_status'=>OrderStatus::Confirmed,'payment_status'=>PaymentStatus::Paid]);
        $manager = User::factory()->create(['role'=>AdminRole::OrderManager]);
        $this->actingAs($manager)->post("/admin/orders/{$paid['order']->order_number}/cancel", ['reason'=>'Must not cancel paid order'])
            ->assertSessionHasErrors('cancellation');
        $this->assertSame(OrderStatus::Confirmed, $paid['order']->fresh()->order_status);
    }
}
