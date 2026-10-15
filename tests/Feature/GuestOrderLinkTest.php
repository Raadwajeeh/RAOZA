<?php

namespace Tests\Feature;

use App\Domain\Commerce\Models\GuestOrderAccessToken;
use App\Mail\GuestOrderAccessMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;

class GuestOrderLinkTest extends TestCase
{
    use BuildsCommerceFixtures, RefreshDatabase;

    public function test_matching_guest_order_gets_hashed_expiring_access_link(): void
    {
        Mail::fake();
        $fixture = $this->checkoutOrder(overrides: ['email' => 'guest@example.com']);

        $this->post(route('guest-orders.send'), [
            'order_number' => $fixture['order']->order_number,
            'email' => 'GUEST@EXAMPLE.COM',
        ])->assertRedirect(route('guest-orders.request'))->assertSessionHas('success');

        $accessUrl = null;
        Mail::assertSent(GuestOrderAccessMail::class, function (GuestOrderAccessMail $mail) use (&$accessUrl): bool {
            $accessUrl = $mail->accessUrl;

            return true;
        });
        $plainToken = basename((string) parse_url($accessUrl, PHP_URL_PATH));
        $record = GuestOrderAccessToken::query()->sole();
        $this->assertSame(hash('sha256', $plainToken), $record->token_hash);
        $this->assertNotSame($plainToken, $record->token_hash);

        $this->get($accessUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Storefront/OrderConfirmation')
                ->where('order.number', $fixture['order']->order_number)
                ->where('guestAccess', true));
        $this->get(route('checkout.confirmation', $fixture['order']->order_number))->assertOk();

        $this->travel((int) config('customer_accounts.guest_order_link_minutes') + 1)->minutes();
        $this->get($accessUrl)->assertNotFound();
        $this->get(route('checkout.confirmation', $fixture['order']->order_number))->assertNotFound();
    }

    public function test_invalid_details_and_account_owned_orders_have_same_generic_response_without_mail(): void
    {
        Mail::fake();
        $fixture = $this->checkoutOrder(overrides: ['email' => 'guest@example.com']);

        $this->post(route('guest-orders.send'), [
            'order_number' => $fixture['order']->order_number,
            'email' => 'wrong@example.com',
        ])->assertRedirect(route('guest-orders.request'))->assertSessionHas('success');

        $fixture['order']->update(['user_id' => \App\Models\User::factory()->create()->id]);
        $this->post(route('guest-orders.send'), [
            'order_number' => $fixture['order']->order_number,
            'email' => 'guest@example.com',
        ])->assertRedirect(route('guest-orders.request'))->assertSessionHas('success');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('guest_order_access_tokens', 0);
    }

    public function test_issuing_a_new_link_revokes_the_previous_link(): void
    {
        Mail::fake();
        $fixture = $this->checkoutOrder();
        $payload = ['order_number' => $fixture['order']->order_number, 'email' => 'buyer@example.com'];
        $links = [];
        Mail::assertNothingSent();

        $this->post(route('guest-orders.send'), $payload);
        Mail::assertSent(GuestOrderAccessMail::class, function (GuestOrderAccessMail $mail) use (&$links): bool {
            $links[] = $mail->accessUrl;

            return true;
        });
        Mail::fake();
        $this->post(route('guest-orders.send'), $payload);
        Mail::assertSent(GuestOrderAccessMail::class, function (GuestOrderAccessMail $mail) use (&$links): bool {
            $links[] = $mail->accessUrl;

            return true;
        });

        $this->assertCount(2, $links);
        $this->get($links[0])->assertNotFound();
        $this->get($links[1])->assertOk();
        $this->assertDatabaseCount('guest_order_access_tokens', 2);
        $this->assertSame(1, GuestOrderAccessToken::query()->whereNotNull('revoked_at')->count());
    }
}
