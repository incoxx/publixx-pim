<?php

declare(strict_types=1);

namespace App\Services\Connectors\AnyPim;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;

class AnyPimCategoryService
{
    /**
     * Kategorien von der Remote-Instanz holen.
     *
     * @return array Hierarchie-Knoten der Remote-Instanz
     */
    public function pullCategories(PendingRequest $http, string $remoteUrl, ?string $hierarchyId = null): array
    {
        $params = [];
        if ($hierarchyId) {
            $params['hierarchy_id'] = $hierarchyId;
        }

        try {
            $response = $http->timeout(30)
                ->get("{$remoteUrl}/api/v1/pim-sync/categories", $params);

            $response->throw();

            return $response->json('data', []);
        } catch (\Throwable $e) {
            Log::channel('connectors')->error("anyPIM Kategorie-Pull fehlgeschlagen: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Schema-Info der Remote-Instanz abrufen.
     */
    public function fetchRemoteSchema(PendingRequest $http, string $remoteUrl): array
    {
        try {
            $response = $http->timeout(15)
                ->get("{$remoteUrl}/api/v1/pim-sync/schema");

            $response->throw();

            return $response->json();
        } catch (\Throwable $e) {
            Log::channel('connectors')->error("anyPIM Schema-Abruf fehlgeschlagen: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Attribut-Definitionen der Remote-Instanz abrufen.
     */
    public function fetchRemoteAttributes(PendingRequest $http, string $remoteUrl): array
    {
        try {
            $response = $http->timeout(30)
                ->get("{$remoteUrl}/api/v1/pim-sync/attributes");

            $response->throw();

            return $response->json('data', []);
        } catch (\Throwable $e) {
            Log::channel('connectors')->error("anyPIM Attribut-Abruf fehlgeschlagen: {$e->getMessage()}");
            return [];
        }
    }
}
