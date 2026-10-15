<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Models\GuestOrderAccessToken;
use App\Domain\Commerce\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestOrderAccessService
{
    /** @return array{url:string, expires_at:\DateTimeInterface} */
    public function issue(Order $order): array
    {
        abort_unless($order->user_id === null, 404);

        [$plainToken, $expiresAt] = DB::transaction(function () use ($order): array {
            GuestOrderAccessToken::query()
                ->where('order_id', $order->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $plainToken = Str::random(64);
            $expiresAt = now()->addMinutes((int) config('customer_accounts.guest_order_link_minutes', 30));
            GuestOrderAccessToken::query()->create([
                'order_id' => $order->id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => $expiresAt,
            ]);

            return [$plainToken, $expiresAt];
        }, 3);

        return [
            'url' => route('guest-orders.show', ['token' => $plainToken]),
            'expires_at' => $expiresAt,
        ];
    }

    /** @return array{order:Order, expires_at:\DateTimeInterface} */
    public function resolve(string $plainToken): array
    {
        abort_unless((bool) preg_match('/^[A-Za-z0-9]{64}$/D', $plainToken), 404);

        return DB::transaction(function () use ($plainToken): array {
            $access = GuestOrderAccessToken::query()
                ->where('token_hash', hash('sha256', $plainToken))
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::query()->whereKey($access->order_id)->whereNull('user_id')->firstOrFail();
            $access->update(['last_accessed_at' => now()]);

            return ['order' => $order, 'expires_at' => $access->expires_at];
        }, 3);
    }
}
