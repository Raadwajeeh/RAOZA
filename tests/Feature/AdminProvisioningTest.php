<?php

namespace Tests\Feature;

use App\Domain\Admin\Enums\AdminRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_owner_requires_confirmation_and_hidden_strong_password(): void
    {
        $this->artisan('admin:provision-owner', ['email'=>'owner@example.com','--name'=>'Owner'])->assertFailed();
        $this->artisan('admin:provision-owner', ['email'=>'owner@example.com','--name'=>'Owner','--confirm'=>true])
            ->expectsQuestion('Owner password (input is hidden)', 'SecureOwner!2026')
            ->assertSuccessful();
        $owner = User::query()->firstOrFail();
        $this->assertSame(AdminRole::Owner, $owner->role);
        $this->assertFalse($owner->is_admin);
        $this->assertDatabaseHas('audit_logs', ['action'=>'owner.provisioned','subject_id'=>(string)$owner->id]);
        $this->artisan('admin:provision-owner', ['email'=>'second@example.com','--name'=>'Second','--confirm'=>true])
            ->expectsQuestion('Owner password (input is hidden)', 'SecureOwner!2026')
            ->assertFailed();
    }

    public function test_emergency_reset_is_staff_only_and_invalidates_sessions(): void
    {
        $staff = User::factory()->create(['role'=>AdminRole::Support,'password'=>'old-password']);
        $customer = User::factory()->create(['role'=>AdminRole::Customer]);
        DB::table('sessions')->insert(['id'=>'staff-session','user_id'=>$staff->id,'payload'=>'x','last_activity'=>time()]);
        $this->artisan('admin:reset-password', ['email'=>$customer->email,'--confirm'=>true])->assertFailed();
        $this->artisan('admin:reset-password', ['email'=>$staff->email,'--confirm'=>true])
            ->expectsQuestion('New staff password (input is hidden)', 'Replacement!2026')
            ->assertSuccessful();
        $this->assertTrue(Hash::check('Replacement!2026', $staff->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id'=>'staff-session']);
        $this->assertDatabaseHas('audit_logs', ['action'=>'staff.password_reset_cli','subject_id'=>(string)$staff->id]);
    }
}
