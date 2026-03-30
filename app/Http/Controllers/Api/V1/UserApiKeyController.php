<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class UserApiKeyController extends Controller
{
    /**
     * Eigene API-Keys auflisten.
     *
     * GET /api/v1/user/api-keys
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tokens = $user->tokens()
            ->where('token_type', 'api_key')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id'           => $token->id,
                'name'         => $token->name,
                'description'  => $token->description,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at'   => $token->expires_at?->toIso8601String(),
                'created_at'   => $token->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $tokens]);
    }

    /**
     * Neuen API-Key erstellen. Das Token wird nur einmal im Klartext angezeigt.
     *
     * POST /api/v1/user/api-keys
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'sometimes|nullable|string|max:500',
            'expires_at'  => 'sometimes|nullable|date|after:now',
        ]);

        $user = $request->user();
        $expiresAt = ! empty($validated['expires_at']) ? new \DateTimeImmutable($validated['expires_at']) : null;

        $token = $user->createToken(
            name: $validated['name'],
            abilities: ['*'],
            expiresAt: $expiresAt,
        );

        // token_type und description setzen
        $token->accessToken->forceFill([
            'token_type'  => 'api_key',
            'description' => $validated['description'] ?? null,
        ])->save();

        return response()->json([
            'data' => [
                'id'          => $token->accessToken->id,
                'name'        => $token->accessToken->name,
                'description' => $validated['description'] ?? null,
                'token'       => $token->plainTextToken, // Einmalig im Klartext!
                'expires_at'  => $expiresAt?->format('c'),
            ],
            'warning' => 'Der API-Key wird nur einmalig angezeigt. Bitte sicher aufbewahren.',
        ], 201);
    }

    /**
     * API-Key widerrufen.
     *
     * DELETE /api/v1/user/api-keys/{tokenId}
     */
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $user = $request->user();

        $token = $user->tokens()
            ->where('id', $tokenId)
            ->where('token_type', 'api_key')
            ->first();

        if (! $token) {
            return response()->json(['error' => 'not_found', 'message' => 'API-Key nicht gefunden.'], 404);
        }

        $token->delete();

        return response()->json(null, 204);
    }

    // =====================================================================
    // Admin: API-Keys anderer User verwalten
    // =====================================================================

    /**
     * Alle API-Keys aller User auflisten (Admin).
     *
     * GET /api/v1/admin/api-keys
     */
    public function adminIndex(Request $request): JsonResponse
    {
        if (! $request->user()?->hasRole('Admin')) {
            abort(403, 'Nur Administratoren.');
        }

        $tokens = PersonalAccessToken::where('token_type', 'api_key')
            ->with('tokenable')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id'           => $token->id,
                'name'         => $token->name,
                'description'  => $token->description,
                'user_name'    => $token->tokenable?->name,
                'user_email'   => $token->tokenable?->email,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at'   => $token->expires_at?->toIso8601String(),
                'created_at'   => $token->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $tokens]);
    }

    /**
     * API-Key für einen bestimmten User erstellen (Admin).
     *
     * POST /api/v1/admin/users/{userId}/api-keys
     */
    public function adminStore(Request $request, string $userId): JsonResponse
    {
        if (! $request->user()?->hasRole('Admin')) {
            abort(403, 'Nur Administratoren.');
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'sometimes|nullable|string|max:500',
            'expires_at'  => 'sometimes|nullable|date|after:now',
        ]);

        $user = User::findOrFail($userId);
        $expiresAt = ! empty($validated['expires_at']) ? new \DateTimeImmutable($validated['expires_at']) : null;

        $token = $user->createToken(
            name: $validated['name'],
            abilities: ['*'],
            expiresAt: $expiresAt,
        );

        $token->accessToken->forceFill([
            'token_type'  => 'api_key',
            'description' => $validated['description'] ?? null,
        ])->save();

        return response()->json([
            'data' => [
                'id'          => $token->accessToken->id,
                'name'        => $token->accessToken->name,
                'description' => $validated['description'] ?? null,
                'user_name'   => $user->name,
                'token'       => $token->plainTextToken,
                'expires_at'  => $expiresAt?->format('c'),
            ],
            'warning' => 'Der API-Key wird nur einmalig angezeigt.',
        ], 201);
    }

    /**
     * Beliebigen API-Key löschen (Admin).
     *
     * DELETE /api/v1/admin/api-keys/{tokenId}
     */
    public function adminDestroy(Request $request, int $tokenId): JsonResponse
    {
        if (! $request->user()?->hasRole('Admin')) {
            abort(403, 'Nur Administratoren.');
        }

        $token = PersonalAccessToken::where('id', $tokenId)
            ->where('token_type', 'api_key')
            ->first();

        if (! $token) {
            return response()->json(['error' => 'not_found', 'message' => 'API-Key nicht gefunden.'], 404);
        }

        $token->delete();

        return response()->json(null, 204);
    }
}
