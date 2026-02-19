<?php

use App\Http\Middleware\RateLimitMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Agent 2: Rate Limit alias
        $middleware->alias([
            'throttle.pim' => RateLimitMiddleware::class,
        ]);

        // Agent 2: Sanctum stateful middleware for API
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Agent 3: RFC 7807 Problem Details — handled in app/Exceptions/Handler.php
        // Laravel 11 auto-discovers the Handler class
    })
    ->create();
