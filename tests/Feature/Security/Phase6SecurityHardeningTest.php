<?php

namespace Tests\Feature\Security;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class Phase6SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_registration_cannot_mass_assign_staff_fields(): void
    {
        $this->post('/account/register',[
            'name'=>'Customer','email'=>'mass@example.test','password'=>'CustomerPass2026','password_confirmation'=>'CustomerPass2026',
            'role'=>'owner','is_admin'=>true,'staff_disabled_at'=>now(),
        ])->assertRedirect();
        $user=User::where('email','mass@example.test')->firstOrFail();
        $this->assertSame(AdminRole::Customer,$user->role);
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->staff_disabled_at);
    }

    public function test_state_changing_customer_route_rejects_missing_csrf_token(): void
    {
        $this->app['env']='local';
        $this->withMiddleware(ValidateCsrfToken::class)
            ->post('/account/register',['name'=>'No CSRF'])
            ->assertStatus(419);
    }

    public function test_customer_login_regenerates_session_identifier(): void
    {
        $user=User::factory()->create(['email'=>'session@example.test','password'=>'SessionPass2026','role'=>AdminRole::Customer]);
        $this->withSession(['marker'=>'before']);
        $old=session()->getId();
        $this->post('/account/login',['email'=>$user->email,'password'=>'SessionPass2026'])->assertRedirect('/account');
        $this->assertNotSame($old,session()->getId());
    }

    public function test_catalog_upload_rejects_svg_and_executable_content(): void
    {
        Storage::fake('public');
        $owner=User::factory()->create(['role'=>AdminRole::Owner]);
        $product=Product::create(['name'=>'Upload target','slug'=>'upload-target','description'=>'test','status'=>ProductStatus::Draft,'base_price'=>1000]);
        $file=UploadedFile::fake()->createWithContent('payload.svg','<svg><script>alert(1)</script></svg>');
        $this->actingAs($owner)->post("/admin/products/{$product->id}/images",['images'=>[$file]])->assertSessionHasErrors('images.0');
        Storage::disk('public')->assertDirectoryEmpty('products');
    }
}
