<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Providers\MolliePaymentProvider;
use App\Domain\Payments\Providers\DemoPaymentProvider;
class AppServiceProvider extends ServiceProvider
{
 public function register(): void { $this->app->bind(PaymentProvider::class,function(){return config('payments.default')==='demo'?app(DemoPaymentProvider::class):app(MolliePaymentProvider::class);}); }
 public function boot(): void {}
}
