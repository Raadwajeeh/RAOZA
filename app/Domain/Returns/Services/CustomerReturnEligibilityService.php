<?php

namespace App\Domain\Returns\Services;

use App\Domain\Commerce\Enums\FulfillmentStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Returns\Enums\ReturnStatus;

class CustomerReturnEligibilityService
{
    public function forOrder(Order $order): array
    {
        $order->loadMissing(['items.returnItems.returnRequest', 'statusHistory']);
        $items = $order->items->map(function ($item): array {
            $alreadyRequested = $item->returnItems
                ->filter(fn ($returnItem) => ! in_array($returnItem->returnRequest->status, [ReturnStatus::Rejected, ReturnStatus::Cancelled], true))
                ->sum('quantity');

            return [
                'id' => $item->id,
                'name' => $item->product_name,
                'sku' => $item->sku,
                'purchased' => $item->quantity,
                'alreadyRequested' => $alreadyRequested,
                'remaining' => max(0, $item->quantity - $alreadyRequested),
            ];
        })->values();

        $deadline = null;
        $message = 'This order is eligible for a return request.';
        $eligible = true;

        if (! config('customer_portal.returns_enabled', true)) {
            $eligible = false;
            $message = 'Customer return requests are currently unavailable. Please contact customer care.';
        } elseif (! in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
            $eligible = false;
            $message = 'A return can be requested only after payment is confirmed.';
        } elseif ($order->fulfillment_status !== FulfillmentStatus::Fulfilled) {
            $eligible = false;
            $message = 'A return can be requested after the order has been fulfilled.';
        } elseif ($items->sum('remaining') < 1) {
            $eligible = false;
            $message = 'No purchased quantity remains available for a new return request.';
        }

        $windowDays = config('customer_portal.return_window_days');
        if ($eligible && $windowDays !== null) {
            $fulfilledAt = $order->statusHistory
                ->where('domain', 'fulfillment')
                ->where('to_status', FulfillmentStatus::Fulfilled->value)
                ->sortByDesc('created_at')
                ->first()?->created_at;

            if (! $fulfilledAt) {
                $eligible = false;
                $message = 'The fulfillment date is unavailable. Please contact customer care for a return review.';
            } else {
                $deadline = $fulfilledAt->copy()->addDays((int) $windowDays);
                if ($deadline->isPast()) {
                    $eligible = false;
                    $message = 'The configured return request window has closed.';
                }
            }
        }

        return [
            'eligible' => $eligible,
            'message' => $message,
            'windowDays' => $windowDays,
            'deadline' => $deadline?->toIso8601String(),
            'items' => $items,
            'reasons' => config('customer_portal.return_reasons', []),
        ];
    }
}
