<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Models\GuestOrderAccessToken;
use App\Domain\Commerce\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GuestOrderClaimService
{
    public function claimForVerifiedCustomer(User $user): int
    {
        if ($user->isStaff() || ! $user->hasVerifiedEmail()) {
            return 0;
        }

        return DB::transaction(function () use ($user): int {
            $orderIds = Order::query()
                ->whereNull('user_id')
                ->whereRaw('LOWER(customer_email) = ?', [mb_strtolower($user->email)])
                ->lockForUpdate()
                ->pluck('id');

            if ($orderIds->isEmpty()) {
                return 0;
            }

            $updated = Order::query()->whereIn('id', $orderIds)->whereNull('user_id')->update(['user_id' => $user->id]);
            GuestOrderAccessToken::query()
                ->whereIn('order_id', $orderIds)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return $updated;
        }, 3);
    }
}
