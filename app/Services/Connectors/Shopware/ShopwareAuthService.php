<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopware;

use App\Models\ConnectorConnection;
use Illuminate\Support\Facades\Http;

class ShopwareAuthService
{
    private string $shopUrl;
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->shopUrl      = rtrim(config('connectors.shopware.shop_url', ''), '/');
        $this->clientId     = config('connectors.shopware.client_id', '');
        $this->clientSecret = config('connectors.shopware.client_secret', '');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->shopUrl) && ! empty($this->clientId) && ! empty($this->clientSecret);
    }

    /**
     * Authentifiziert über Shopware Integration (client_credentials).
     *
     * @param  string  $clientId      Client-ID (oder aus Config)
     * @param  string  $clientSecret  Client-Secret (oder aus Config)
     * @param  string  $shopUrl       Shop-URL (oder aus Config)
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null}
     */
    public function authenticate(string $clientId = '', string $clientSecret = '', string $shopUrl = ''): array
    {
        $id = $clientId ?: $this->clientId;
        $secret = $clientSecret ?: $this->clientSecret;
        $url = rtrim($shopUrl ?: $this->shopUrl, '/');

        if (empty($url)) {
            throw new \RuntimeException('Shopware Shop-URL ist nicht konfiguriert. Bitte in den Verbindungseinstellungen oder .env hinterlegen.');
        }

        $response = Http::timeout(15)
            ->post("{$url}/api/oauth/token", [
                'grant_type'    => 'client_credentials',
                'client_id'     => $id,
                'client_secret' => $secret,
            ]);

        $response->throw();
        $data = $response->json();

        if (!is_array($data) || empty($data['access_token'])) {
            throw new \RuntimeException('Shopware hat keine gültige Token-Antwort zurückgegeben. Bitte Client-ID, Secret und Shop-URL prüfen.');
        }

        return [
            'access_token'  => $data['access_token'],
            'refresh_token' => null,
            'expires_in'    => $data['expires_in'] ?? 600,
        ];
    }

    /**
     * Erneuert den Token (bei Shopware: neuer client_credentials Request).
     */
    public function refreshAccessToken(ConnectorConnection $connection): void
    {
        $clientId = $connection->settings['client_id'] ?? $this->clientId;
        $clientSecret = $connection->settings['client_secret'] ?? $this->clientSecret;
        $shopUrl = rtrim($connection->settings['shop_url'] ?? $this->shopUrl, '/');

        $response = Http::timeout(15)
            ->post("{$shopUrl}/api/oauth/token", [
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]);

        $response->throw();
        $data = $response->json();

        if (!is_array($data) || empty($data['access_token'])) {
            throw new \RuntimeException('Shopware Token-Refresh fehlgeschlagen: keine gültige Antwort.');
        }

        $connection->update([
            'access_token'     => $data['access_token'],
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 600),
        ]);
    }
}
