<?php

namespace Tests\Feature;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\OrderPresenter;
use App\Domain\Fulfillment\Enums\ShipmentStatus;
use App\Domain\Fulfillment\Models\ShippingCarrier;
use App\Domain\Fulfillment\Providers\DhlShippingProvider;
use App\Domain\Fulfillment\Providers\PostNlShippingProvider;
use App\Domain\Fulfillment\Services\FulfillmentService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MultiCarrierShippingTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_partial_shipments_only_fulfill_after_all_quantities_are_handed_over(): void
    {
        [$order,$item,$staff]=$this->fixture(3);
        $service=app(FulfillmentService::class);
        $first=$service->createManualShipment($order,$this->payload($item->id,1),$staff->id)->shipment;
        $second=$service->createManualShipment($order,$this->payload($item->id,2),$staff->id)->shipment;

        $this->assertSame(2,$order->shipments()->count());
        $service->handOver($first,['operation_key'=>(string)Str::uuid()],$staff->id);
        $this->assertSame('ready_to_ship',$order->fresh()->fulfillment_status->value);
        $service->handOver($second,['operation_key'=>(string)Str::uuid()],$staff->id);
        $this->assertSame('fulfilled',$order->fresh()->fulfillment_status->value);
        $this->assertDatabaseCount('inventory_movements',0);
    }

    public function test_shipment_cannot_exceed_remaining_quantity(): void
    {
        [$order,$item,$staff]=$this->fixture(2);
        $service=app(FulfillmentService::class);
        $service->createManualShipment($order,$this->payload($item->id,1),$staff->id);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds the remaining quantity');
        $service->createManualShipment($order,$this->payload($item->id,2),$staff->id);
    }

    public function test_duplicate_create_and_handover_operations_are_idempotent(): void
    {
        [$order,$item,$staff]=$this->fixture();
        $service=app(FulfillmentService::class);
        $payload=$this->payload($item->id,1);
        $first=$service->createManualShipment($order,$payload,$staff->id);
        $duplicate=$service->createManualShipment($order,$payload,$staff->id);
        $operation=['operation_key'=>(string)Str::uuid()];
        $handed=$service->handOver($first->shipment,$operation,$staff->id);
        $again=$service->handOver($first->shipment,$operation,$staff->id);

        $this->assertTrue($first->changed);
        $this->assertFalse($duplicate->changed);
        $this->assertTrue($handed->changed);
        $this->assertFalse($again->changed);
        $this->assertDatabaseCount('shipments',1);
        $this->assertSame(1,$first->shipment->events()->where('status','shipped')->count());
    }

    public function test_cancelling_unhanded_draft_retains_history_and_releases_allocated_quantity(): void
    {
        [$order,$item,$staff]=$this->fixture();
        $service=app(FulfillmentService::class);
        $draft=$service->createManualShipment($order,$this->payload($item->id,1),$staff->id)->shipment;
        $service->cancelDraft($draft,['operation_key'=>(string)Str::uuid(),'reason'=>'Incorrect parcel setup'],$staff->id);
        $replacement=$service->createManualShipment($order,$this->payload($item->id,1),$staff->id)->shipment;

        $this->assertSame(ShipmentStatus::Cancelled,$draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->cancelled_at);
        $this->assertSame(ShipmentStatus::Pending,$replacement->status);
        $this->assertDatabaseHas('shipment_events',['shipment_id'=>$draft->id,'status'=>'cancelled']);
    }

    public function test_tracking_template_is_snapshot_based_and_delivery_requires_explicit_transition(): void
    {
        [$order,$item,$staff]=$this->fixture();
        $carrier=ShippingCarrier::where('code','manual')->firstOrFail();
        $carrier->update(['tracking_url_template'=>'https://track.example.test/p/{tracking_number}']);
        $service=app(FulfillmentService::class);
        $payload=$this->payload($item->id,1);$payload['tracking_number']='RZ 123';
        $shipment=$service->createManualShipment($order,$payload,$staff->id)->shipment;
        $this->assertSame('https://track.example.test/p/RZ%20123',$shipment->tracking_url);
        $this->assertNull($shipment->delivered_at);
        $service->handOver($shipment,['operation_key'=>(string)Str::uuid()],$staff->id);
        $this->assertNull($shipment->fresh()->delivered_at);
        $service->setOperationalStatus($shipment,['operation_key'=>(string)Str::uuid(),'status'=>'delivered'],$staff->id);
        $this->assertNotNull($shipment->fresh()->delivered_at);
    }

    public function test_customer_presentation_hides_drafts_and_exposes_only_handed_parcels(): void
    {
        [$order,$item,$staff]=$this->fixture(2);
        $service=app(FulfillmentService::class);
        $draft=$service->createManualShipment($order,$this->payload($item->id,1),$staff->id)->shipment;
        $handed=$service->createManualShipment($order,$this->payload($item->id,1),$staff->id)->shipment;
        $service->handOver($handed,['operation_key'=>(string)Str::uuid()],$staff->id);
        $detail=app(OrderPresenter::class)->detail($order->fresh());

        $this->assertCount(1,$detail['shipments']);
        $this->assertSame($handed->id,$detail['shipments'][0]['id']);
        $this->assertNotSame($draft->id,$detail['shipments'][0]['id']);
    }

    public function test_manual_a6_label_is_private_minimal_and_valid_pdf(): void
    {
        [$order,$item,$staff]=$this->fixture();
        $shipment=app(FulfillmentService::class)->createManualShipment($order,$this->payload($item->id,1),$staff->id)->shipment;
        $support=User::factory()->create(['role'=>AdminRole::Support]);
        $this->actingAs($support)->get("/admin/shipments/{$shipment->id}/documents/manual-address-label")->assertForbidden();
        $response=$this->actingAs($staff)->get("/admin/shipments/{$shipment->id}/documents/manual-address-label")->assertOk()->assertHeader('content-type','application/pdf');
        $pdf=$response->getContent();
        $this->assertStringStartsWith('%PDF-',$pdf);
        $this->assertStringNotContainsString('payment',$pdf);
    }

    public function test_future_provider_stubs_never_send_http_requests(): void
    {
        Http::fake();
        foreach([app(PostNlShippingProvider::class),app(DhlShippingProvider::class)] as $provider){
            try{$provider->fetch('not-real');}catch(RuntimeException $exception){$this->assertStringContainsString('not configured',$exception->getMessage());}
        }
        Http::assertNothingSent();
    }

    public function test_fulfillment_staff_can_register_custom_carrier_without_creating_checkout_method(): void
    {
        $staff=User::factory()->create(['role'=>AdminRole::Fulfillment]);
        $this->actingAs($staff)->post('/admin/shipping-carriers',[
            'name'=>'Local Bike Courier','code'=>'local-bike','integration_key'=>'manual',
            'tracking_url_template'=>'https://courier.example.test/{tracking_number}',
            'active'=>true,'position'=>50,'notes'=>'Manual dispatch only.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shipping_carriers',['code'=>'local-bike','integration_key'=>'manual']);
        $this->assertDatabaseMissing('shipping_methods',['code'=>'local-bike']);

        $support=User::factory()->create(['role'=>AdminRole::Support]);
        $this->actingAs($support)->post('/admin/shipping-carriers',[
            'name'=>'Forbidden','code'=>'forbidden','integration_key'=>'manual','active'=>true,'position'=>60,
        ])->assertForbidden();
    }

    public function test_handover_queues_one_customer_message_and_duplicate_request_queues_none(): void
    {
        Mail::fake();
        [$order,$item,$staff]=$this->fixture();
        $shipment=app(FulfillmentService::class)->createManualShipment($order,$this->payload($item->id,1),$staff->id)->shipment;
        $operation=(string)Str::uuid();
        $this->actingAs($staff)->post("/admin/shipments/{$shipment->id}/hand-over",['operation_key'=>$operation])->assertSessionHasNoErrors();
        $this->post("/admin/shipments/{$shipment->id}/hand-over",['operation_key'=>$operation])->assertSessionHasNoErrors();
        Mail::assertSent(\App\Mail\OrderShippedMail::class,1);
        $this->assertDatabaseHas('audit_logs',['action'=>'shipment.handed_over','subject_id'=>(string)$shipment->id]);
    }

    private function fixture(int $quantity=1):array
    {
        $staff=User::factory()->create(['role'=>AdminRole::Fulfillment]);
        $order=Order::create(['order_number'=>'RZ-SHIP-'.Str::upper(Str::random(8)),'customer_email'=>'shipping@example.test','currency'=>'EUR','subtotal_amount'=>1000*$quantity,'discount_amount'=>0,'shipping_amount'=>495,'tax_amount'=>174*$quantity,'tax_rate_basis_points'=>2100,'total_amount'=>1000*$quantity+495,'order_status'=>'confirmed','payment_status'=>'paid','fulfillment_status'=>'ready_to_ship','placed_at'=>now(),'paid_at'=>now()]);
        $item=$order->items()->create(['product_name'=>'RAOZA Test Piece','sku'=>'RZ-SHIP-1','unit_price'=>1000,'quantity'=>$quantity,'subtotal_amount'=>1000*$quantity,'discount_amount'=>0,'tax_amount'=>174*$quantity,'total_amount'=>1000*$quantity]);
        $order->addresses()->create(['type'=>'shipping','first_name'=>'Raoza','last_name'=>'Customer','street'=>'Teststraat','house_number'=>'10','postal_code'=>'1011AB','city'=>'Amsterdam','country_code'=>'NL']);
        return [$order,$item,$staff];
    }

    private function payload(int $itemId,int $quantity):array
    {
        return ['shipping_carrier_id'=>ShippingCarrier::where('code','manual')->value('id'),'tracking_number'=>null,'tracking_url'=>null,'idempotency_key'=>(string)Str::uuid(),'items'=>[['order_item_id'=>$itemId,'quantity'=>$quantity]]];
    }
}
