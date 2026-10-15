<?php

namespace App\Providers;

use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Providers\DemoPaymentProvider;
use App\Domain\Payments\Providers\MolliePaymentProvider;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentProvider::class, function () {
            return config('payments.default') === 'demo' ? app(DemoPaymentProvider::class) : app(MolliePaymentProvider::class);
        });
    }

    public function boot(): void
    {
        VerifyEmail::toMailUsing(fn ($notifiable, string $url) => (new MailMessage)
            ->subject('Verify your RAOZA email')
            ->view('emails.verify-email', ['url'=>$url, 'store'=>app(\App\Domain\Content\Services\StoreInformation::class)->get()]));
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url=route('password.reset',['token'=>$token,'email'=>$notifiable->getEmailForPasswordReset()]);
            return (new MailMessage)->subject('Reset your RAOZA password')->view('emails.reset-password',['url'=>$url,'store'=>app(\App\Domain\Content\Services\StoreInformation::class)->get()]);
        });
        Queue::failing(function (JobFailed $event): void {
            Log::error('Queued job failed', [
                'job' => $event->job->resolveName(),
                'job_uuid' => $event->job->uuid(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'exception_type' => $event->exception::class,
            ]);
        });
    }
}
