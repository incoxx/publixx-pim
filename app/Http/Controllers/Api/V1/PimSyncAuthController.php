<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PimSyncAuthController extends Controller
{
    /**
     * Client-Credentials-Flow: client_id + client_secret → Bearer Token.
     *
     * POST /api/v1/pim-sync/token
     */
    public function token(Request $request): JsonResponse
    {
        $request->validate([
            'client_id'     => 'required|string',
            'client_secret' => 'required|string',
        ]);

        // Timing-sichere Validierung: Immer Hash prüfen, auch wenn Client nicht existiert
        $client = ApiClient::where('client_id', $request->input('client_id'))->first();

        // Dummy-Hash prüfen wenn Client nicht existiert (verhindert Timing-Leak / Enumeration)
        $secretToCheck = $client?->client_secret ?? '$2y$12$dummyhashtopreventtimingleakattacksXXXXXXXXXXXXXXXXXXX';
        $isValid = Hash::check($request->input('client_secret'), $secretToCheck);

        if (! $client || ! $isValid) {
            return response()->json([
                'error'   => 'invalid_client',
                'message' => 'Ungültige Client-Credentials.',
            ], 401);
        }

        if (! $client->isUsable()) {
            return response()->json([
                'error'   => 'client_inactive',
                'message' => 'Der API-Client ist deaktiviert oder abgelaufen.',
            ], 403);
        }

        // Bestehende abgelaufene Tokens bereinigen (nicht alle löschen — erlaubt parallele Sessions)
        $client->tokens()
            ->where('expires_at', '<', now())
            ->delete();

        $expiresAt = now()->addHour();
        $token = $client->createToken('pim-sync', $client->scopes ?? [], $expiresAt);

        // last_used_at aktualisieren
        $client->updateQuietly(['last_used_at' => now()]);

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_in'   => 3600,
            'scopes'       => $client->scopes ?? [],
        ]);
    }
}
