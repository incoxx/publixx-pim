<?php

declare(strict_types=1);

namespace App\Services\Connectors\AnyPim;

use App\Exceptions\ImportCancelledException;
use App\Services\PimSync\PimSyncExportService;
use App\Services\PimSync\PimSyncImportService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AnyPimProductService
{
    public function __construct(
        private readonly PimSyncExportService $exportService,
        private readonly PimSyncImportService $importService,
    ) {}

    /**
     * Produkte zur Remote-Instanz pushen.
     *
     * Nutzt denselben Cache-basierten Abbruch-Mechanismus wie der JSON-Import
     * (Cache-Key "bmecat_import_cancel_{$importId}"), wenn eine Import-ID
     * mitgegeben wird — damit der bestehende /json-import/cancel-Endpoint
     * auch hierfür funktioniert.
     *
     * @param  callable(int $pushed, int $total, int $page): void|null  $progressCallback
     * @param  callable(): void|null  $heartbeatCallback
     * @return array{pushed: int, errors: int, details: array}
     */
    public function pushProducts(
        PendingRequest $http,
        string $remoteUrl,
        array $filters = [],
        array $options = [],
        ?string $importId = null,
        ?callable $progressCallback = null,
        ?callable $heartbeatCallback = null,
    ): array {
        $stats = ['pushed' => 0, 'errors' => 0, 'details' => []];
        $perPage = 100;
        $page = 1;
        $total = isset($filters['skus']) ? count($filters['skus']) : 0;

        do {
            if ($heartbeatCallback) {
                ($heartbeatCallback)();
            }

            if ($importId && Cache::get("bmecat_import_cancel_{$importId}")) {
                Cache::forget("bmecat_import_cancel_{$importId}");
                throw new ImportCancelledException($importId);
            }

            $filters['per_page'] = $perPage;
            $filters['page'] = $page;

            $result = $this->exportService->exportProducts($filters);
            $products = $result['products'];

            if (empty($products)) {
                break;
            }

            // Batch an Remote senden
            try {
                $response = $http->timeout(120)
                    ->post("{$remoteUrl}/api/v1/pim-sync/products", [
                        'products'            => $products,
                        'conflict_resolution' => $options['conflict_resolution'] ?? 'remote_wins',
                        'sync_attributes'     => $options['sync_attributes'] ?? true,
                        'sync_prices'         => $options['sync_prices'] ?? true,
                        'sync_media'          => $options['sync_media'] ?? true,
                    ]);

                $response->throw();
                $remoteStats = $response->json('stats', []);
                $stats['pushed'] += ($remoteStats['created'] ?? 0) + ($remoteStats['updated'] ?? 0);
                $stats['errors'] += $remoteStats['errors'] ?? 0;

                if (! empty($remoteStats['details'])) {
                    $stats['details'] = array_merge($stats['details'], $remoteStats['details']);
                }
            } catch (\Throwable $e) {
                $stats['errors'] += count($products);
                $stats['details'][] = ['error' => "Batch-Push Seite {$page} fehlgeschlagen: " . $e->getMessage()];
                Log::channel('connectors')->error('anyPIM Push fehlgeschlagen', [
                    'page'  => $page,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($progressCallback) {
                ($progressCallback)($stats['pushed'] + $stats['errors'], $total, $page);
            }

            $page++;
        } while (count($products) === $perPage);

        return $stats;
    }

    /**
     * Produkte von Remote-Instanz pullen.
     *
     * @return array{pulled: int, errors: int, details: array}
     */
    public function pullProducts(
        PendingRequest $http,
        string $remoteUrl,
        array $filters = [],
        array $options = [],
    ): array {
        $stats = ['pulled' => 0, 'errors' => 0, 'details' => []];
        $perPage = 100;
        $page = 1;

        do {
            try {
                $response = $http->timeout(60)
                    ->get("{$remoteUrl}/api/v1/pim-sync/products", array_merge($filters, [
                        'per_page' => $perPage,
                        'page'     => $page,
                    ]));

                $response->throw();
                $products = $response->json('products', []);

                if (empty($products)) {
                    break;
                }

                // Lokal importieren
                $importStats = $this->importService->importProducts($products, $options);
                $stats['pulled'] += ($importStats['created'] ?? 0) + ($importStats['updated'] ?? 0);
                $stats['errors'] += $importStats['errors'] ?? 0;

                if (! empty($importStats['details'])) {
                    $stats['details'] = array_merge($stats['details'], $importStats['details']);
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['details'][] = ['error' => "Pull Seite {$page} fehlgeschlagen: " . $e->getMessage()];
                Log::channel('connectors')->error('anyPIM Pull fehlgeschlagen', [
                    'page'  => $page,
                    'error' => $e->getMessage(),
                ]);
                break;
            }

            $page++;
        } while (count($products) === $perPage);

        return $stats;
    }

    /**
     * Delta-Sync: Nur geänderte Produkte pushen.
     *
     * @return array{pushed: int, skipped: int, errors: int}
     */
    public function deltaPush(
        PendingRequest $http,
        string $remoteUrl,
        array $filters = [],
        array $options = [],
    ): array {
        // 1. Remote-Checksums holen
        $response = $http->timeout(60)
            ->get("{$remoteUrl}/api/v1/pim-sync/checksums", $filters);

        $response->throw();
        $remoteChecksums = $response->json('checksums', []);

        // 2. Lokale Checksums berechnen
        $localChecksums = $this->exportService->calculateChecksums($filters);

        // 3. Differenz ermitteln
        $changedSkus = [];
        foreach ($localChecksums as $sku => $data) {
            $remoteChecksum = $remoteChecksums[$sku]['checksum'] ?? null;
            if ($remoteChecksum !== $data['checksum']) {
                $changedSkus[] = $sku;
            }
        }

        if (empty($changedSkus)) {
            return ['pushed' => 0, 'skipped' => count($localChecksums), 'errors' => 0];
        }

        // 4. Nur geänderte Produkte pushen
        $pushResult = $this->pushProducts($http, $remoteUrl, ['skus' => $changedSkus], $options);

        return [
            'pushed'  => $pushResult['pushed'],
            'skipped' => count($localChecksums) - count($changedSkus),
            'errors'  => $pushResult['errors'],
        ];
    }

    /**
     * Delta-Sync: Nur geänderte Produkte pullen.
     *
     * @return array{pulled: int, skipped: int, errors: int}
     */
    public function deltaPull(
        PendingRequest $http,
        string $remoteUrl,
        array $filters = [],
        array $options = [],
    ): array {
        // 1. Remote-Checksums holen
        $response = $http->timeout(60)
            ->get("{$remoteUrl}/api/v1/pim-sync/checksums", $filters);

        $response->throw();
        $remoteChecksums = $response->json('checksums', []);

        // 2. Lokale Checksums berechnen
        $localChecksums = $this->exportService->calculateChecksums($filters);

        // 3. Differenz ermitteln (Remote hat neuere Daten)
        $changedSkus = [];
        foreach ($remoteChecksums as $sku => $data) {
            $localChecksum = $localChecksums[$sku]['checksum'] ?? null;
            if ($localChecksum !== $data['checksum']) {
                $changedSkus[] = $sku;
            }
        }

        if (empty($changedSkus)) {
            return ['pulled' => 0, 'skipped' => count($remoteChecksums), 'errors' => 0];
        }

        // 4. Nur geänderte Produkte pullen
        $pullResult = $this->pullProducts($http, $remoteUrl, ['skus' => $changedSkus], $options);

        return [
            'pulled'  => $pullResult['pulled'],
            'skipped' => count($remoteChecksums) - count($changedSkus),
            'errors'  => $pullResult['errors'],
        ];
    }
}
