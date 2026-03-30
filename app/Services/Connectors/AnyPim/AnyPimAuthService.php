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
        return true;
    }

    /**
     * Authentifiziert gegen eine Remote-anyPIM-Instanz.
     * Der API-Key wird direkt als Bearer Token gesendet — kein Token-Exchange nötig.
     *
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null}
     */
    public function authenticate(string $apiKey, string $ignored = '', string $remoteUrl = ''): array
    {
        // Verbindung testen: Schema-Endpoint mit API-Key als Bearer aufrufen
        $url = rtrim($remoteUrl, '/');

        $response = Http::withToken($apiKey)
            ->timeout(15)
            ->get("{$url}/api/v1/pim-sync/schema");

        $response->throw();

        // API-Key ist der Token — kein Exchange nötig
        return [
            'access_token'  => $apiKey,
            'refresh_token' => null,
            'expires_in'    => null, // API-Key hat eigenes Ablaufdatum
        ];
    }

    /**
     * Token erneuern. Bei API-Keys nicht nötig — Key bleibt gültig bis zum Ablauf.
     */
    public function refreshAccessToken(ConnectorConnection $connection): void
    {
        // API-Keys benötigen keinen Refresh.
        // Der gespeicherte access_token IST der API-Key.
    }

    /**
     * API-Key verschlüsselt in Connection-Settings speichern.
     */
    public static function encryptApiKey(string $apiKey): string
    {
        return Crypt::encryptString($apiKey);
    }

    /**
     * Verschlüsselten API-Key aus Connection-Settings auslesen.
     */
    public static function decryptApiKey(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }

    /**
     * Remote-URL aus Connection-Settings extrahieren.
     */
    public function getRemoteUrl(ConnectorConnection $connection): string
    {
        return rtrim($connection->settings['remote_url'] ?? '', '/');
    }
}
