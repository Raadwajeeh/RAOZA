<?php

namespace Tests\Feature\Commerce;

use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Services\OrderCancellationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;

class OrderCancellationIntegrityTest extends TestCase
{
    use BuildsCommerceFixtures;
    use RefreshDatabase;

    public function test_cancellation_releases_once_and_records_the_actor(): void
    {
        $fixture = $this->checkoutOrder([['price' => 1000, 'quantity' => 2, 'stock' => 3]]);
        $actor = User::factory()->create();
        $service = app(OrderCancellationService::class);

        $cancelled = $service->cancel($fixture['order'], $actor->id);
        $again = $service->cancel($cancelled, $actor->id);

        $this->assertSame(OrderStatus::Cancelled, $again->order_status);
        $this->assertSame(0, $fixture['inventories'][0]->fresh()->quantity_reserved);
        $this->assertSame(3, $fixture['inventories'][0]->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('order_status_history', 2);
        $this->assertDatabaseHas('order_status_history', ['order_id' => $fixture['order']->id, 'reason' => 'admin_cancelled', 'actor_user_id' => $actor->id]);
    }
}
