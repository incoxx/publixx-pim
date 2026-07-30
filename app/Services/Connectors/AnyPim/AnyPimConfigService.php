<?php

declare(strict_types=1);

namespace App\Services\Connectors\AnyPim;

use App\Services\Import\JsonFormatImporter;
use App\Services\Import\JsonImportResult;
use Illuminate\Http\Client\PendingRequest;

class AnyPimConfigService
{
    /**
     * Konfiguration (Produkttypen, Attribute, Wertelisten, Hersteller, ...) von
     * der Remote-Instanz holen und lokal importieren.
     *
     * Im Gegensatz zu Produkten ist Konfigurationsdaten typischerweise klein genug
     * fuer einen einzelnen Request (kein Paging noetig).
     */
    public function pullConfig(
        PendingRequest $http,
        string $remoteUrl,
        array $sections,
        JsonFormatImporter $importer,
    ): JsonImportResult {
        $response = $http->timeout(120)
            ->post("{$remoteUrl}/api/v1/json-export", [
                'sections' => $sections,
                'inline' => true,
            ]);

        $response->throw();

        $data = $response->json();

        return $importer->importData($data);
    }
}
