<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\WriteAuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class SsoController extends Controller
{
    /**
     * GET /api/v1/auth/sso/config
     *
     * Returns SSO configuration for the frontend.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'data' => [
                'enabled' => (bool) config('services.azure.client_id') && config('sso.enabled', false),
                'provider' => 'azure',
                'label' => 'Mit Microsoft anmelden',
            ],
        ]);
    }

    /**
     * GET /api/v1/auth/sso/redirect
     *
     * Redirects to Azure AD for OIDC authentication.
     */
    public function redirect(Request $request): RedirectResponse|JsonResponse
    {
        if (! $this->isSsoEnabled()) {
            return $this->problemResponse(
                title: 'SSO Disabled',
                detail: 'Single Sign-On is not configured for this instance.',
                status: Response::HTTP_NOT_FOUND,
                type: 'auth/sso-disabled',
            );
        }

        $driver = Socialite::driver('azure');

        // Store frontend redirect URL in state
        if ($request->has('redirect')) {
            session(['sso_redirect' => $request->query('redirect')]);
        }

        return $driver->redirect();
    }

    /**
     * GET /api/v1/auth/sso/callback
     *
     * Handles the OIDC callback from Azure AD.
     */
    public function callback(Request $request): \Illuminate\Http\RedirectResponse|JsonResponse
    {
        if (! $this->isSsoEnabled()) {
            return $this->problemResponse(
                title: 'SSO Disabled',
                detail: 'Single Sign-On is not configured for this instance.',
                status: Response::HTTP_NOT_FOUND,
                type: 'auth/sso-disabled',
            );
        }

        try {
            $azureUser = Socialite::driver('azure')->user();
        } catch (\Exception $e) {
            $frontendUrl = config('app.frontend_url', config('app.url'));

            return redirect()->to($frontendUrl.'/login?sso_error='.urlencode('Authentifizierung fehlgeschlagen.'));
        }

        // Find or provision user
        $user = $this->findOrProvisionUser($azureUser);

        if (! $user) {
            $frontendUrl = config('app.frontend_url', config('app.url'));

            return redirect()->to($frontendUrl.'/login?sso_error='.urlencode('Kein Zugang. Bitte kontaktieren Sie den Administrator.'));
        }

        if (! $user->is_active) {
            $frontendUrl = config('app.frontend_url', config('app.url'));

            return redirect()->to($frontendUrl.'/login?sso_error='.urlencode('Ihr Konto wurde deaktiviert.'));
        }

        // Create Sanctum token (same pattern as AuthController::login)
        $user->tokens()->delete();

        $token = $user->createToken(
            name: 'pim-sso',
            expiresAt: now()->addHours((int) config('sanctum.expiration_hours', 24)),
        );

        $user->update(['last_login_at' => now()]);

        WriteAuditLog::dispatch(
            auditableType: User::class,
            auditableId: $user->id,
            action: 'logged_in',
            oldValues: null,
            newValues: ['method' => 'sso'],
            userId: $user->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        )->afterCommit();

        // Redirect to frontend with token
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $redirectPath = session()->pull('sso_redirect', '/products');

        $query = http_build_query([
            'sso_token' => $token->plainTextToken,
            'sso_expires' => $token->accessToken->expires_at?->toIso8601String(),
        ]);

        return redirect()->to($frontendUrl.'/login?'.$query.'&redirect='.urlencode($redirectPath));
    }

    /**
     * Find existing user or create via JIT provisioning.
     */
    private function findOrProvisionUser(object $azureUser): ?User
    {
        $email = $azureUser->getEmail();
        $azureId = $azureUser->getId();

        // 1. Match by SSO provider + ID
        $user = User::where('sso_provider', 'azure')
            ->where('sso_id', $azureId)
            ->first();

        if ($user) {
            // Update name if changed in Azure AD
            if ($azureUser->getName() && $user->name !== $azureUser->getName()) {
                $user->update(['name' => $azureUser->getName()]);
            }

            return $user;
        }

        // Sicherheit (Audit M-1): Verknuepfung/Provisionierung nur fuer verifizierte
        // E-Mails aus erlaubten Domains. Verhindert Account-Takeover ueber ein in
        // einem fremden Tenant angelegtes Konto mit der E-Mail eines PIM-Nutzers.
        if (! $email || ! $this->isEmailTrusted($azureUser, $email)) {
            return null;
        }

        // 2. Match by email (link existing user to SSO)
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update([
                'sso_provider' => 'azure',
                'sso_id' => $azureId,
            ]);

            return $user;
        }

        // 3. JIT Provisioning (if enabled)
        if (! config('sso.auto_provision', true)) {
            return null;
        }

        $user = User::create([
            'name' => $azureUser->getName() ?: explode('@', $email)[0],
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
            'sso_provider' => 'azure',
            'sso_id' => $azureId,
            'language' => 'de',
            'is_active' => true,
        ]);

        // Assign default role
        $defaultRole = config('sso.default_role', 'Viewer');
        $role = Role::where('name', $defaultRole)->where('guard_name', 'sanctum')->first();

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    /**
     * Prueft, ob eine SSO-E-Mail vertrauenswuerdig ist (Audit M-1):
     *  - ein explizit als unverifiziert markiertes email_verified-Claim wird abgelehnt,
     *  - bei konfigurierter Domain-Allowlist muss die Domain enthalten sein.
     * Ohne Allowlist (Default) greift nur die email_verified-Pruefung.
     */
    private function isEmailTrusted(object $azureUser, string $email): bool
    {
        $raw = (array) ($azureUser->user ?? []);
        if (array_key_exists('email_verified', $raw)
            && filter_var($raw['email_verified'], FILTER_VALIDATE_BOOLEAN) === false
        ) {
            return false;
        }

        $allowedDomains = array_map('strtolower', (array) config('sso.allowed_domains', []));
        if ($allowedDomains === []) {
            return true;
        }

        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        return $domain !== '' && in_array($domain, $allowedDomains, true);
    }

    private function isSsoEnabled(): bool
    {
        return (bool) config('services.azure.client_id') && config('sso.enabled', false);
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
