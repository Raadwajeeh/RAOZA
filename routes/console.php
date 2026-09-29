<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('raoza:about', function () {
    $this->info('RAOZA — Urban Editorial commerce foundation');
})->purpose('Show RAOZA project identity');

Schedule::command('commerce:reconcile --limit=50')
    ->name('commerce-reconciliation')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
