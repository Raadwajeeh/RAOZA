<?php

namespace App\Http\Controllers;

use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\OrderPresenter;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Services\CustomerReturnEligibilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->latest('placed_at')
            ->paginate(15)
            ->through(fn (Order $order) => [
                'number' => $order->order_number,
                'placedAt' => $order->placed_at?->toIso8601String(),
                'total' => $order->total_amount,
                'currency' => $order->currency,
                'paymentStatus' => $order->payment_status->value,
                'orderStatus' => $order->order_status->value,
                'fulfillmentStatus' => $order->fulfillment_status->value,
            ]);

        return Inertia::render('Account/Index', ['orders' => $orders]);
    }

    public function show(
        Request $request,
        string $orderNumber,
        OrderPresenter $presenter,
        CustomerReturnEligibilityService $eligibility,
    ): Response
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('order_number', $orderNumber)
            ->with(['items.returnItems.returnRequest', 'statusHistory', 'returns.items.orderItem'])
            ->firstOrFail();

        $detail = $presenter->detail($order);
        $detail['returnEligibility'] = $eligibility->forOrder($order);
        $detail['returns'] = $order->returns->sortByDesc('requested_at')->map(fn (ReturnRequest $return) => [
            'number' => $return->return_number,
            'status' => $return->status->value,
            'requestedAt' => $return->requested_at?->toIso8601String(),
            'items' => $return->items->map(fn ($item) => [
                'name' => $item->orderItem->product_name,
                'quantity' => $item->quantity,
            ])->values(),
        ])->values();
        $detail['documents'] = $order->invoices()->orderByDesc('issued_at')->get()->map(fn ($document) => [
            'number' => $document->document_number,
            'type' => $document->document_type->value,
            'issuedAt' => $document->issued_at?->toIso8601String(),
            'total' => $document->total_amount,
            'currency' => $document->currency,
            'url' => route('account.orders.documents.download', [$order->order_number, $document->document_number]),
        ])->values();

        return Inertia::render('Account/Orders/Show', ['order' => $detail]);
    }
}
