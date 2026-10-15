<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\GuestOrderAccessService;
use App\Jobs\DeliverTransactionalNotification;
use App\Models\NotificationDispatch;

Artisan::command('raoza:about', function () {
    $this->info('RAOZA — Urban Editorial commerce foundation');
})->purpose('Show RAOZA project identity');

Artisan::command('raoza:e2e:guest-url {order}', function (GuestOrderAccessService $access) {
    if (! app()->environment('testing') || ! config('commerce.demo_mode')) {
        $this->error('E2E support commands are disabled.');
        return 1;
    }
    $order=Order::query()->whereNull('user_id')->where('order_number',$this->argument('order'))->firstOrFail();
    $this->line($access->issue($order)['url']);
    return 0;
})->purpose('Issue a testing-only guest order URL for browser automation');

Schedule::command('commerce:reconcile --limit=50')
    ->name('commerce-reconciliation')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command('commerce:expire-pending-orders --limit=100')
    ->name('pending-order-expiration')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::call(function (): void {
    NotificationDispatch::query()->whereNotNull('encrypted_mailable')->where(function ($query) {
        $query->where(fn ($pending) => $pending->where('status','pending')->where(fn ($due) => $due->whereNull('next_attempt_at')->orWhere('next_attempt_at','<=',now())))
            ->orWhere(fn ($stale) => $stale->where('status','processing')->where('claimed_at','<',now()->subMinutes(10)));
    })->orderBy('id')->limit(100)->pluck('id')->each(fn ($id) => DeliverTransactionalNotification::dispatch($id));
})->name('notification-outbox-recovery')->everyMinute()->withoutOverlapping(5);
