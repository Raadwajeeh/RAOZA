<?php

namespace Tests\Feature;

use App\Domain\Admin\Enums\AdminRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerPortalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_update_profile_without_changing_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('account.settings.profile'), [
            'name' => 'Updated Customer',
            'email' => $user->email,
            'phone' => '+31 6 12345678',
        ])->assertRedirect()->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Updated Customer', $user->name);
        $this->assertSame('+31 6 12345678', $user->phone);
        $this->assertSame(AdminRole::Customer, $user->role);
        $this->assertFalse($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_change_requires_current_password_and_requires_reverification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)->patch(route('account.settings.profile'), [
            'name' => $user->name,
            'email' => 'new@example.com',
            'current_password' => 'wrong',
        ])->assertSessionHasErrors('current_password');
        $this->assertSame($user->email, $user->fresh()->email);

        $this->patch(route('account.settings.profile'), [
            'name' => $user->name,
            'email' => 'NEW@EXAMPLE.COM',
            'current_password' => 'password',
        ])->assertRedirect(route('verification.notice'));

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get(route('account.settings.edit'))->assertRedirect(route('verification.notice'));
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)->patch(route('account.settings.password'), [
            'current_password' => 'incorrect',
            'password' => 'NewStrongPass2026',
            'password_confirmation' => 'NewStrongPass2026',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));

        $this->patch(route('account.settings.password'), [
            'current_password' => 'password',
            'password' => 'NewStrongPass2026',
            'password_confirmation' => 'NewStrongPass2026',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(Hash::check('NewStrongPass2026', $user->fresh()->password));
    }

    public function test_staff_cannot_use_customer_settings_routes(): void
    {
        $staff = User::factory()->create(['is_admin' => true, 'role' => AdminRole::Owner]);

        $this->actingAs($staff)->get(route('account.settings.edit'))->assertForbidden();
        $this->patch(route('account.settings.profile'), ['name' => 'No', 'email' => $staff->email])->assertForbidden();
    }
}
