<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LicenseGeneratorController extends Controller
{
    /**
     * POST /api/v1/license-generator/validate-key
     *
     * Validate an Ed25519 private key and return the corresponding public key.
     * The private key is NOT stored.
     */
    public function validateKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'private_key' => ['required', 'string'],
        ]);

        $privateKeyBin = base64_decode($validated['private_key'], true);

        if ($privateKeyBin === false || strlen($privateKeyBin) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            return response()->json([
                'valid' => false,
                'error' => 'Ungültiger Private Key. Erwartet: base64-kodierter Ed25519 Secret Key (64 Bytes).',
            ], 422);
        }

        try {
            $publicKeyBin = sodium_crypto_sign_publickey_from_secretkey($privateKeyBin);
        } catch (\SodiumException) {
            return response()->json([
                'valid' => false,
                'error' => 'Der Key konnte nicht verarbeitet werden.',
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'public_key' => base64_encode($publicKeyBin),
        ]);
    }

    /**
     * POST /api/v1/license-generator/generate
     *
     * Generate a signed license key. The private key is used for signing only
     * and is NOT stored anywhere.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'private_key' => ['required', 'string'],
            'customer' => ['required', 'string', 'max:255'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string'],
            'max_users' => ['integer', 'min:0'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $privateKeyBin = base64_decode($validated['private_key'], true);

        if ($privateKeyBin === false || strlen($privateKeyBin) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            return response()->json([
                'valid' => false,
                'error' => 'Ungültiger Private Key.',
            ], 422);
        }

        // Validate modules against known list
        $knownModules = array_keys(config('license.modules', []));
        $requestedModules = $validated['modules'];
        $invalidModules = array_diff($requestedModules, $knownModules);

        if (! empty($invalidModules)) {
            return response()->json([
                'valid' => false,
                'error' => 'Unbekannte Module: '.implode(', ', $invalidModules),
            ], 422);
        }

        $payload = [
            'id' => (string) Str::uuid(),
            'customer' => $validated['customer'],
            'modules' => array_values($requestedModules),
            'max_users' => (int) ($validated['max_users'] ?? 0),
            'expires_at' => $validated['expires_at'] ?? null,
            'issued_at' => now()->toIso8601String(),
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $signature = sodium_crypto_sign_detached($payloadJson, $privateKeyBin);
        } catch (\SodiumException) {
            return response()->json([
                'valid' => false,
                'error' => 'Signierung fehlgeschlagen.',
            ], 422);
        }

        $b64Payload = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
        $b64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        $licenseKey = "ANYPIM-{$b64Payload}.{$b64Signature}";

        return response()->json([
            'license_key' => $licenseKey,
            'payload' => $payload,
        ]);
    }

    /**
     * POST /api/v1/license-generator/generate-keypair
     *
     * Generate a new Ed25519 keypair.
     */
    public function generateKeypair(): JsonResponse
    {
        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);

        return response()->json([
            'private_key' => base64_encode($secretKey),
            'public_key' => base64_encode($publicKey),
        ]);
    }
}
