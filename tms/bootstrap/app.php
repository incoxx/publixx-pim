<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            // Validierungsfehler und andere HTTP-Exceptions von Laravel selbst
            // rendern lassen — sonst geht die 422-Feldliste verloren.
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            // Nur ausserhalb von Serverfehlern die Original-Message ausliefern.
            // Bei 5xx wuerden sonst DB-Fehler inkl. SQL-Fragmenten und
            // Provider-Antworten (die API-Key-Reste enthalten koennen) im
            // HTTP-Body landen — unabhaengig von APP_DEBUG.
            if ($status >= 500 && !config('app.debug')) {
                \Illuminate\Support\Facades\Log::error('TMS error', [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'path' => $request->path(),
                ]);

                return response()->json([
                    'error' => 'Internal server error. Details siehe TMS-Log.',
                ], 500);
            }

            return response()->json([
                'error' => $e->getMessage(),
            ], $status);
        });
    })
    ->create();
