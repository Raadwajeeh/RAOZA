<?php
namespace App\Http\Controllers;
use App\Domain\Commerce\Models\Order;
use App\Domain\Returns\Enums\ReturnCondition;
use App\Domain\Returns\Enums\ReturnResolution;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\ReturnItem;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Services\RefundService;
use App\Domain\Returns\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AdminReturnsController extends Controller {
 public function index():Response {
  $returns=ReturnRequest::with(['order:id,order_number,customer_email,currency,total_amount','items.orderItem','refunds'])->latest('requested_at')->get()->map(fn($r)=>[
   'return_number'=>$r->return_number,'order_number'=>$r->order->order_number,'email'=>$r->order->customer_email,'currency'=>$r->order->currency,'order_total'=>$r->order->total_amount,'status'=>$r->status->value,'requested_at'=>$r->requested_at?->toIso8601String(),'admin_note'=>$r->admin_note,
   'items'=>$r->items->map(fn($i)=>['id'=>$i->id,'name'=>$i->orderItem->product_name,'sku'=>$i->orderItem->sku,'quantity'=>$i->quantity,'condition'=>$i->condition?->value,'resolution'=>$i->resolution->value,'restocked'=>(bool)$i->restocked_at]),
   'refunds'=>$r->refunds->map(fn($x)=>['id'=>$x->id,'amount'=>$x->amount,'status'=>$x->status->value,'reason'=>$x->reason,'requested_at'=>$x->requested_at?->toIso8601String()]),
  ]);
  return Inertia::render('Admin/Returns/Index',['returns'=>$returns]);
 }
 public function store(Request $r,Order $order,ReturnService $service):RedirectResponse {
  $d=$r->validate(['reason_summary'=>['nullable','string','max:255'],'customer_note'=>['nullable','string','max:2000'],'items'=>['required','array','min:1'],'items.*.order_item_id'=>['required','integer'],'items.*.quantity'=>['required','integer','min:1'],'items.*.reason_code'=>['required','string','max:64'],'items.*.reason_text'=>['nullable','string','max:1000']]);
  try{$service->create($order,$d['items'],$d['reason_summary']??null,$d['customer_note']??null);}catch(RuntimeException $e){return back()->withErrors(['return'=>$e->getMessage()]);}
  return back()->with('success','Return request created.');
 }
 public function transition(Request $r,ReturnRequest $return,ReturnService $service):RedirectResponse {
  $d=$r->validate(['status'=>['required','in:under_review,approved,awaiting_return,received,inspected,completed,rejected,cancelled'],'note'=>['nullable','string','max:2000']]);
  try{$service->transition($return,ReturnStatus::from($d['status']),$d['note']??null);}catch(RuntimeException $e){return back()->withErrors(['return'=>$e->getMessage()]);}
  return back()->with('success','Return status updated.');
 }
 public function inspect(Request $r,ReturnItem $returnItem,ReturnService $service):RedirectResponse {
  $d=$r->validate(['condition'=>['required','in:resellable,damaged,other'],'resolution'=>['required','in:refund,no_refund']]);
  try{$service->inspect($returnItem,ReturnCondition::from($d['condition']),ReturnResolution::from($d['resolution']));}catch(RuntimeException $e){return back()->withErrors(['return'=>$e->getMessage()]);}
  return back()->with('success','Return item inspected.');
 }
 public function refund(Request $r,Order $order,RefundService $service):RedirectResponse {
  $d=$r->validate(['amount'=>['required','integer','min:1'],'return_id'=>['nullable','integer','exists:returns,id'],'return_number'=>['nullable','string'],'reason'=>['nullable','string','max:500'],'idempotency_key'=>['nullable','string','max:128']]);
  $return=null;if(isset($d['return_id']))$return=ReturnRequest::where('order_id',$order->id)->findOrFail($d['return_id']);elseif(!empty($d['return_number']))$return=ReturnRequest::where('order_id',$order->id)->where('return_number',$d['return_number'])->firstOrFail();
  try{$service->request($order,(int)$d['amount'],$return,$d['reason']??null,$d['idempotency_key']??null);}catch(RuntimeException $e){return back()->withErrors(['refund'=>$e->getMessage()]);}
  return back()->with('success','Refund request created. Provider processing is still required.');
 }
}
