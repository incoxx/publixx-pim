<?php

use App\Http\Middleware\CacheCatalogResponse;
use App\Http\Middleware\CatalogAccessControl;
use App\Http\Middleware\CheckModuleLicense;
use App\Http\Middleware\DetectSuspiciousActivity;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\RestrictScopedApiToken;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Agent 2: Rate Limit alias
        $middleware->alias([
            'throttle.pim' => RateLimitMiddleware::class,
            'catalog.access' => CatalogAccessControl::class,
            'catalog.no-share' => \App\Http\Middleware\RejectCatalogShareAccess::class,
            'catalog.cache' => CacheCatalogResponse::class,
            'module' => CheckModuleLicense::class,
        ]);

        // Agent 2: Sanctum stateful middleware for API
        $middleware->api(
            prepend: [
                \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            ],
            // Global, damit auch Routen außerhalb der auth:sanctum-Gruppe erfasst
            // sind (z.B. die öffentliche v1/catalog-Gruppe selbst).
            append: [
                RestrictScopedApiToken::class,
                // Angriffs-Erkennung (Geo/Bot/Enumeration). Läuft für alle API-Requests.
                DetectSuspiciousActivity::class,
            ],
        );

        // CSRF-Ausnahmen: Die App authentifiziert per Bearer-Token (Sanctum PAT),
        // nicht per Session-Cookie. Auf "stateful" Domains (z. B. der eingebettete
        // Katalog unter derselben Domain) erzwingt Sanctum sonst CSRF für POSTs und
        // blockiert den token-basierten Login/Embed mit HTTP 419. Diese Endpoints
        // stellen Token aus bzw. sind der öffentliche, einbettbare Katalog:
        $middleware->validateCsrfTokens(except: [
            'api/v1/auth/login',
            'api/v1/catalog/*',
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
