<?php

namespace Tests\Feature;

use App\Domain\Commerce\Enums\FulfillmentStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Services\ReturnService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;

class CustomerReturnsTest extends TestCase
{
    use BuildsCommerceFixtures, RefreshDatabase;

    public function test_customer_can_submit_specific_quantities_without_automatic_refund(): void
    {
        [$user, $fixture] = $this->returnableOrder(quantity: 2);
        $item = $fixture['order']->items()->firstOrFail();

        $this->actingAs($user)->post(route('account.returns.store', $fixture['order']->order_number), [
            'items' => [['order_item_id' => $item->id, 'quantity' => 1, 'reason_code' => 'wrong_size']],
            'customer_note' => 'Please review the fit.',
        ])->assertRedirect(route('account.returns.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('returns', ['order_id' => $fixture['order']->id, 'status' => 'requested']);
        $this->assertDatabaseHas('return_items', ['order_item_id' => $item->id, 'quantity' => 1, 'reason_code' => 'wrong_size']);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_customer_cannot_return_more_than_remaining_quantity_or_duplicate_it(): void
    {
        [$user, $fixture] = $this->returnableOrder(quantity: 2);
        $item = $fixture['order']->items()->firstOrFail();
        $payload = ['items' => [['order_item_id' => $item->id, 'quantity' => 2, 'reason_code' => 'other']]];

        $this->actingAs($user)->post(route('account.returns.store', $fixture['order']->order_number), $payload)
            ->assertRedirect(route('account.returns.index'));
        $this->post(route('account.returns.store', $fixture['order']->order_number), [
            'items' => [['order_item_id' => $item->id, 'quantity' => 1, 'reason_code' => 'other']],
        ])->assertSessionHasErrors('return');
        $this->assertDatabaseCount('returns', 1);
    }

    public function test_return_order_ownership_is_enforced_server_side(): void
    {
        [$owner, $fixture] = $this->returnableOrder();
        $attacker = User::factory()->create();
        $item = $fixture['order']->items()->firstOrFail();

        $this->actingAs($attacker)->post(route('account.returns.store', $fixture['order']->order_number), [
            'items' => [['order_item_id' => $item->id, 'quantity' => 1, 'reason_code' => 'other']],
        ])->assertNotFound();
        $this->assertDatabaseCount('returns', 0);
    }

    public function test_unfulfilled_order_displays_reason_and_cannot_enter_return_flow(): void
    {
        $user = User::factory()->create();
        $fixture = $this->checkoutOrder();
        $fixture['order']->update(['user_id' => $user->id, 'payment_status' => PaymentStatus::Paid, 'fulfillment_status' => FulfillmentStatus::Processing]);
        $item = $fixture['order']->items()->firstOrFail();

        $this->actingAs($user)->get(route('account.orders.show', $fixture['order']->order_number))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.returnEligibility.eligible', false)
                ->where('order.returnEligibility.windowDays', null));
        $this->post(route('account.returns.store', $fixture['order']->order_number), [
            'items' => [['order_item_id' => $item->id, 'quantity' => 1, 'reason_code' => 'other']],
        ])->assertSessionHasErrors('return');
    }

    public function test_no_return_window_is_assumed_when_not_configured(): void
    {
        config()->set('customer_portal.return_window_days', null);
        [$user, $fixture] = $this->returnableOrder();
        $fixture['order']->update(['placed_at' => now()->subYears(2)]);

        $this->actingAs($user)->get(route('account.orders.show', $fixture['order']->order_number))
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.returnEligibility.eligible', true)
                ->where('order.returnEligibility.deadline', null));
    }

    public function test_configured_return_window_is_enforced_from_fulfillment_history(): void
    {
        config()->set('customer_portal.return_window_days', 14);
        [$user, $fixture] = $this->returnableOrder();
        $fixture['order']->statusHistory()->create([
            'domain' => 'fulfillment',
            'from_status' => 'ready_to_ship',
            'to_status' => 'fulfilled',
            'reason' => 'test',
            'created_at' => now()->subDays(15),
        ]);

        $this->actingAs($user)->get(route('account.orders.show', $fixture['order']->order_number))
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.returnEligibility.eligible', false)
                ->where('order.returnEligibility.windowDays', 14));
    }

    public function test_customer_sees_status_updates_only_for_owned_returns(): void
    {
        [$user, $fixture] = $this->returnableOrder();
        $item = $fixture['order']->items()->firstOrFail();
        $return = app(ReturnService::class)->create($fixture['order'], [
            ['order_item_id' => $item->id, 'quantity' => 1, 'reason_code' => 'other'],
        ]);
        app(ReturnService::class)->transition($return, ReturnStatus::UnderReview);

        $this->actingAs($user)->get(route('account.returns.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('returns', 1)
                ->where('returns.0.number', $return->return_number)
                ->where('returns.0.status', 'under_review'));

        $this->actingAs(User::factory()->create())->get(route('account.returns.index'))
            ->assertInertia(fn (Assert $page) => $page->has('returns', 0));
    }

    private function returnableOrder(int $quantity = 1): array
    {
        $user = User::factory()->create();
        $fixture = $this->checkoutOrder([['price' => 1000, 'quantity' => $quantity, 'stock' => $quantity + 2]]);
        $fixture['order']->update([
            'user_id' => $user->id,
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Fulfilled,
        ]);

        return [$user, $fixture];
    }
}
