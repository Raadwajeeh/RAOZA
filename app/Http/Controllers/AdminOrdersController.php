<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Services\AuditService;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\OrderCancellationService;
use App\Domain\Invoicing\Services\InvoicingReadiness;
use App\Domain\Invoicing\Enums\InvoiceType;
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
        if ($request->filled('q')) {
            $term = '%'.mb_strtolower(trim((string) $request->q)).'%';
            $query->where(fn ($x) => $x->whereRaw('LOWER(order_number) LIKE ?', [$term])->orWhereRaw('LOWER(customer_email) LIKE ?', [$term]));
        }
        foreach (['payment_status', 'fulfillment_status', 'order_status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->$field);
            }
        }

        return Inertia::render('Admin/Orders/Index', ['orders' => $query->paginate(25)->withQueryString()]);
    }

    public function show(Request $request, Order $order, InvoicingReadiness $readiness)
    {
        $order->load(['items.product.images', 'addresses', 'payments.events', 'shipments.items.orderItem', 'shipments.events', 'shipments.carrier', 'returns.items', 'refunds', 'statusHistory', 'invoices']);

        $allocated = $order->shipments->reject(fn ($shipment) => $shipment->status === \App\Domain\Fulfillment\Enums\ShipmentStatus::Cancelled)
            ->flatMap->items->groupBy('order_item_id')->map->sum('quantity');

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
            'canCancel' => $request->user()->canDo('orders.manage') && $order->order_status === OrderStatus::PendingPayment,
            'documents' => $order->invoices->sortByDesc('issued_at')->map(fn ($document) => [
                'number' => $document->document_number,
                'type' => $document->document_type->value,
                'issuedAt' => $document->issued_at?->toIso8601String(),
                'total' => $document->total_amount,
                'currency' => $document->currency,
                'url' => route('admin.orders.documents.accounting', [$order->order_number, $document->document_number]),
            ])->values(),
            'operationalDocuments' => collect(['packing-slip', 'picking-list', 'order-sheet'])->map(fn ($type) => [
                'type' => $type,
                'url' => route('admin.orders.documents.operational', [$order->order_number, $type]),
            ]),
            'invoicingBlockers' => array_values(array_unique([...$readiness->blockers(), ...$readiness->blockers(InvoiceType::CreditNote)])),
            'shippingCarriers' => \App\Domain\Fulfillment\Models\ShippingCarrier::query()->where('active', true)->orderBy('position')->orderBy('name')->get(),
            'remainingShipmentItems' => $order->items->map(fn ($item) => [
                'id' => $item->id, 'name' => $item->product_name, 'sku' => $item->sku,
                'purchased' => $item->quantity, 'remaining' => max(0, $item->quantity - (int) ($allocated[$item->id] ?? 0)),
            ])->values(),
            'canManageFulfillment' => $request->user()->canDo('fulfillment.manage'),
        ]);
    }

    public function cancel(Request $request, Order $order, OrderCancellationService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $before = ['order_status' => $order->order_status->value, 'payment_status' => $order->payment_status->value, 'fulfillment_status' => $order->fulfillment_status->value];
        try {
            $cancelled = $service->cancel($order, $request->user()->id, 'admin_cancelled: '.$data['reason']);
        } catch (RuntimeException $exception) {
            $this->audit->record($request, 'order.cancellation_rejected', $order->fresh(), $before, [
                'reason' => $data['reason'], 'error' => $exception->getMessage(),
            ]);

            return back()->withErrors(['cancellation' => $exception->getMessage()]);
        }
        $this->audit->record($request, 'order.unpaid_cancelled', $cancelled, $before, [
            'order_status' => $cancelled->order_status->value, 'payment_status' => $cancelled->payment_status->value,
            'fulfillment_status' => $cancelled->fulfillment_status->value, 'reason' => $data['reason'],
        ]);

        return back()->with('success', 'Unpaid order cancelled and reserved inventory released.');
    }
}
