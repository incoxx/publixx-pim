<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiClientScope
{
    /**
     * Prüft ob der authentifizierte ApiClient den nötigen Scope hat.
     *
     * Nutzung: ->middleware('api-client-scope:products:read')
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $user = $request->user();

        // Nur für ApiClient-Tokens prüfen (normale User-Tokens passieren lassen)
        if (! $user instanceof ApiClient) {
            return $next($request);
        }

        foreach ($scopes as $scope) {
            if (! $user->hasScope($scope)) {
                return response()->json([
                    'error'   => 'insufficient_scope',
                    'message' => "Fehlender Scope: {$scope}",
                    'required_scope' => $scope,
                ], 403);
            }
        }

        // last_used_at throttled aktualisieren (max. einmal pro Minute)
        if (! $user->last_used_at || $user->last_used_at->diffInMinutes(now()) >= 1) {
            $user->updateQuietly(['last_used_at' => now()]);
        }

        return $next($request);
    }
}
