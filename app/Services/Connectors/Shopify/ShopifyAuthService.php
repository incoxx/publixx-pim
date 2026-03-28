<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopify;

use App\Models\ConnectorConnection;
use Illuminate\Support\Facades\Http;

class ShopifyAuthService
{
    private string $shopUrl;
    private string $accessToken;
    private string $clientId;
    private string $clientSecret;

    private const API_VERSION = '2025-10';

    public function __construct()
    {
        $this->shopUrl      = rtrim(config('connectors.shopify.shop_url', ''), '/');
        $this->accessToken  = config('connectors.shopify.access_token', '');
        $this->clientId     = config('connectors.shopify.client_id', '');
        $this->clientSecret = config('connectors.shopify.client_secret', '');
    }

    public function isConfigured(): bool
    {
        if (empty($this->shopUrl)) {
            return false;
        }
        // Entweder statischer Token ODER Client Credentials müssen vorhanden sein
        return ! empty($this->accessToken) || (! empty($this->clientId) && ! empty($this->clientSecret));
    }

    /**
     * Authentifiziert gegen Shopify.
     *
     * Unterstützt zwei Methoden:
     * 1. Legacy (vor 2026): Statischer Admin API Access Token (shpat_...)
     * 2. Neu (ab 2026): Client Credentials → kurzlebiger Token (24h)
     *
     * @param  string  $accessTokenOrClientId  Access Token ODER Client ID
     * @param  string  $clientSecretOrEmpty     Client Secret (leer bei statischem Token)
     * @param  string  $shopUrl                 Shop-URL
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null}
     */
    public function authenticate(string $accessTokenOrClientId = '', string $clientSecretOrEmpty = '', string $shopUrl = ''): array
    {
        $url = rtrim($shopUrl ?: $this->shopUrl, '/');

        if (empty($url)) {
            throw new \RuntimeException('Shopify Shop-URL ist nicht konfiguriert. Bitte in den Verbindungseinstellungen oder .env hinterlegen.');
        }

        // Modus erkennen: Wenn Client Secret vorhanden → Client Credentials Flow
        if (! empty($clientSecretOrEmpty)) {
            return $this->authenticateWithClientCredentials(
                $accessTokenOrClientId ?: $this->clientId,
                $clientSecretOrEmpty,
                $url,
            );
        }

        // Legacy: Statischer Access Token
        return $this->authenticateWithAccessToken(
            $accessTokenOrClientId ?: $this->accessToken,
            $url,
        );
    }

    /**
     * Legacy: Validiert einen statischen Access Token (vor 2026).
     */
    private function authenticateWithAccessToken(string $token, string $shopUrl): array
    {
        if (empty($token)) {
            throw new \RuntimeException('Shopify Access Token ist nicht konfiguriert.');
        }

        $response = Http::timeout(15)
            ->withHeaders(['X-Shopify-Access-Token' => $token])
            ->acceptJson()
            ->get("{$shopUrl}/admin/api/" . self::API_VERSION . '/shop.json');

        $response->throw();

        return [
            'access_token'  => $token,
            'refresh_token' => null,
            'expires_in'    => null, // Statische Tokens laufen nicht ab
        ];
    }

    /**
     * Neu (ab 2026): Client Credentials → kurzlebiger Token (24h).
     *
     * POST https://{shop}.myshopify.com/admin/oauth/access_token
     * mit client_id + client_secret → access_token (24h gültig)
     */
    private function authenticateWithClientCredentials(string $clientId, string $clientSecret, string $shopUrl): array
    {
        if (empty($clientId) || empty($clientSecret)) {
            throw new \RuntimeException('Shopify Client ID und Client Secret sind nicht konfiguriert.');
        }

        $response = Http::timeout(15)
            ->post("{$shopUrl}/admin/oauth/access_token", [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]);

        $response->throw();
        $data = $response->json();

        $expiresIn = $data['expires_in'] ?? 86400; // Standard: 24 Stunden

        // Token validieren
        $token = $data['access_token'] ?? '';
        if (empty($token)) {
            throw new \RuntimeException('Shopify hat keinen Access Token zurückgegeben.');
        }

        // Validierung via Shop-Info
        Http::timeout(15)
            ->withHeaders(['X-Shopify-Access-Token' => $token])
            ->acceptJson()
            ->get("{$shopUrl}/admin/api/" . self::API_VERSION . '/shop.json')
            ->throw();

        return [
            'access_token'  => $token,
            'refresh_token' => null,
            'expires_in'    => $expiresIn,
        ];
    }

    /**
     * Erneuert den Token bei Client Credentials (kurzlebige Tokens).
     * Bei statischen Tokens: No-op.
     */
    public function refreshAccessToken(ConnectorConnection $connection): void
    {
        $settings = $connection->settings ?? [];
        $clientId = $settings['client_id'] ?? $this->clientId;
        $clientSecret = $settings['client_secret'] ?? $this->clientSecret;
        $shopUrl = rtrim($settings['shop_url'] ?? $this->shopUrl, '/');

        // Kein Client Secret → statischer Token, kein Refresh nötig
        if (empty($clientSecret)) {
            return;
        }

        // Client Credentials: neuen Token holen
        $result = $this->authenticateWithClientCredentials($clientId, $clientSecret, $shopUrl);

        $connection->update([
            'access_token'     => $result['access_token'],
            'token_expires_at' => $result['expires_in']
                ? now()->addSeconds($result['expires_in'])
                : null,
        ]);
    }

    /**
     * Gibt die API-Version zurück.
     */
    public static function apiVersion(): string
    {
        return self::API_VERSION;
    }
}
