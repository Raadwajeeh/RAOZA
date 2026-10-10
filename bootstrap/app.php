<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/health/live', [\App\Http\Controllers\HealthController::class, 'live'])->name('health.live');
            Route::get('/health/ready', [\App\Http\Controllers\HealthController::class, 'ready'])
                ->middleware('throttle:60,1')
                ->name('health.ready');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(
                at: $trustedProxies,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO,
            );
        }
        $middleware->validateCsrfTokens(except: ['payments/webhook']);
        $middleware->web(append: [\App\Http\Middleware\SecurityHeaders::class, \App\Http\Middleware\ResolveCart::class, \App\Http\Middleware\HandleInertiaRequests::class]);
        $middleware->alias(['admin' => \App\Http\Middleware\EnsureAdmin::class, 'permission' => \App\Http\Middleware\RequirePermission::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
