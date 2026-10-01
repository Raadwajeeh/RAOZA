<?php
namespace App\Http\Controllers;
use App\Domain\Admin\Services\AuditService;
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
 public function __construct(private AuditService $audit){}
 public function index():Response {
  $returns=ReturnRequest::with(['order:id,order_number,customer_email,currency,total_amount','items.orderItem','refunds'])->latest('requested_at')->get()->map(fn($r)=>[
   'return_number'=>$r->return_number,'order_number'=>$r->order->order_number,'email'=>$r->order->customer_email,'currency'=>$r->order->currency,'order_total'=>$r->order->total_amount,'status'=>$r->status->value,'requested_at'=>$r->requested_at?->toIso8601String(),'admin_note'=>$r->admin_note,
   'items'=>$r->items->map(fn($i)=>['id'=>$i->id,'name'=>$i->orderItem->product_name,'sku'=>$i->orderItem->sku,'quantity'=>$i->quantity,'condition'=>$i->condition?->value,'resolution'=>$i->resolution->value,'restocked'=>(bool)$i->restocked_at]),
   'refunds'=>$r->refunds->map(fn($x)=>['id'=>$x->id,'amount'=>$x->amount,'status'=>$x->status->value,'provider_status'=>$x->provider_status,'provider_refund_id'=>$x->provider_refund_id,'reason'=>$x->reason,'requested_at'=>$x->requested_at?->toIso8601String(),'last_synced_at'=>$x->last_synced_at?->toIso8601String()]),
  ]);
  return Inertia::render('Admin/Returns/Index',['returns'=>$returns]);
 }
 public function store(Request $r,Order $order,ReturnService $service):RedirectResponse {
  $d=$r->validate(['reason_summary'=>['nullable','string','max:255'],'customer_note'=>['nullable','string','max:2000'],'items'=>['required','array','min:1'],'items.*.order_item_id'=>['required','integer'],'items.*.quantity'=>['required','integer','min:1'],'items.*.reason_code'=>['required','string','max:64'],'items.*.reason_text'=>['nullable','string','max:1000']]);
  try{$created=$service->create($order,$d['items'],$d['reason_summary']??null,$d['customer_note']??null);}catch(RuntimeException $e){return back()->withErrors(['return'=>$e->getMessage()]);}
  $this->audit->record($r,'return.created',$created,null,['order_id'=>$order->id,'item_count'=>count($d['items']),'status'=>$created->status->value]);
  return back()->with('success','Return request created.');
 }
 public function transition(Request $r,ReturnRequest $return,ReturnService $service):RedirectResponse {
  $d=$r->validate(['status'=>['required','in:under_review,approved,awaiting_return,received,inspected,completed,rejected,cancelled'],'note'=>['nullable','string','max:2000']]);
  $before=['status'=>$return->status->value];try{$updated=$service->transition($return,ReturnStatus::from($d['status']),$d['note']??null);}catch(RuntimeException $e){return back()->withErrors(['return'=>$e->getMessage()]);}
  $this->audit->record($r,'return.transitioned',$updated,$before,['status'=>$updated->status->value]);
  return back()->with('success','Return status updated.');
 }
 public function inspect(Request $r,ReturnItem $returnItem,ReturnService $service):RedirectResponse {
  $d=$r->validate(['condition'=>['required','in:resellable,damaged,other'],'resolution'=>['required','in:refund,no_refund']]);
  $before=['condition'=>$returnItem->condition?->value,'resolution'=>$returnItem->resolution->value];try{$updated=$service->inspect($returnItem,ReturnCondition::from($d['condition']),ReturnResolution::from($d['resolution']));}catch(RuntimeException $e){return back()->withErrors(['return'=>$e->getMessage()]);}
  $this->audit->record($r,'return_item.inspected',$updated,$before,['condition'=>$updated->condition?->value,'resolution'=>$updated->resolution->value,'restocked'=>(bool)$updated->restocked_at]);
  return back()->with('success','Return item inspected.');
 }
 public function refund(Request $r,Order $order,RefundService $service):RedirectResponse {
  $d=$r->validate(['amount'=>['required','integer','min:1'],'return_id'=>['nullable','integer','exists:returns,id'],'return_number'=>['nullable','string'],'reason'=>['nullable','string','max:500'],'idempotency_key'=>['required','string','max:128']]);
  $return=null;if(isset($d['return_id']))$return=ReturnRequest::where('order_id',$order->id)->findOrFail($d['return_id']);elseif(!empty($d['return_number']))$return=ReturnRequest::where('order_id',$order->id)->where('return_number',$d['return_number'])->firstOrFail();
  try{$refund=$service->request($order,(int)$d['amount'],$return,$d['reason']??null,$d['idempotency_key']);$refund=$service->submit($refund);}catch(RuntimeException $e){return back()->withErrors(['refund'=>$e->getMessage()]);}
  $this->audit->record($r,'refund.initiated',$refund,null,['order_id'=>$order->id,'amount'=>$refund->amount,'currency'=>$refund->currency,'status'=>$refund->status->value,'return_id'=>$refund->return_id]);
  return back()->with('success',$refund->status===\App\Domain\Returns\Enums\RefundStatus::Succeeded?'Refund confirmed by the payment provider.':'Refund submitted to the payment provider.');
 }
 public function syncRefund(Request $r,\App\Domain\Returns\Models\Refund $refund,RefundService $service):RedirectResponse {
  $before=['status'=>$refund->status->value,'provider_status'=>$refund->provider_status];try{$updated=$refund->provider_refund_id?$service->sync($refund):$service->submit($refund);}catch(RuntimeException $e){return back()->withErrors(['refund'=>$e->getMessage()]);}
  $this->audit->record($r,'refund.operator_synced',$updated,$before,['status'=>$updated->status->value,'provider_status'=>$updated->provider_status]);
  return back()->with('success',$updated->status===\App\Domain\Returns\Enums\RefundStatus::Succeeded?'Refund confirmed by the payment provider.':'Refund status synchronized.');
 }
}
