<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Product;
use App\Models\PublixxExportMapping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Publixx-specific dataset service for the Live-API.
 *
 * Provides endpoints for Publixx to fetch product datasets:
 * - All products for a mapping
 * - Single product for a mapping
 * - PQL-filtered products
 * - Webhook handling for Publixx callbacks
 */
class PublixxDatasetService
{
    public function __construct(
        protected ExportService $exportService,
        protected DatasetBuilder $datasetBuilder,
    ) {}

    /**
     * Get all datasets for a mapping.
     *
     * @param  string  $mappingId
     * @param  array   $queryParams  Optional filters (status, pagination)
     * @return array   ['data' => [...], 'meta' => [...]]
     */
    public function getAllDatasets(string $mappingId, array $queryParams = []): array
    {
        $mapping = $this->findMappingOrFail($mappingId);

        $filters = $this->buildMappingFilters($mapping, $queryParams);
        $perPage = (int) ($queryParams['per_page'] ?? 50);

        $query = $this->exportService->applyFilters(Product::query(), $filters);

        // Apply view filter from mapping
        if ($mapping->attribute_view_id) {
            $query->whereHas('attributeValues.attribute.viewAssignments', function (Builder $q) use ($mapping) {
                $q->where('attribute_view_id', $mapping->attribute_view_id);
            });
        }

        // Apply output hierarchy filter from mapping
        if ($mapping->output_hierarchy_id) {
            $query->whereHas('outputHierarchyAssignments', function (Builder $q) use ($mapping) {
                $q->whereHas('hierarchyNode', fn (Builder $nq) => $nq->where('hierarchy_id', $mapping->output_hierarchy_id));
            });
        }

        $paginated = $query->paginate($perPage);

        $datasets = [];
        foreach ($paginated->items() as $product) {
            $datasets[] = $this->exportService->exportProduct($product, $mapping);
        }

        return [
            'data' => $datasets,
            'meta' => [
                'mapping_id' => $mapping->id,
                'mapping_name' => $mapping->name,
                'format' => $mapping->flatten_mode,
                'languages' => $mapping->languages,
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ];
    }

    /**
     * Get a single dataset for a mapping + product.
     */
    public function getDataset(string $mappingId, string $productId): ?array
    {
        $mapping = $this->findMappingOrFail($mappingId);
        $product = Product::findOrFail($productId);

        return $this->exportService->exportProduct($product, $mapping);
    }

    /**
     * Get datasets filtered by PQL query.
     *
     * Delegates PQL execution to Agent 5's PqlExecutor.
     *
     * @param  string  $mappingId
     * @param  string  $pqlQuery   PQL query string
     * @param  array   $params     Additional parameters
     * @return array
     */
    public function getDatasetsByPql(string $mappingId, string $pqlQuery, array $params = []): array
    {
        $mapping = $this->findMappingOrFail($mappingId);

        // Delegate to PQL Agent (Agent 5)
        $productIds = $this->executePqlQuery($pqlQuery, $params);

        if (empty($productIds)) {
            return [
                'data' => [],
                'meta' => [
                    'mapping_id' => $mapping->id,
                    'pql' => $pqlQuery,
                    'total' => 0,
                ],
            ];
        }

        $products = Product::whereIn('id', $productIds)->get();
        $datasets = [];

        foreach ($products as $product) {
            $datasets[] = $this->exportService->exportProduct($product, $mapping);
        }

        return [
            'data' => $datasets,
            'meta' => [
                'mapping_id' => $mapping->id,
                'pql' => $pqlQuery,
                'total' => count($datasets),
            ],
        ];
    }

    /**
     * Handle incoming Publixx webhook.
     *
     * @param  array  $payload  Webhook payload from Publixx
     * @return array  Response data
     */
    public function handleWebhook(array $payload): array
    {
        $event = $payload['event'] ?? 'unknown';
        $mappingId = $payload['mapping_id'] ?? null;
        $productId = $payload['product_id'] ?? null;

        Log::info('Publixx webhook received', [
            'event' => $event,
            'mapping_id' => $mappingId,
            'product_id' => $productId,
        ]);

        return match ($event) {
            'dataset.request' => $this->handleDatasetRequest($mappingId, $productId),
            'dataset.invalidate' => $this->handleDatasetInvalidate($mappingId, $productId),
            'mapping.sync' => $this->handleMappingSync($mappingId),
            default => [
                'status' => 'ignored',
                'message' => "Unknown event: {$event}",
            ],
        };
    }

    // ─── Webhook Handlers ───────────────────────────────────────

    /**
     * Handle dataset request webhook.
     */
    protected function handleDatasetRequest(?string $mappingId, ?string $productId): array
    {
        if (!$mappingId || !$productId) {
            return ['status' => 'error', 'message' => 'Missing mapping_id or product_id'];
        }

        $dataset = $this->getDataset($mappingId, $productId);

        return [
            'status' => 'ok',
            'data' => $dataset,
        ];
    }

    /**
     * Handle dataset invalidation webhook.
     */
    protected function handleDatasetInvalidate(?string $mappingId, ?string $productId): array
    {
        if ($productId) {
            $this->exportService->invalidateProductCache($productId);
        } elseif ($mappingId) {
            $this->exportService->invalidateMappingCache($mappingId);
        }

        return ['status' => 'ok', 'message' => 'Cache invalidated'];
    }

    /**
     * Handle mapping sync webhook.
     */
    protected function handleMappingSync(?string $mappingId): array
    {
        if ($mappingId) {
            $this->exportService->invalidateMappingCache($mappingId);
        }

        return ['status' => 'ok', 'message' => 'Mapping cache cleared'];
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Find mapping or throw 404.
     */
    protected function findMappingOrFail(string $mappingId): PublixxExportMapping
    {
        return PublixxExportMapping::findOrFail($mappingId);
    }

    /**
     * Build filters from mapping defaults + query params.
     */
    protected function buildMappingFilters(PublixxExportMapping $mapping, array $queryParams): array
    {
        $filters = [];

        // Default: only active products
        $filters['status'] = $queryParams['filter']['status'] ?? 'active';

        // Delta export support
        if (isset($queryParams['filter']['updated_after'])) {
            $filters['updated_after'] = $queryParams['filter']['updated_after'];
        }

        return $filters;
    }

    /**
     * Execute PQL query via Agent 5's PqlExecutor.
     * Returns array of product IDs.
     */
    protected function executePqlQuery(string $pqlQuery, array $params = []): array
    {
        // Integration point for Agent 5's PqlExecutor
        if (app()->bound(\App\Services\Pql\PqlExecutor::class)) {
            try {
                $executor = app(\App\Services\Pql\PqlExecutor::class);
                $result = $executor->execute($pqlQuery, $params);
                return $result->pluck('id')->toArray();
            } catch (\Throwable $e) {
                Log::warning('PQL execution failed in export', [
                    'query' => $pqlQuery,
                    'error' => $e->getMessage(),
                ]);
                return [];
            }
        }

        Log::warning('PqlExecutor not bound, PQL queries unavailable');
        return [];
    }
}
