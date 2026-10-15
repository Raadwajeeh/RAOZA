<?php

namespace Tests\Feature;

use App\Domain\Commerce\Models\OrderAddress;
use App\Domain\Customer\Models\CustomerAddress;
use App\Http\Middleware\ResolveCart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;

class CustomerAddressBookTest extends TestCase
{
    use BuildsCommerceFixtures, RefreshDatabase;

    public function test_customer_can_create_update_and_delete_owned_addresses(): void
    {
        $user = User::factory()->create();
        $payload = $this->addressPayload();

        $this->actingAs($user)->post(route('account.addresses.store'), $payload)->assertSessionHas('success');
        $address = $user->addresses()->sole();
        $this->assertTrue($address->is_default_shipping);
        $this->assertTrue($address->is_default_billing);

        $this->patch(route('account.addresses.update', $address->id), [...$payload, 'label' => 'Studio', 'city' => 'Utrecht'])
            ->assertSessionHas('success');
        $this->assertSame('Utrecht', $address->fresh()->city);

        $this->delete(route('account.addresses.destroy', $address->id))->assertSessionHas('success');
        $this->assertDatabaseMissing('customer_addresses', ['id' => $address->id]);
    }

    public function test_defaults_are_unique_per_customer_and_reassigned_after_delete(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.addresses.store'), $this->addressPayload(['label' => 'First']));
        $this->post(route('account.addresses.store'), $this->addressPayload([
            'label' => 'Second',
            'street' => 'Tweede straat',
            'is_default_shipping' => true,
            'is_default_billing' => false,
        ]));
        $first = $user->addresses()->where('label', 'First')->firstOrFail();
        $second = $user->addresses()->where('label', 'Second')->firstOrFail();

        $this->assertFalse($first->fresh()->is_default_shipping);
        $this->assertTrue($second->fresh()->is_default_shipping);
        $this->assertTrue($first->fresh()->is_default_billing);

        $this->patch(route('account.addresses.default', $second->id), ['type' => 'billing'])->assertSessionHas('success');
        $this->assertFalse($first->fresh()->is_default_billing);
        $this->assertTrue($second->fresh()->is_default_billing);

        $this->delete(route('account.addresses.destroy', $first->id));
        $this->assertTrue($second->fresh()->is_default_billing);
    }

    public function test_address_routes_are_scoped_to_authenticated_customer(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $address = CustomerAddress::query()->create([
            'user_id' => $owner->id,
            ...$this->addressPayload(),
        ]);

        $this->actingAs($attacker)->patch(route('account.addresses.update', $address->id), $this->addressPayload(['city' => 'Leiden']))
            ->assertNotFound();
        $this->delete(route('account.addresses.destroy', $address->id))->assertNotFound();
        $this->patch(route('account.addresses.default', $address->id), ['type' => 'shipping'])->assertNotFound();
        $this->assertSame('Amsterdam', $address->fresh()->city);
    }

    public function test_checkout_can_use_owned_saved_address_and_order_snapshot_does_not_change(): void
    {
        $user = User::factory()->create(['email' => 'saved@example.com']);
        $address = CustomerAddress::query()->create([
            'user_id' => $user->id,
            ...$this->addressPayload(['street' => 'Saved street', 'house_number' => '44']),
        ]);
        $fixture = $this->cartFixture();
        $fixture['cart']->update(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->withCookie(ResolveCart::COOKIE, $fixture['cart']->token)
            ->post(route('checkout.store'), [
                'email' => $user->email,
                'phone' => null,
                'shipping_address_id' => $address->id,
                'shipping_method_id' => $fixture['shipping']->id,
                'terms_accepted' => true,
                'billing_same_as_shipping' => true,
            ]);
        $response->assertRedirect()->assertSessionHasNoErrors();
        $orderAddress = OrderAddress::query()->where('type', 'shipping')->sole();
        $this->assertSame('Saved street', $orderAddress->street);
        $this->assertSame('44', $orderAddress->house_number);

        $address->update(['street' => 'Changed later']);
        $this->assertSame('Saved street', $orderAddress->fresh()->street);
    }

    public function test_checkout_rejects_saved_address_owned_by_another_customer(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $address = CustomerAddress::query()->create(['user_id' => $owner->id, ...$this->addressPayload()]);
        $fixture = $this->cartFixture();
        $fixture['cart']->update(['user_id' => $attacker->id]);

        $this->actingAs($attacker)
            ->withCookie(ResolveCart::COOKIE, $fixture['cart']->token)
            ->post(route('checkout.store'), [
                'email' => $attacker->email,
                'shipping_address_id' => $address->id,
                'shipping_method_id' => $fixture['shipping']->id,
                'terms_accepted' => true,
                'billing_same_as_shipping' => true,
            ])->assertNotFound();
        $this->assertDatabaseCount('orders', 0);
    }

    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home',
            'first_name' => 'Rae',
            'last_name' => 'Customer',
            'company' => null,
            'phone' => null,
            'street' => 'Example street',
            'house_number' => '10',
            'addition' => null,
            'postal_code' => '1011 AA',
            'city' => 'Amsterdam',
            'country_code' => 'NL',
            'is_shipping' => true,
            'is_billing' => true,
            'is_default_shipping' => false,
            'is_default_billing' => false,
        ], $overrides);
    }
}
