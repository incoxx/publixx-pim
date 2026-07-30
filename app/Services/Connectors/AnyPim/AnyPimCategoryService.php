<?php

declare(strict_types=1);

namespace App\Services\Connectors\AnyPim;

use Illuminate\Http\Client\PendingRequest;

class AnyPimCategoryService
{
    /**
     * Schema-Info der Remote-Instanz abrufen.
     *
     * Wirft absichtlich bei Fehlschlag (keine eigene try/catch-Behandlung) — die
     * Aufrufer (AnyPimConnector::testConnection(), PimSync-Konsolenkommando im
     * Dry-Run) fangen den Fehler bereits selbst ab, um eine gescheiterte
     * Verbindung als solche zu melden statt als "OK" mit leerem Schema.
     */
    public function fetchRemoteSchema(PendingRequest $http, string $remoteUrl): array
    {
        $response = $http->timeout(15)
            ->get("{$remoteUrl}/api/v1/pim-sync/schema");

        $response->throw();

        return $response->json();
    }
}
