<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: static function (): void {
            // Shadow PayZephyr's built-in webhook route (D-40). App routes load after package routes,
            // so this replaces it: Paystack webhooks are only accepted at /api/webhooks/paystack.
            Route::middleware('api')
                ->post('/payments/webhook/{provider}', static fn(): never => abort(404))
                ->name('payments.webhook');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Which proxies are trusted comes from config/trustedproxy.php (TRUSTED_PROXIES). From a trusted
        // proxy, accept only the client IP, scheme and port. X-Forwarded-Host stays untrusted: most proxies
        // pass a client-set value through, and a spoofed host would poison password-reset and signed links.
        $forwarded = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO;
        $middleware->trustProxies(headers: $forwarded | Request::HEADER_X_FORWARDED_PORT);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn(Request $request) => $request->is('api/*') || $request->expectsJson());
    })
    ->create();
