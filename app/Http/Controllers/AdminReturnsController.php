<?php
namespace App\Http\Controllers;
use App\Domain\Commerce\Models\Order;
use App\Domain\Returns\Enums\RefundStatus;
use App\Domain\Returns\Enums\ReturnCondition;
use App\Domain\Returns\Enums\ReturnResolution;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\Refund;
use App\Domain\Returns\Models\ReturnItem;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Services\RefundService;
use App\Domain\Returns\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
class AdminReturnsController extends Controller {
 public function index():Response {$returns=ReturnRequest::with(['order:id,order_number,customer_email,currency,total_amount','items.orderItem'])->latest('requested_at')->get()->map(fn($r)=>['return_number'=>$r->return_number,'order_number'=>$r->order->order_number,'email'=>$r->order->customer_email,'status'=>$r->status->value,'requested_at'=>$r->requested_at?->toIso8601String(),'items'=>$r->items->map(fn($i)=>['id'=>$i->id,'name'=>$i->orderItem->product_name,'sku'=>$i->orderItem->sku,'quantity'=>$i->quantity,'condition'=>$i->condition?->value,'resolution'=>$i->resolution->value,'restocked'=>(bool)$i->restocked_at])]);return Inertia::render('Admin/Returns/Index',['returns'=>$returns]);}
 public function transition(Request $r,ReturnRequest $return,ReturnService $service):RedirectResponse {$d=$r->validate(['status'=>['required','in:under_review,approved,awaiting_return,received,inspected,completed,rejected,cancelled'],'note'=>['nullable','string','max:2000']]);$service->transition($return,ReturnStatus::from($d['status']),$d['note']??null);return back();}
 public function inspect(Request $r,ReturnItem $returnItem,ReturnService $service):RedirectResponse {$d=$r->validate(['condition'=>['required','in:resellable,damaged,other'],'resolution'=>['required','in:refund,no_refund']]);$service->inspect($returnItem,ReturnCondition::from($d['condition']),ReturnResolution::from($d['resolution']));return back();}
 public function refund(Request $r,Order $order,RefundService $service):RedirectResponse {$d=$r->validate(['amount'=>['required','integer','min:1'],'return_id'=>['nullable','integer','exists:returns,id'],'reason'=>['nullable','string','max:500']]);$return=isset($d['return_id'])?ReturnRequest::where('order_id',$order->id)->findOrFail($d['return_id']):null;$service->request($order,(int)$d['amount'],$return,$d['reason']??null);return back();}
}
