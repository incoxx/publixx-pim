<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentifiziert PimSync-Requests via ApiClient Sanctum-Token.
 *
 * Ersetzt auth:sanctum für PimSync-Endpoints, da der Standard-Sanctum-Guard
 * nur den users-Provider kennt und ApiClient-Tokens nicht auflösen kann.
 */
class AuthenticatePimSync
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error'   => 'unauthenticated',
                'message' => 'Bearer Token erforderlich.',
            ], 401);
        }

        // Sanctum-Token auflösen
        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken) {
            return response()->json([
                'error'   => 'invalid_token',
                'message' => 'Ungültiger oder abgelaufener Token.',
            ], 401);
        }

        // Prüfen ob der Token zu einem ApiClient gehört
        if ($accessToken->tokenable_type !== ApiClient::class) {
            return response()->json([
                'error'   => 'invalid_token_type',
                'message' => 'Dieser Token ist kein PimSync API-Client Token.',
            ], 403);
        }

        // Token-Ablauf prüfen
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            $accessToken->delete();
            return response()->json([
                'error'   => 'token_expired',
                'message' => 'Token abgelaufen. Bitte neuen Token anfordern.',
            ], 401);
        }

        // ApiClient laden und Verfügbarkeit prüfen
        $apiClient = $accessToken->tokenable;

        if (! $apiClient || ! $apiClient instanceof ApiClient || ! $apiClient->isUsable()) {
            return response()->json([
                'error'   => 'client_inactive',
                'message' => 'API-Client ist deaktiviert oder abgelaufen.',
            ], 403);
        }

        // ApiClient als authentifizierten User setzen (für Scope-Middleware etc.)
        $request->setUserResolver(fn () => $apiClient);

        // last_used_at auf Token aktualisieren
        $accessToken->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
