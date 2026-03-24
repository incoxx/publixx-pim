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

        Log::info('LicenseService: Aktivierungsversuch.', [
            'key_length' => strlen($licenseKey),
            'has_prefix' => str_starts_with($licenseKey, 'ANYPIM-'),
            'has_dot' => str_contains($licenseKey, '.'),
            'public_key_configured' => ! empty(config('license.public_key')),
        ]);

        $decoded = $this->decodeLicenseKey($licenseKey);

        if ($decoded === null) {
            return ['valid' => false, 'error' => 'Der Lizenzschlüssel ist ungültig. Details im Server-Log.'];
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
            $settings = null;
        }

        $key = $settings['key'] ?? null;

        // Fallback to .env-based license key (survives migrate:fresh, DB resets, etc.)
        if (empty($key)) {
            $key = config('license.key');
        }

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
        // Whitespace entfernen (Zeilenumbrüche, Leerzeichen aus Copy-Paste)
        $key = preg_replace('/\s+/', '', $key);

        // Strip the ANYPIM- prefix if present
        $key = preg_replace('/^ANYPIM-/', '', $key);

        $parts = explode('.', $key, 2);

        if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
            Log::warning('LicenseService: Ungültiges Key-Format (erwartet: payload.signature).', [
                'parts_count' => count($parts),
                'key_length' => strlen($key),
                'key_prefix' => substr($key, 0, 20) . '...',
            ]);

            return null;
        }

        $payloadB64 = $parts[0];
        $signatureB64 = $parts[1];

        // Base64url → Standard-Base64, Padding wiederherstellen
        $payloadB64Std = strtr($payloadB64, '-_', '+/');
        $payloadB64Std = match (strlen($payloadB64Std) % 4) {
            2 => $payloadB64Std . '==',
            3 => $payloadB64Std . '=',
            default => $payloadB64Std,
        };

        $payloadJson = base64_decode($payloadB64Std, true);

        if ($payloadJson === false) {
            Log::warning('LicenseService: Base64-Dekodierung des Payloads fehlgeschlagen.', [
                'payload_b64_length' => strlen($payloadB64),
            ]);

            return null;
        }

        $payload = json_decode($payloadJson, true);

        if (! is_array($payload)) {
            Log::warning('LicenseService: JSON-Dekodierung des Payloads fehlgeschlagen.', [
                'json_error' => json_last_error_msg(),
                'payload_length' => strlen($payloadJson),
                'payload_preview' => substr($payloadJson, 0, 100) . (strlen($payloadJson) > 100 ? '...' : ''),
            ]);

            return null;
        }

        // Verify signature if public key is configured
        $publicKey = config('license.public_key');

        if (! empty($publicKey) && function_exists('sodium_crypto_sign_verify_detached')) {
            // Signatur-Base64url → Standard-Base64, Padding wiederherstellen
            $sigB64Std = strtr($signatureB64, '-_', '+/');
            $sigB64Std = match (strlen($sigB64Std) % 4) {
                2 => $sigB64Std . '==',
                3 => $sigB64Std . '=',
                default => $sigB64Std,
            };

            $signature = base64_decode($sigB64Std, true);
            $pubKeyBin = base64_decode($publicKey, true);

            if ($signature === false || $pubKeyBin === false) {
                Log::warning('LicenseService: Base64-Dekodierung von Signatur oder Public Key fehlgeschlagen.', [
                    'sig_decode_ok' => $signature !== false,
                    'pubkey_decode_ok' => $pubKeyBin !== false,
                    'pubkey_length' => $pubKeyBin !== false ? strlen($pubKeyBin) : 0,
                ]);

                return null;
            }

            if (strlen($pubKeyBin) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                Log::warning('LicenseService: Public Key hat falsche Länge.', [
                    'expected' => SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES,
                    'actual' => strlen($pubKeyBin),
                ]);

                return null;
            }

            try {
                $valid = sodium_crypto_sign_verify_detached($signature, $payloadJson, $pubKeyBin);
            } catch (\SodiumException $e) {
                Log::warning('LicenseService: Sodium-Exception bei Signaturprüfung.', [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }

            if (! $valid) {
                Log::warning('LicenseService: Signaturprüfung fehlgeschlagen — Public Key stimmt nicht mit Signatur überein.');

                return null;
            }
        }

        // Basic structure validation
        if (! isset($payload['modules']) || ! is_array($payload['modules'])) {
            Log::warning('LicenseService: Payload enthält kein gültiges modules-Array.', [
                'keys' => array_keys($payload),
            ]);

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
