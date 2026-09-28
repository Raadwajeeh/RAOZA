<?php

namespace App\Domain\Payments\Services;

use App\Domain\Commerce\Enums\FulfillmentStatus;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\PaymentSyncResult;
use App\Domain\Payments\Data\ProviderPayment;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentService
{
    public function __construct(private PaymentProvider $provider) {}

    public function createAttempt(Order $order, string $redirectUrl, string $webhookUrl): Payment
    {
        $payment = DB::transaction(function () use ($order): Payment {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $lockedOrder->payment_status->canAcceptPayment()) {
                throw new RuntimeException('This order cannot accept another payment.');
            }

            $active = Payment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('provider', $this->provider->name())
                ->whereIn('status', [
                    PaymentAttemptStatus::Created->value,
                    PaymentAttemptStatus::Open->value,
                    PaymentAttemptStatus::Pending->value,
                    PaymentAttemptStatus::Authorized->value,
                    PaymentAttemptStatus::Paid->value,
                ])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($active) {
                if ($active->provider_payment_id && ($active->metadata['checkout_url'] ?? null)) {
                    return $active;
                }

                throw new RuntimeException('A payment attempt is already being prepared.');
            }

            $this->ensureReservationLocked($lockedOrder);

            return Payment::query()->create([
                'order_id' => $lockedOrder->id,
                'provider' => $this->provider->name(),
                'currency' => $lockedOrder->currency,
                'amount' => $lockedOrder->total_amount,
                'status' => PaymentAttemptStatus::Created,
                'metadata' => ['order_number' => $lockedOrder->order_number],
            ]);
        }, 3);

        if ($payment->provider_payment_id) {
            return $payment;
        }

        try {
            $order = Order::query()->findOrFail($order->id);
            $remote = $this->provider->create($order, $redirectUrl, $webhookUrl);
            $this->assertMatches($order, $remote);
            $payment->update([
                'provider_payment_id' => $remote->id,
                'method' => $remote->method,
                'status' => $this->mapStatus($remote->status),
                'provider_created_at' => now(),
                'metadata' => ['order_number' => $order->order_number, 'checkout_url' => $remote->checkoutUrl],
            ]);
            $order->update(['payment_status' => PaymentStatus::Pending]);

            return $payment->refresh();
        } catch (\Throwable $exception) {
            $payment->update(['status' => PaymentAttemptStatus::Failed, 'failed_at' => now()]);

            throw $exception;
        }
    }

    public function sync(Payment $payment, string $eventType = 'provider_sync'): PaymentSyncResult
    {
        if (! $payment->provider_payment_id) {
            throw new RuntimeException('Payment has no provider id.');
        }

        $remote = $this->provider->fetch($payment->provider_payment_id);
        $this->assertMatches($payment->order, $remote);

        return $this->apply($payment, $remote, $eventType);
    }

    private function apply(Payment $payment, ProviderPayment $remote, string $eventType): PaymentSyncResult
    {
        return DB::transaction(function () use ($payment, $remote, $eventType): PaymentSyncResult {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            $new = $this->mapStatus($remote->status);
            $key = hash('sha256', implode('|', [$locked->provider, $remote->id, $new->value]));

            if (PaymentEvent::query()->where('event_key', $key)->exists()) {
                return new PaymentSyncResult($locked->refresh(), false, false);
            }

            $changes = ['method' => $remote->method ?: $locked->method];

            // A verified paid attempt is terminal locally. A stale provider response must not regress it.
            $mayUpdateAttempt = $locked->status !== PaymentAttemptStatus::Paid || $new === PaymentAttemptStatus::Paid;
            if ($mayUpdateAttempt) {
                $changes['status'] = $new;
            }

            if ($new === PaymentAttemptStatus::Paid) {
                $changes['paid_at'] = $locked->paid_at ?: now();
            }
            if ($mayUpdateAttempt && $new === PaymentAttemptStatus::Failed) {
                $changes['failed_at'] = $locked->failed_at ?: now();
            }
            if ($mayUpdateAttempt && $new === PaymentAttemptStatus::Cancelled) {
                $changes['cancelled_at'] = $locked->cancelled_at ?: now();
            }
            if ($mayUpdateAttempt && $new === PaymentAttemptStatus::Expired) {
                $changes['expired_at'] = $locked->expired_at ?: now();
            }

            $locked->update($changes);
            $becamePaid = false;

            if ($new === PaymentAttemptStatus::Paid && $order->payment_status->canAcceptPayment()) {
                $this->commitPaidOrder($order);
                $becamePaid = true;
            } elseif (in_array($new, [PaymentAttemptStatus::Failed, PaymentAttemptStatus::Cancelled, PaymentAttemptStatus::Expired], true)
                && ! $order->payment_status->isSettled()
                && ! Payment::query()->where('order_id', $order->id)->where('id', '>', $locked->id)->exists()) {
                $this->releaseOrder($order, $new->value);
            } elseif (in_array($new, [PaymentAttemptStatus::Open, PaymentAttemptStatus::Pending, PaymentAttemptStatus::Authorized], true)
                && ! $order->payment_status->isSettled()) {
                $order->update(['payment_status' => PaymentStatus::Pending]);
            }

            PaymentEvent::query()->create([
                'payment_id' => $locked->id,
                'event_key' => $key,
                'event_type' => $eventType,
                'provider_status' => $remote->status,
                'payload' => ['provider_payment_id' => $remote->id, 'status' => $remote->status],
                'processed_at' => now(),
                'created_at' => now(),
            ]);

            return new PaymentSyncResult($locked->refresh(), $becamePaid, true);
        }, 3);
    }

    private function commitPaidOrder(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items->sortBy('variant_id') as $item) {
            if (! $item->variant_id) {
                throw new RuntimeException('Cannot commit stock for an order item without its variant.');
            }

            $inventory = Inventory::query()->where('variant_id', $item->variant_id)->lockForUpdate()->firstOrFail();
            if ($inventory->quantity_reserved < $item->quantity || $inventory->quantity_on_hand < $item->quantity) {
                throw new RuntimeException('Reserved inventory is inconsistent.');
            }

            $inventory->quantity_reserved -= $item->quantity;
            $inventory->quantity_on_hand -= $item->quantity;
            $inventory->assertConsistent();
            $inventory->save();
            InventoryMovement::query()->create([
                'variant_id' => $item->variant_id,
                'type' => InventoryMovementType::Order,
                'quantity' => -$item->quantity,
                'reference_type' => 'order',
                'reference_id' => (string) $order->id,
                'note' => 'Paid order '.$order->order_number,
                'created_at' => now(),
            ]);
        }

        $from = $order->order_status->value;
        $previousFulfillment = $order->fulfillment_status->value;
        $order->update([
            'payment_status' => PaymentStatus::Paid,
            'order_status' => OrderStatus::Confirmed,
            'fulfillment_status' => FulfillmentStatus::Processing,
            'paid_at' => $order->paid_at ?: now(),
        ]);
        $order->statusHistory()->create(['domain' => 'order', 'from_status' => $from, 'to_status' => OrderStatus::Confirmed->value, 'reason' => 'payment_verified', 'created_at' => now()]);
        $order->statusHistory()->create(['domain' => 'fulfillment', 'from_status' => $previousFulfillment, 'to_status' => FulfillmentStatus::Processing->value, 'reason' => 'payment_verified', 'created_at' => now()]);
    }

    private function releaseOrder(Order $order, string $reason): void
    {
        if ($order->order_status === OrderStatus::Cancelled) {
            return;
        }

        $order->loadMissing('items');
        foreach ($order->items->sortBy('variant_id') as $item) {
            if (! $item->variant_id) {
                continue;
            }
            $inventory = Inventory::query()->where('variant_id', $item->variant_id)->lockForUpdate()->first();
            if (! $inventory) {
                continue;
            }
            $release = min($item->quantity, $inventory->quantity_reserved);
            if ($release > 0) {
                $inventory->quantity_reserved -= $release;
                $inventory->assertConsistent();
                $inventory->save();
            }
        }

        $from = $order->order_status->value;
        $order->update(['payment_status' => PaymentStatus::Failed, 'order_status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
        $order->statusHistory()->create(['domain' => 'order', 'from_status' => $from, 'to_status' => OrderStatus::Cancelled->value, 'reason' => 'payment_'.$reason, 'created_at' => now()]);
    }

    private function ensureReservationLocked(Order $order): void
    {
        $needsReservation = $order->order_status === OrderStatus::Cancelled;
        $order->loadMissing('items');

        if ($needsReservation) {
            foreach ($order->items->sortBy('variant_id') as $item) {
                if (! $item->variant_id) {
                    throw new RuntimeException('Order item no longer has a variant.');
                }
                $inventory = Inventory::query()->where('variant_id', $item->variant_id)->lockForUpdate()->firstOrFail();
                if ($inventory->availableQuantity() < $item->quantity) {
                    throw new RuntimeException('Stock is no longer available for this payment retry.');
                }
                $inventory->quantity_reserved += $item->quantity;
                $inventory->assertConsistent();
                $inventory->save();
            }
        }

        $order->update(['order_status' => OrderStatus::PendingPayment, 'payment_status' => PaymentStatus::Pending, 'cancelled_at' => null]);
    }

    private function assertMatches(Order $order, ProviderPayment $remote): void
    {
        if ($remote->amount !== $order->total_amount
            || strtoupper($remote->currency) !== strtoupper($order->currency)
            || ($remote->orderNumber !== null && $remote->orderNumber !== $order->order_number)) {
            throw new RuntimeException('Provider payment does not match the order.');
        }
    }

    private function mapStatus(string $status): PaymentAttemptStatus
    {
        return match (strtolower($status)) {
            'open' => PaymentAttemptStatus::Open,
            'pending' => PaymentAttemptStatus::Pending,
            'authorized' => PaymentAttemptStatus::Authorized,
            'paid' => PaymentAttemptStatus::Paid,
            'failed' => PaymentAttemptStatus::Failed,
            'expired' => PaymentAttemptStatus::Expired,
            'canceled', 'cancelled' => PaymentAttemptStatus::Cancelled,
            default => throw new RuntimeException('Unsupported provider payment status.'),
        };
    }
}
