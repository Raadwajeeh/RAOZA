<?php

namespace App\Jobs;

use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\GuestOrderAccessService;
use App\Mail\GuestOrderAccessMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\TransactionalNotificationService;

class SendGuestOrderAccessLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $orderId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(GuestOrderAccessService $access, TransactionalNotificationService $notifications): void
    {
        $order = Order::query()->whereKey($this->orderId)->whereNull('user_id')->first();

        if (! $order) {
            return;
        }

        $issued = $access->issue($order);
        $notifications->sendOnce('guest-order-access:'.hash('sha256', $issued['url']), $order->customer_email, new GuestOrderAccessMail($order, $issued['url'], $issued['expires_at']), $order);
    }
}
