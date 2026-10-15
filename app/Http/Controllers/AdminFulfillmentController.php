<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Services\AuditService;
use App\Domain\Commerce\Enums\FulfillmentStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Fulfillment\Models\Shipment;
use App\Domain\Fulfillment\Models\ShippingCarrier;
use App\Domain\Fulfillment\Services\FulfillmentService;
use App\Mail\OrderShippedMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AdminFulfillmentController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index():Response
    {
        $orders=Order::query()->whereIn('payment_status',['paid','partially_refunded'])->whereIn('fulfillment_status',['processing','printing','ready_to_ship','on_hold'])->with('items')->latest('paid_at')->get()->map(fn($o)=>['order_number'=>$o->order_number,'email'=>$o->customer_email,'status'=>$o->fulfillment_status->value,'paid_at'=>$o->paid_at?->toIso8601String(),'items'=>$o->items->map(fn($i)=>['name'=>$i->product_name,'sku'=>$i->sku,'quantity'=>$i->quantity,'options'=>$i->variant_options])]);
        return Inertia::render('Admin/Fulfillment/Queue',['orders'=>$orders,'shippingCarriers'=>ShippingCarrier::query()->orderBy('position')->orderBy('name')->get()]);
    }

    public function transition(Request $request,Order $order,FulfillmentService $service):RedirectResponse
    {
        $data=$request->validate(['status'=>['required','in:processing,printing,ready_to_ship,on_hold,cancelled']]);
        $before=['fulfillment_status'=>$order->fulfillment_status->value];
        try{$updated=$service->transition($order,FulfillmentStatus::from($data['status']),$request->user()->id,'admin_transition');}
        catch(RuntimeException $exception){return back()->withErrors(['fulfillment'=>$exception->getMessage()]);}
        $this->audit->record($request,'fulfillment.transitioned',$updated,$before,['fulfillment_status'=>$updated->fulfillment_status->value]);
        return back()->with('success','Fulfillment status updated.');
    }

    public function ship(Request $request,Order $order,FulfillmentService $service):RedirectResponse
    {
        $data=$request->validate($this->createRules($order));
        try{$result=$service->createManualShipment($order,$data,$request->user()->id);}
        catch(RuntimeException $exception){return back()->withErrors(['fulfillment'=>$exception->getMessage()]);}
        if($result->changed)$this->audit->record($request,'shipment.draft_created',$result->shipment,null,['order_id'=>$order->id,'carrier_code'=>$result->shipment->carrier_code,'item_count'=>$result->shipment->items->count()]);
        return back()->with('success',$result->changed?'Shipment draft created. Confirm handover separately when the parcel reaches the carrier.':'Duplicate request safely ignored.');
    }

    public function update(Request $request,Shipment $shipment,FulfillmentService $service):RedirectResponse
    {
        $data=$request->validate(['shipping_carrier_id'=>['required','integer',Rule::exists('shipping_carriers','id')->where('active',true)],'tracking_number'=>['nullable','string','max:255'],'tracking_url'=>['nullable','url:https','max:2048'],'operation_key'=>['required','uuid']]);
        return $this->mutation($request,$shipment,fn()=>$service->updateDraft($shipment,$data,$request->user()->id),'shipment.updated','Shipment draft updated.');
    }

    public function handOver(Request $request,Shipment $shipment,FulfillmentService $service):RedirectResponse
    {
        $data=$request->validate(['operation_key'=>['required','uuid'],'shipped_at'=>['nullable','date','before_or_equal:now']]);
        $before=$shipment->toArray();
        try{$result=$service->handOver($shipment,$data,$request->user()->id);}catch(RuntimeException $exception){return back()->withErrors(['fulfillment'=>$exception->getMessage()]);}
        if(!$result->changed)return back()->with('success','Duplicate operation safely ignored.');
        $this->audit->record($request,'shipment.handed_over',$result->shipment,$before,$result->shipment->toArray());
        Mail::to($result->shipment->order->customer_email)->queue(new OrderShippedMail($result->shipment->order,$result->shipment));
        return back()->with('success','Carrier handover confirmed.');
    }

    public function status(Request $request,Shipment $shipment,FulfillmentService $service):RedirectResponse
    {
        $data=$request->validate(['operation_key'=>['required','uuid'],'status'=>['required',Rule::in(['in_transit','exception','delivered'])]]);
        return $this->mutation($request,$shipment,fn()=>$service->setOperationalStatus($shipment,$data,$request->user()->id),'shipment.status_changed','Shipment status updated.');
    }

    public function cancel(Request $request,Shipment $shipment,FulfillmentService $service):RedirectResponse
    {
        $data=$request->validate(['operation_key'=>['required','uuid'],'reason'=>['required','string','min:3','max:500']]);
        return $this->mutation($request,$shipment,fn()=>$service->cancelDraft($shipment,$data,$request->user()->id),'shipment.cancelled','Shipment draft cancelled; history was retained.');
    }

    private function mutation(Request $request,Shipment $shipment,callable $callback,string $action,string $message):RedirectResponse
    {
        $before=$shipment->toArray();
        try{$result=$callback();}catch(RuntimeException $exception){return back()->withErrors(['fulfillment'=>$exception->getMessage()]);}
        if($result->changed)$this->audit->record($request,$action,$result->shipment,$before,$result->shipment->toArray());
        return back()->with('success',$result->changed?$message:'Duplicate operation safely ignored.');
    }

    private function createRules(Order $order):array
    {
        return ['shipping_carrier_id'=>['required','integer',Rule::exists('shipping_carriers','id')->where('active',true)],'tracking_number'=>['nullable','string','max:255'],'tracking_url'=>['nullable','url:https','max:2048'],'idempotency_key'=>['required','uuid'],'items'=>['required','array','min:1'],'items.*.order_item_id'=>['required','integer','distinct',Rule::exists('order_items','id')->where('order_id',$order->id)],'items.*.quantity'=>['required','integer','min:1']];
    }
}
