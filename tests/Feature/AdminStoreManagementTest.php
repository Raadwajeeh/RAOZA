<?php

namespace Tests\Feature;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Content\Models\SiteContent;
use App\Domain\Fulfillment\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStoreManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_store_information_and_checkout_shipping(): void
    {
        $owner=User::factory()->create(['role'=>AdminRole::Owner]);
        $this->actingAs($owner)->patch('/admin/store',[
            'brand_name' => 'RAOZA',
            'company_name' => 'Local QA Store',
            'contact_email' => 'owner@example.test',
            'support_email' => 'support@example.test',
            'country' => 'Netherlands',
            'country_code' => 'NL',
            'currency' => 'EUR',
            'locale' => 'en-NL',
            'shipping_origin' => 'Netherlands',
            'customer_service' => 'Email customer service for help.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Local QA Store',SiteContent::where('key','store_information_draft')->firstOrFail()->value['company_name']);
        $this->assertDatabaseMissing('site_content',['key'=>'store_information']);

        $this->post('/admin/store/shipping-methods',['name'=>'QA Standard','code'=>'qa-standard','provider'=>'demo','price'=>495,'currency'=>'EUR','active'=>true,'position'=>1,'description'=>'Local QA only'])->assertSessionHasNoErrors();
        $method=ShippingMethod::where('code','qa-standard')->firstOrFail();
        $this->assertSame(495,$method->price);
        $this->assertFalse($method->active);
        $this->assertTrue($method->pending_configuration['active']);

        $this->patch("/admin/store/shipping-methods/{$method->id}",['name'=>'QA Standard Updated','code'=>'qa-standard','provider'=>'demo','price'=>595,'currency'=>'EUR','active'=>false,'position'=>2,'description'=>'Updated QA method'])->assertSessionHasNoErrors();
        $this->assertSame(495,$method->fresh()->price);
        $this->assertSame(595,$method->fresh()->pending_configuration['price']);
        $this->assertFalse($method->fresh()->active);
    }
}
