<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Jobs\WriteAuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Account-bezogener Lockout gegen (verteiltes) Credential-Stuffing (Audit M-5):
        // Der globale throttle.pim:auth begrenzt nur pro IP. Hier zusaetzlich max.
        // 5 Fehlversuche pro E-Mail in 15 Minuten — unabhaengig von der Angreifer-IP.
        $throttleKey = 'login:'.Str::lower((string) $request->validated('email'));

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return $this->problemResponse(
                title: 'Too Many Attempts',
                detail: "Zu viele Anmeldeversuche. Bitte in {$seconds} Sekunden erneut versuchen.",
                status: Response::HTTP_TOO_MANY_REQUESTS,
                type: 'auth/too-many-attempts',
            );
        }

        $user = User::where('email', $request->validated('email'))->first();

        if (! $user) {
            RateLimiter::hit($throttleKey, 900);

            return $this->problemResponse(
                title: 'Authentication Failed',
                detail: 'The provided credentials are incorrect.',
                status: Response::HTTP_UNAUTHORIZED,
                type: 'auth/invalid-credentials',
            );
        }

        // SSO-only users must use the SSO login flow
        if ($user->isSsoUser()) {
            return $this->problemResponse(
                title: 'SSO Required',
                detail: 'Dieses Konto verwendet Single Sign-On. Bitte melden Sie sich über den SSO-Button an.',
                status: Response::HTTP_FORBIDDEN,
                type: 'auth/sso-required',
            );
        }

        if (! Hash::check($request->validated('password'), $user->password ?? '')) {
            RateLimiter::hit($throttleKey, 900);

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

        // Erfolgreiche Anmeldung: Fehlversuchs-Zaehler zuruecksetzen.
        RateLimiter::clear($throttleKey);

        // Vorherige Session-Tokens löschen (Single-Session), API-Keys behalten
        $user->tokens()->where('token_type', '!=', 'api_key')->delete();

        $token = $user->createToken(
            name: 'pim-api',
            expiresAt: now()->addHours((int) config('sanctum.expiration_hours', 24)),
        );

        $user->update(['last_login_at' => now()]);

        WriteAuditLog::dispatch(
            auditableType: User::class,
            auditableId: $user->id,
            action: 'logged_in',
            oldValues: null,
            newValues: ['method' => 'credentials'],
            userId: $user->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        )->afterCommit();

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
                'user' => new UserResource($user->load(['roles.permissions', 'roles.entityRestrictions', 'roles.tabPermissions'])),
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
        $user = $request->user()->load(['roles.permissions', 'roles.entityRestrictions', 'roles.tabPermissions']);

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
            'type' => "https://anypim.local/problems/{$type}",
            'title' => $title,
            'detail' => $detail,
            'status' => $status,
        ], $extra), $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
