<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PendingOrderExpirationService
{
    public function __construct(private OrderCancellationService $cancellations) {}

    /** @return array{checked:int,expired:int,skipped:int} */
    public function expire(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $cutoff = now()->subMinutes(max(5, (int) config('commerce.pending_order_reservation_minutes', 30)));
        $ids = Order::query()
            ->where('order_status', OrderStatus::PendingPayment->value)
            ->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Pending->value, PaymentStatus::Failed->value])
            ->where('placed_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $summary = ['checked' => 0, 'expired' => 0, 'skipped' => 0];
        foreach ($ids as $id) {
            $summary['checked']++;
            $expired = DB::transaction(function () use ($id, $cutoff): bool {
                $order = Order::query()->whereKey($id)->lockForUpdate()->first();
                if (! $order || ! $this->eligible($order, $cutoff)) {
                    return false;
                }
                $this->cancellations->cancel($order, null, 'reservation_window_expired');
                return true;
            }, 3);
            $summary[$expired ? 'expired' : 'skipped']++;
        }

        if ($summary['expired'] > 0) {
            Log::notice('Expired abandoned pending orders', [
                'operation' => 'pending_order_expiration',
                'checked' => $summary['checked'],
                'expired' => $summary['expired'],
                'skipped' => $summary['skipped'],
            ]);
        }

        return $summary;
    }

    private function eligible(Order $order, $cutoff): bool
    {
        if ($order->order_status !== OrderStatus::PendingPayment
            || ! in_array($order->payment_status, [PaymentStatus::Unpaid, PaymentStatus::Pending, PaymentStatus::Failed], true)
            || $order->placed_at->isAfter($cutoff)) {
            return false;
        }

        return ! $order->payments()
            ->where(function ($query): void {
                $query->where(function ($providerState): void {
                    $providerState->whereNotNull('provider_payment_id')
                        ->whereIn('status', [
                            PaymentAttemptStatus::Created->value,
                            PaymentAttemptStatus::Open->value,
                            PaymentAttemptStatus::Pending->value,
                            PaymentAttemptStatus::Authorized->value,
                            PaymentAttemptStatus::Paid->value,
                        ]);
                })->orWhere('status', PaymentAttemptStatus::Paid->value);
            })
            ->exists();
    }
}
