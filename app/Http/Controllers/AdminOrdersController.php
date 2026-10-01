<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Services\AuditService;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\OrderCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class AdminOrdersController
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $query = Order::latest('placed_at');
        if ($request->filled('q')) $query->where(fn ($x) => $x->where('order_number','ilike','%'.$request->q.'%')->orWhere('customer_email','ilike','%'.$request->q.'%'));
        foreach (['payment_status','fulfillment_status','order_status'] as $field) if ($request->filled($field)) $query->where($field,$request->$field);
        return Inertia::render('Admin/Orders/Index',['orders'=>$query->paginate(25)->withQueryString()]);
    }

    public function show(Request $request, Order $order)
    {
        $order->load(['items.product.images','addresses','payments.events','shipments.items','returns.items','refunds','statusHistory']);
        return Inertia::render('Admin/Orders/Show',[
            'order'=>$order,
            'canCancel'=>$request->user()->canDo('orders.manage') && $order->order_status===OrderStatus::PendingPayment,
        ]);
    }

    public function cancel(Request $request, Order $order, OrderCancellationService $service): RedirectResponse
    {
        $data = $request->validate(['reason'=>['required','string','min:3','max:500']]);
        $before = ['order_status'=>$order->order_status->value,'payment_status'=>$order->payment_status->value,'fulfillment_status'=>$order->fulfillment_status->value];
        try {
            $cancelled = $service->cancel($order, $request->user()->id, 'admin_cancelled: '.$data['reason']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cancellation'=>$exception->getMessage()]);
        }
        $this->audit->record($request,'order.unpaid_cancelled',$cancelled,$before,[
            'order_status'=>$cancelled->order_status->value,'payment_status'=>$cancelled->payment_status->value,
            'fulfillment_status'=>$cancelled->fulfillment_status->value,'reason'=>$data['reason'],
        ]);
        return back()->with('success','Unpaid order cancelled and reserved inventory released.');
    }
}
