<?php

namespace App\Http\Controllers;

use App\Domain\Commerce\Models\Order;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Services\CustomerReturnEligibilityService;
use App\Domain\Returns\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CustomerReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $returns = ReturnRequest::query()
            ->whereHas('order', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['order:id,order_number', 'items.orderItem:id,product_name,sku'])
            ->latest('requested_at')
            ->get()
            ->map(fn (ReturnRequest $return) => $this->present($return));

        return Inertia::render('Account/Returns', ['returns' => $returns]);
    }

    public function store(
        Request $request,
        string $orderNumber,
        CustomerReturnEligibilityService $eligibilityService,
        ReturnService $returnService,
    ): RedirectResponse {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('order_number', $orderNumber)
            ->with(['items.returnItems.returnRequest', 'statusHistory'])
            ->firstOrFail();
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason_code' => ['required', Rule::in(array_keys(config('customer_portal.return_reasons', [])))],
            'items.*.reason_text' => ['nullable', 'string', 'max:1000'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $eligibility = $eligibilityService->forOrder($order);

        if (! $eligibility['eligible']) {
            throw ValidationException::withMessages(['return' => $eligibility['message']]);
        }
        $remaining = collect($eligibility['items'])->keyBy('id');
        foreach ($data['items'] as $item) {
            if (! $remaining->has($item['order_item_id']) || $item['quantity'] > $remaining[$item['order_item_id']]['remaining']) {
                throw ValidationException::withMessages(['return' => 'A selected return quantity is no longer available.']);
            }
        }

        try {
            $returnService->create($order, $data['items'], 'Customer portal request', $data['customer_note'] ?? null);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['return' => $exception->getMessage()]);
        }

        return redirect()->route('account.returns.index')->with('success', 'Return request submitted for review. No refund has been issued.');
    }

    public function present(ReturnRequest $return): array
    {
        return [
            'number' => $return->return_number,
            'orderNumber' => $return->order->order_number,
            'status' => $return->status->value,
            'requestedAt' => $return->requested_at?->toIso8601String(),
            'items' => $return->items->map(fn ($item) => [
                'name' => $item->orderItem->product_name,
                'sku' => $item->orderItem->sku,
                'quantity' => $item->quantity,
                'reason' => $item->reason_code,
                'resolution' => $item->resolution->value,
            ])->values(),
        ];
    }
}
