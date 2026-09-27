<?php
namespace Tests\Feature\Security;

use App\Domain\Admin\Enums\AdminRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_credentials_cannot_be_used_at_admin_login(): void
    {
        User::factory()->create(['email'=>'customer@example.test','password'=>'secret-pass','role'=>AdminRole::Customer,'is_admin'=>false]);
        $this->post('/admin/login',['email'=>'customer@example.test','password'=>'secret-pass'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_support_cannot_open_inventory_or_fulfillment_management(): void
    {
        $user=User::factory()->create(['role'=>AdminRole::Support,'is_admin'=>false]);
        $this->actingAs($user)->get('/admin/inventory')->assertForbidden();
        $this->actingAs($user)->get('/admin/fulfillment')->assertForbidden();
    }

    public function test_fulfillment_role_can_open_inventory_read_view_but_not_adjust_it(): void
    {
        $user=User::factory()->create(['role'=>AdminRole::Fulfillment,'is_admin'=>false]);
        $this->actingAs($user)->get('/admin/inventory')->assertOk();
        $this->assertFalse($user->canDo('inventory.adjust'));
    }
}
