<?php
namespace App\Http\Controllers;
use App\Domain\Commerce\Enums\FulfillmentStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Fulfillment\Services\FulfillmentService;
use App\Mail\OrderShippedMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
class AdminFulfillmentController extends Controller {
 public function index():Response{$orders=Order::query()->where('payment_status','paid')->whereIn('fulfillment_status',['processing','printing','ready_to_ship','on_hold'])->with('items')->latest('paid_at')->get()->map(fn($o)=>['order_number'=>$o->order_number,'email'=>$o->customer_email,'status'=>$o->fulfillment_status->value,'paid_at'=>$o->paid_at?->toIso8601String(),'items'=>$o->items->map(fn($i)=>['name'=>$i->product_name,'sku'=>$i->sku,'quantity'=>$i->quantity,'options'=>$i->variant_options])]);return Inertia::render('Admin/Fulfillment/Queue',['orders'=>$orders]);}
 public function transition(Request $r,Order $order,FulfillmentService $service):RedirectResponse{$data=$r->validate(['status'=>['required','in:processing,printing,ready_to_ship,on_hold,cancelled']]);try{$service->transition($order,FulfillmentStatus::from($data['status']),$r->user()->id,'admin_transition');}catch(RuntimeException $e){return back()->withErrors(['fulfillment'=>$e->getMessage()]);}return back()->with('success','Fulfillment status updated.');}
 public function ship(Request $r,Order $order,FulfillmentService $service):RedirectResponse{$data=$r->validate(['tracking_number'=>['nullable','string','max:255'],'tracking_url'=>['nullable','url','max:2048']]);try{$shipment=$service->createManualShipment($order,$data,$r->user()->id);}catch(RuntimeException $e){return back()->withErrors(['fulfillment'=>$e->getMessage()]);}Mail::to($order->customer_email)->queue(new OrderShippedMail($order,$shipment));return back()->with('success','Order marked as shipped.');}
}
