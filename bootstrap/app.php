<?php

use App\Http\Middleware\RateLimitMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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

        // Return 401 JSON for unauthenticated API requests instead of redirecting to login
        $middleware->redirectGuestsTo(fn (Request $request) => $request->expectsJson() ? null : null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return 401 JSON for unauthenticated requests (pure API app, no login route)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'type' => 'https://tools.ietf.org/html/rfc7235#section-3.1',
                'title' => 'Unauthenticated',
                'status' => 401,
                'detail' => 'Authentication is required to access this resource.',
            ], 401);
        });
    })
    ->create();
