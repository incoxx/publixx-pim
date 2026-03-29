<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApiClientController extends Controller
{
    /**
     * Alle API-Clients auflisten.
     */
    public function index(): JsonResponse
    {
        $clients = ApiClient::orderBy('name')
            ->get()
            ->map(fn (ApiClient $client) => [
                'id'           => $client->id,
                'name'         => $client->name,
                'client_id'    => $client->client_id,
                'scopes'       => $client->scopes,
                'is_active'    => $client->is_active,
                'rate_limit'   => $client->rate_limit,
                'last_used_at' => $client->last_used_at?->toIso8601String(),
                'expires_at'   => $client->expires_at?->toIso8601String(),
                'created_at'   => $client->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $clients]);
    }

    /**
     * Neuen API-Client erstellen.
     * Das client_secret wird nur hier einmalig im Klartext zurückgegeben.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'scopes'     => 'sometimes|array',
            'scopes.*'   => 'string|in:' . implode(',', ApiClient::AVAILABLE_SCOPES),
            'rate_limit' => 'sometimes|integer|min:1|max:10000',
            'expires_at' => 'sometimes|nullable|date|after:now',
        ]);

        $credentials = ApiClient::generateCredentials();

        $client = ApiClient::create([
            'name'          => $validated['name'],
            'client_id'     => $credentials['client_id'],
            'client_secret' => Hash::make($credentials['client_secret']),
            'scopes'        => $validated['scopes'] ?? ApiClient::AVAILABLE_SCOPES,
            'rate_limit'    => $validated['rate_limit'] ?? 600,
            'expires_at'    => $validated['expires_at'] ?? null,
            'created_by'    => $request->user()?->id,
        ]);

        return response()->json([
            'data' => [
                'id'            => $client->id,
                'name'          => $client->name,
                'client_id'     => $client->client_id,
                'client_secret' => $credentials['client_secret'], // Einmalig im Klartext!
                'scopes'        => $client->scopes,
                'rate_limit'    => $client->rate_limit,
                'expires_at'    => $client->expires_at?->toIso8601String(),
            ],
            'warning' => 'Das client_secret wird nur einmalig angezeigt. Bitte sicher aufbewahren.',
        ], 201);
    }

    /**
     * Einzelnen API-Client anzeigen.
     */
    public function show(ApiClient $apiClient): JsonResponse
    {
        return response()->json([
            'data' => [
                'id'           => $apiClient->id,
                'name'         => $apiClient->name,
                'client_id'    => $apiClient->client_id,
                'scopes'       => $apiClient->scopes,
                'is_active'    => $apiClient->is_active,
                'rate_limit'   => $apiClient->rate_limit,
                'last_used_at' => $apiClient->last_used_at?->toIso8601String(),
                'expires_at'   => $apiClient->expires_at?->toIso8601String(),
                'created_at'   => $apiClient->created_at->toIso8601String(),
                'created_by'   => $apiClient->createdByUser?->name,
            ],
        ]);
    }

    /**
     * API-Client aktualisieren (Name, Scopes, Status, Rate-Limit).
     */
    public function update(Request $request, ApiClient $apiClient): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'scopes'     => 'sometimes|array',
            'scopes.*'   => 'string|in:' . implode(',', ApiClient::AVAILABLE_SCOPES),
            'is_active'  => 'sometimes|boolean',
            'rate_limit' => 'sometimes|integer|min:1|max:10000',
            'expires_at' => 'sometimes|nullable|date',
        ]);

        $apiClient->update($validated);

        // Wenn deaktiviert → alle aktiven Tokens widerrufen
        if (isset($validated['is_active']) && ! $validated['is_active']) {
            $apiClient->tokens()->delete();
        }

        return response()->json([
            'data' => [
                'id'         => $apiClient->id,
                'name'       => $apiClient->name,
                'client_id'  => $apiClient->client_id,
                'scopes'     => $apiClient->scopes,
                'is_active'  => $apiClient->is_active,
                'rate_limit' => $apiClient->rate_limit,
                'expires_at' => $apiClient->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * API-Client löschen.
     */
    public function destroy(ApiClient $apiClient): JsonResponse
    {
        // Alle Tokens widerrufen
        $apiClient->tokens()->delete();
        $apiClient->delete();

        return response()->json(null, 204);
    }

    /**
     * Neues client_secret generieren (das alte wird ungültig).
     */
    public function regenerateSecret(ApiClient $apiClient): JsonResponse
    {
        $credentials = ApiClient::generateCredentials();

        $apiClient->update([
            'client_secret' => Hash::make($credentials['client_secret']),
        ]);

        // Alle bestehenden Tokens widerrufen
        $apiClient->tokens()->delete();

        return response()->json([
            'data' => [
                'id'            => $apiClient->id,
                'name'          => $apiClient->name,
                'client_id'     => $apiClient->client_id,
                'client_secret' => $credentials['client_secret'], // Einmalig im Klartext!
            ],
            'warning' => 'Das neue client_secret wird nur einmalig angezeigt. Alle bestehenden Tokens wurden widerrufen.',
        ]);
    }
}
