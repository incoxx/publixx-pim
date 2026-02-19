<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return $this->problemResponse(
                title: 'Authentication Failed',
                detail: 'The provided credentials are incorrect.',
                status: Response::HTTP_UNAUTHORIZED,
                type: 'auth/invalid-credentials',
            );
        }

        if (! $user->is_active) {
            return $this->problemResponse(
                title: 'Account Deactivated',
                detail: 'Your account has been deactivated. Please contact an administrator.',
                status: Response::HTTP_FORBIDDEN,
                type: 'auth/account-deactivated',
            );
        }

        // Vorherige Tokens löschen (Single-Session)
        $user->tokens()->delete();

        $token = $user->createToken(
            name: 'pim-api',
            expiresAt: now()->addHours((int) config('sanctum.expiration_hours', 24)),
        );

        $user->update(['last_login_at' => now()]);

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
                'user' => new UserResource($user->load('roles.permissions')),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out.',
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken(
            name: 'pim-api',
            expiresAt: now()->addHours((int) config('sanctum.expiration_hours', 24)),
        );

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions');

        return response()->json([
            'data' => new UserResource($user),
        ], Response::HTTP_OK);
    }

    /**
     * RFC 7807 Problem Details Response.
     */
    private function problemResponse(
        string $title,
        string $detail,
        int $status,
        string $type,
        array $extra = [],
    ): JsonResponse {
        return response()->json(array_merge([
            'type' => "https://publixx-pim.local/problems/{$type}",
            'title' => $title,
            'detail' => $detail,
            'status' => $status,
        ], $extra), $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
