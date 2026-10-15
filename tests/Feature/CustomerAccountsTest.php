<?php

namespace Tests\Feature;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Commerce\Services\GuestOrderAccessService;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;

class CustomerAccountsTest extends TestCase
{
    use BuildsCommerceFixtures, RefreshDatabase;

    public function test_customer_can_register_and_is_required_to_verify_email(): void
    {
        Notification::fake();

        $response = $this->post(route('account.store'), [
            'name' => 'Rae Customer',
            'email' => 'RAE@EXAMPLE.COM',
            'password' => 'StrongPass2026',
            'password_confirmation' => 'StrongPass2026',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $user = User::query()->where('email', 'rae@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->isStaff());
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get(route('account.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_customer_and_staff_login_surfaces_are_isolated(): void
    {
        $customer = User::factory()->create(['password' => 'password']);
        $staff = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'password',
            'is_admin' => true,
            'role' => AdminRole::Owner,
        ]);

        $this->post(route('account.authenticate'), ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect(route('account.index'));
        $this->assertAuthenticatedAs($customer);
        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->post(route('account.authenticate'), ['email' => $staff->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/admin/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_verified_email_claims_only_matching_unowned_guest_orders_and_revokes_links(): void
    {
        $matching = $this->checkoutOrder(overrides: ['email' => 'buyer@example.com']);
        $other = $this->checkoutOrder(overrides: ['email' => 'someone-else@example.com']);
        app(GuestOrderAccessService::class)->issue($matching['order']);
        $user = User::factory()->create(['email' => 'buyer@example.com', 'email_verified_at' => null]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
        $this->actingAs($user)->get($url)->assertRedirect(route('account.index'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame($user->id, $matching['order']->fresh()->user_id);
        $this->assertNull($other['order']->fresh()->user_id);
        $this->assertNotNull($matching['order']->guestAccessTokens()->firstOrFail()->revoked_at);
    }

    public function test_customer_can_reset_password_but_staff_reset_is_not_exposed(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['email' => 'customer@example.com']);
        $staff = User::factory()->create(['email' => 'staff@example.com', 'is_admin' => true, 'role' => AdminRole::Owner]);

        $this->post(route('password.email'), ['email' => $customer->email])->assertSessionHas('success');
        $token = null;
        Notification::assertSentTo($customer, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->assertNotNull($token);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $customer->email,
            'password' => 'NewStrongPass2026',
            'password_confirmation' => 'NewStrongPass2026',
        ])->assertRedirect(route('account.index'));
        $this->assertTrue(Hash::check('NewStrongPass2026', $customer->fresh()->password));

        Notification::fake();
        $this->post(route('logout'));
        $this->post(route('password.email'), ['email' => $staff->email])->assertSessionHas('success');
        Notification::assertNothingSent();
    }

    public function test_customer_can_only_view_orders_owned_by_that_account(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $owned = $this->checkoutOrder();
        $other = $this->checkoutOrder(overrides: ['email' => 'other@example.com']);
        $owned['order']->update(['user_id' => $customer->id]);
        $other['order']->update(['user_id' => $otherCustomer->id]);

        $this->actingAs($customer)->get(route('account.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Account/Index')->has('orders.data', 1));
        $this->get(route('account.orders.show', $owned['order']->order_number))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Account/Orders/Show')->where('order.number', $owned['order']->order_number));
        $this->get(route('account.orders.show', $other['order']->order_number))->assertNotFound();
        $this->get(route('checkout.confirmation', $other['order']->order_number))->assertNotFound();
        $this->post(route('payments.start', $other['order']->order_number))->assertNotFound();
    }

    public function test_staff_cannot_enter_customer_portal(): void
    {
        $staff = User::factory()->create(['is_admin' => true, 'role' => AdminRole::Owner]);

        $this->actingAs($staff)->get(route('account.index'))->assertForbidden();
    }
}
