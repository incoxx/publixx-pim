<?php

declare(strict_types=1);

namespace App\Services\Connectors\SalesforceCommerce;

use App\Models\ConnectorConnection;
use Illuminate\Support\Facades\Http;

class SalesforceCommerceAuthService
{
    private string $instanceUrl;
    private string $clientId;
    private string $clientSecret;

    private const TOKEN_URL = 'https://account.demandware.com/dwsso/oauth2/access_token';

    public function __construct()
    {
        $this->instanceUrl  = rtrim(config('connectors.salesforce_commerce.instance_url', ''), '/');
        $this->clientId     = config('connectors.salesforce_commerce.client_id', '');
        $this->clientSecret = config('connectors.salesforce_commerce.client_secret', '');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->instanceUrl) && ! empty($this->clientId) && ! empty($this->clientSecret);
    }

    /**
     * Authentifiziert über SFCC Account Manager (client_credentials).
     *
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null}
     */
    public function authenticate(string $clientId = '', string $clientSecret = '', string $instanceUrl = ''): array
    {
        $id = $clientId ?: $this->clientId;
        $secret = $clientSecret ?: $this->clientSecret;
        $url = rtrim($instanceUrl ?: $this->instanceUrl, '/');

        if (empty($url)) {
            throw new \RuntimeException('SFCC Instance-URL ist nicht konfiguriert. Bitte in den Verbindungseinstellungen oder .env hinterlegen.');
        }

        $response = Http::withBasicAuth($id, $secret)
            ->asForm()
            ->timeout(15)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'client_credentials',
            ]);

        $response->throw();
        $data = $response->json();

        if (!is_array($data) || empty($data['access_token'])) {
            throw new \RuntimeException('SFCC hat keine gueltige Token-Antwort zurueckgegeben. Bitte Client-ID, Secret und Instance-URL pruefen.');
        }

        return [
            'access_token'  => $data['access_token'],
            'refresh_token' => null,
            'expires_in'    => $data['expires_in'] ?? 1800,
        ];
    }

    /**
     * Erneuert den Token (bei SFCC: neuer client_credentials Request).
     */
    public function refreshAccessToken(ConnectorConnection $connection): void
    {
        $clientId = $connection->settings['client_id'] ?? $this->clientId;
        $clientSecret = $connection->settings['client_secret'] ?? $this->clientSecret;

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->timeout(15)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'client_credentials',
            ]);

        $response->throw();
        $data = $response->json();

        if (!is_array($data) || empty($data['access_token'])) {
            throw new \RuntimeException('SFCC Token-Refresh fehlgeschlagen: keine gueltige Antwort.');
        }

        $connection->update([
            'access_token'     => $data['access_token'],
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 1800),
        ]);
    }
}
