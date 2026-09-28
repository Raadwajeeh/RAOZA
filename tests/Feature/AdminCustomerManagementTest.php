<?php

namespace Tests\Feature;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Commerce\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_meaningful_guest_customer_detail(): void
    {
        $owner=User::factory()->create(['role'=>AdminRole::Owner]);
        $order=Order::create(['order_number'=>'RZ-CUSTOMER-1','customer_email'=>'guest@example.test','customer_phone'=>'0612345678','currency'=>'EUR','subtotal_amount'=>5000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>868,'tax_rate_basis_points'=>2100,'total_amount'=>5000,'order_status'=>'confirmed','payment_status'=>'paid','fulfillment_status'=>'unfulfilled','placed_at'=>now(),'paid_at'=>now()]);
        $order->addresses()->create(['type'=>'shipping','first_name'=>'Rae','last_name'=>'Customer','street'=>'Teststraat','house_number'=>'10','postal_code'=>'1234 AB','city'=>'Amsterdam','country_code'=>'NL']);

        $this->actingAs($owner)->get('/admin/customers/'.rawurlencode('guest@example.test'))
            ->assertOk()->assertInertia(fn(Assert $page)=>$page->component('Admin/Customers/Show')->where('customer.name','Rae Customer')->where('customer.phone','0612345678')->has('customer.orders',1)->has('customer.addresses',1));
    }
}
