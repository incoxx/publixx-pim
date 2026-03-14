<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    private ?array $decoded = null;

    private bool $resolved = false;

    /**
     * Check whether a specific enterprise module is active (licensed + not expired).
     */
    public function isModuleActive(string $module): bool
    {
        $license = $this->resolve();

        if ($license === null) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        $modules = $license['modules'] ?? [];

        return in_array($module, $modules, true);
    }

    /**
     * Get the list of all licensed module keys.
     */
    public function getActiveModules(): array
    {
        $license = $this->resolve();

        if ($license === null || $this->isExpired()) {
            return [];
        }

        return $license['modules'] ?? [];
    }

    /**
     * Return decoded license metadata (or null if no valid license).
     */
    public function getLicenseInfo(): ?array
    {
        $license = $this->resolve();

        if ($license === null) {
            return null;
        }

        return [
            'id' => $license['id'] ?? null,
            'customer' => $license['customer'] ?? null,
            'modules' => $license['modules'] ?? [],
            'max_users' => $license['max_users'] ?? 0,
            'expires_at' => $license['expires_at'] ?? null,
            'issued_at' => $license['issued_at'] ?? null,
            'valid' => ! $this->isExpired(),
        ];
    }

    /**
     * Check whether the license has expired.
     */
    public function isExpired(): bool
    {
        $license = $this->resolve();

        if ($license === null) {
            return true;
        }

        $expiresAt = $license['expires_at'] ?? null;

        if ($expiresAt === null) {
            return false; // perpetual license
        }

        return now()->startOfDay()->greaterThan($expiresAt);
    }

    /**
     * Days remaining until license expiration (null = perpetual or no license).
     */
    public function daysRemaining(): ?int
    {
        $license = $this->resolve();

        if ($license === null) {
            return null;
        }

        $expiresAt = $license['expires_at'] ?? null;

        if ($expiresAt === null) {
            return null;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($expiresAt, false));
    }

    /**
     * Maximum number of users allowed (0 = unlimited).
     */
    public function maxUsers(): int
    {
        $license = $this->resolve();

        return $license['max_users'] ?? 0;
    }

    /**
     * Validate and store a new license key.
     *
     * @return array{valid: bool, error?: string, license?: array}
     */
    public function activateLicense(string $licenseKey): array
    {
        $licenseKey = trim($licenseKey);

        if ($licenseKey === '') {
            // Clear the license
            Setting::setPayload('license', ['key' => null, 'activated_at' => null]);
            $this->resetCache();

            return ['valid' => true, 'license' => null];
        }

        $decoded = $this->decodeLicenseKey($licenseKey);

        if ($decoded === null) {
            return ['valid' => false, 'error' => 'Der Lizenzschlüssel ist ungültig.'];
        }

        // Store in settings
        Setting::setPayload('license', [
            'key' => $licenseKey,
            'activated_at' => now()->toIso8601String(),
        ]);

        $this->resetCache();

        return ['valid' => true, 'license' => $this->getLicenseInfo()];
    }

    /**
     * Build the module status overview (for the settings UI).
     */
    public function getModuleOverview(): array
    {
        $activeModules = $this->getActiveModules();
        $allModules = config('license.modules', []);

        $overview = [];

        foreach ($allModules as $key => $meta) {
            $overview[$key] = [
                'name' => $meta['name'],
                'description' => $meta['description'],
                'licensed' => in_array($key, $activeModules, true),
            ];
        }

        return $overview;
    }

    /**
     * Resolve and cache the license payload for the current request.
     */
    private function resolve(): ?array
    {
        if ($this->resolved) {
            return $this->decoded;
        }

        $this->resolved = true;
        $this->decoded = null;

        try {
            $settings = Setting::getPayload('license');
        } catch (\Throwable) {
            // Database might not be available during migrations
            return null;
        }

        $key = $settings['key'] ?? null;

        if (empty($key)) {
            return null;
        }

        $this->decoded = $this->decodeLicenseKey($key);

        return $this->decoded;
    }

    /**
     * Decode and verify a license key string.
     *
     * Format:  ANYPIM-<base64url-payload>.<base64url-signature>
     *
     * Uses sodium (Ed25519) signature verification when a public key is configured.
     * Falls back to unsigned JSON decode for development/testing.
     */
    private function decodeLicenseKey(string $key): ?array
    {
        // Strip the ANYPIM- prefix if present
        $key = preg_replace('/^ANYPIM-/', '', $key);

        $parts = explode('.', $key, 2);

        if (count($parts) < 1) {
            return null;
        }

        $payloadB64 = $parts[0];
        $signatureB64 = $parts[1] ?? null;

        $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'), true);

        if ($payloadJson === false) {
            return null;
        }

        $payload = json_decode($payloadJson, true);

        if (! is_array($payload)) {
            return null;
        }

        // Verify signature if public key is configured
        $publicKey = config('license.public_key');

        if (! empty($publicKey) && function_exists('sodium_crypto_sign_verify_detached')) {
            if ($signatureB64 === null) {
                Log::warning('LicenseService: License key has no signature but public key is configured.');

                return null;
            }

            $signature = base64_decode(strtr($signatureB64, '-_', '+/'), true);
            $pubKeyBin = base64_decode($publicKey, true);

            if ($signature === false || $pubKeyBin === false) {
                return null;
            }

            try {
                $valid = sodium_crypto_sign_verify_detached($signature, $payloadJson, $pubKeyBin);
            } catch (\SodiumException) {
                return null;
            }

            if (! $valid) {
                Log::warning('LicenseService: License signature verification failed.');

                return null;
            }
        }

        // Basic structure validation
        if (! isset($payload['modules']) || ! is_array($payload['modules'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Reset the in-memory cache (after activation/deactivation).
     */
    private function resetCache(): void
    {
        $this->decoded = null;
        $this->resolved = false;
    }
}
