<?php

declare(strict_types=1);

namespace App\Services\Connectors\AnyPim;

use App\Models\ConnectorConnection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class AnyPimAuthService
{
    /**
     * Prüft ob der Connector grundsätzlich konfiguriert ist.
     * Bei anyPIM geschieht die Konfiguration pro Connection, nicht global.
     */
    public function isConfigured(): bool
    {
        // anyPIM-Connections werden dynamisch erstellt — immer "konfiguriert"
        return true;
    }

    /**
     * Authentifiziert gegen eine Remote-anyPIM-Instanz via Client Credentials.
     *
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null}
     */
    public function authenticate(string $clientId, string $clientSecret, string $remoteUrl): array
    {
        $url = rtrim($remoteUrl, '/');

        $response = Http::timeout(15)
            ->post("{$url}/api/v1/pim-sync/token", [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]);

        $response->throw();
        $data = $response->json();

        return [
            'access_token'  => $data['access_token'],
            'refresh_token' => null,
            'expires_in'    => $data['expires_in'] ?? 3600,
        ];
    }

    /**
     * Client-Secret verschlüsselt in Connection-Settings speichern.
     * Das settings-JSON-Feld ist nicht automatisch verschlüsselt,
     * daher wird das Secret manuell mit Laravel Crypt verschlüsselt.
     */
    public static function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    /**
     * Verschlüsseltes Client-Secret aus Connection-Settings auslesen.
     */
    public static function decryptSecret(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }

    /**
     * Token für eine bestehende Connection erneuern.
     */
    public function refreshAccessToken(ConnectorConnection $connection): void
    {
        $settings = $connection->settings ?? [];
        $remoteUrl = $settings['remote_url'] ?? '';
        $clientId = $settings['client_id'] ?? '';
        $encryptedSecret = $settings['client_secret_encrypted'] ?? '';

        if (empty($remoteUrl) || empty($clientId) || empty($encryptedSecret)) {
            throw new \RuntimeException('anyPIM-Verbindung ist nicht vollständig konfiguriert (remote_url, client_id, client_secret_encrypted).');
        }

        $clientSecret = self::decryptSecret($encryptedSecret);
        $result = $this->authenticate($clientId, $clientSecret, $remoteUrl);

        $connection->update([
            'access_token'     => $result['access_token'],
            'token_expires_at' => now()->addSeconds($result['expires_in'] ?? 3600),
        ]);
    }

    /**
     * Remote-URL aus Connection-Settings extrahieren.
     */
    public function getRemoteUrl(ConnectorConnection $connection): string
    {
        return rtrim($connection->settings['remote_url'] ?? '', '/');
    }
}
