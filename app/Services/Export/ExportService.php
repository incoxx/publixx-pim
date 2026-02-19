<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Product;
use App\Models\PublixxExportMapping;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Orchestrates the export pipeline:
 * 1. Filter products (status, hierarchy, attributes, delta)
 * 2. Load required relations based on mapping
 * 3. Resolve attribute values (via Inheritance Agent)
 * 4. Build datasets via DatasetBuilder
 * 5. Cache results
 */
class ExportService
{
    protected const CACHE_TTL = 1800; // 30 minutes
    protected const CACHE_PREFIX = 'export:mapping:';

    public function __construct(
        protected DatasetBuilder $datasetBuilder,
        protected MappingResolver $mappingResolver,
    ) {}

    // ─── Single Product Export ──────────────────────────────────

    /**
     * Export a single product with a given mapping.
     */
    public function exportProduct(Product $product, PublixxExportMapping $mapping, array $options = []): array
    {
        $cacheKey = $this->cacheKey($mapping->id, $product->id);

        if (!($options['skipCache'] ?? false)) {
            $cached = Cache::tags($this->cacheTags($mapping->id, $product->id))->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $product = $this->loadProductRelations($product, $mapping);
        $options = $this->resolveAttributeValues($product, $mapping, $options);

        $dataset = $this->datasetBuilder->build($product, $mapping, $options);

        Cache::tags($this->cacheTags($mapping->id, $product->id))
            ->put($cacheKey, $dataset, self::CACHE_TTL);

        return $dataset;
    }

    /**
     * Export a single product by ID.
     */
    public function exportProductById(string $productId, ?PublixxExportMapping $mapping = null, array $options = []): ?array
    {
        $product = Product::find($productId);
        if (!$product) {
            return null;
        }

        $mapping = $mapping ?? $this->getDefaultMapping();
        if (!$mapping) {
            return null;
        }

        return $this->exportProduct($product, $mapping, $options);
    }

    // ─── Filtered Export ────────────────────────────────────────

    /**
     * Export products matching filters.
     *
     * @param  array  $filters  Request filter parameters
     * @param  PublixxExportMapping|null $mapping
     * @param  array  $options  Additional options (perPage, page, etc.)
     * @return array  ['data' => [...], 'meta' => [...]]
     */
    public function exportFiltered(array $filters, ?PublixxExportMapping $mapping = null, array $options = []): array
    {
        $mapping = $mapping ?? $this->getDefaultMapping();
        if (!$mapping) {
            return ['data' => [], 'meta' => ['total' => 0]];
        }

        $query = $this->applyFilters(Product::query(), $filters);
        $perPage = $options['perPage'] ?? 50;

        /** @var LengthAwarePaginator $paginated */
        $paginated = $query->paginate($perPage);

        $datasets = [];
        foreach ($paginated->items() as $product) {
            $datasets[] = $this->exportProduct($product, $mapping, $options);
        }

        return [
            'data' => $datasets,
            'meta' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ];
    }

    /**
     * Bulk export products by filter criteria (POST endpoint).
     */
    public function exportBulk(array $filters, ?PublixxExportMapping $mapping = null, array $options = []): array
    {
        $mapping = $mapping ?? $this->getDefaultMapping();
        if (!$mapping) {
            return [];
        }

        $query = $this->applyFilters(Product::query(), $filters);

        // Chunk to avoid memory exhaustion
        $datasets = [];
        $query->chunk(100, function ($products) use ($mapping, $options, &$datasets) {
            foreach ($products as $product) {
                $datasets[] = $this->exportProduct($product, $mapping, $options);
            }
        });

        return $datasets;
    }

    // ─── Filter Logic ───────────────────────────────────────────

    /**
     * Apply export filters to a product query.
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        // Status filter
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Hierarchy node filter
        if (isset($filters['hierarchy_node'])) {
            $query->where('master_hierarchy_node_id', $filters['hierarchy_node']);
        }

        // Hierarchy path filter (prefix match on node path)
        if (isset($filters['hierarchy_path'])) {
            $path = $filters['hierarchy_path'];
            $query->whereHas('masterHierarchyNode', function (Builder $q) use ($path) {
                $q->where('path', 'LIKE', "%{$path}%");
            });
        }

        // Attribute filters: filter[attribute.gewicht][gte]=5
        if (isset($filters['attribute']) && is_array($filters['attribute'])) {
            foreach ($filters['attribute'] as $attrName => $condition) {
                $query->whereHas('attributeValues', function (Builder $q) use ($attrName, $condition) {
                    $q->whereHas('attribute', fn (Builder $aq) => $aq->where('technical_name', $attrName));

                    if (is_array($condition)) {
                        foreach ($condition as $operator => $value) {
                            $column = is_numeric($value) ? 'value_number' : 'value_string';
                            $sqlOp = match ($operator) {
                                'gte' => '>=',
                                'lte' => '<=',
                                'gt' => '>',
                                'lt' => '<',
                                'contains' => 'LIKE',
                                default => '=',
                            };
                            $sqlValue = $operator === 'contains' ? "%{$value}%" : $value;
                            $q->where($column, $sqlOp, $sqlValue);
                        }
                    } else {
                        // Exact match
                        $q->where(function (Builder $vq) use ($condition) {
                            $vq->where('value_string', $condition)
                               ->orWhere('value_number', $condition);
                        });
                    }
                });
            }
        }

        // View filter: only attributes in a specific attribute view
        if (isset($filters['view'])) {
            // This filter is applied at the mapping level, not query level
            // Store it for later use by the DatasetBuilder
        }

        // Delta export: filter[updated_after]
        if (isset($filters['updated_after'])) {
            $query->where('updated_at', '>=', $filters['updated_after']);
        }

        return $query;
    }

    // ─── Relation Loading ───────────────────────────────────────

    /**
     * Eagerly load product relations based on mapping config.
     */
    protected function loadProductRelations(Product $product, PublixxExportMapping $mapping): Product
    {
        $relations = ['attributeValues.attribute', 'attributeValues.unit', 'attributeValues.valueListEntry'];

        if ($mapping->include_media) {
            $relations[] = 'mediaAssignments.media';
        }

        if ($mapping->include_prices) {
            $relations[] = 'prices.priceType';
        }

        if ($mapping->include_variants) {
            $relations[] = 'variants.prices';
        }

        if ($mapping->include_relations) {
            $relations[] = 'outgoingRelations.relationType';
            $relations[] = 'outgoingRelations.targetProduct.mediaAssignments.media';
        }

        // Load hierarchy for path resolution
        $relations[] = 'masterHierarchyNode';

        $product->loadMissing($relations);

        return $product;
    }

    /**
     * Resolve attribute values using the AttributeValueResolver (Agent 4 dependency).
     *
     * If the resolver is not yet available, falls back to direct attribute values.
     */
    protected function resolveAttributeValues(Product $product, PublixxExportMapping $mapping, array $options): array
    {
        // Integration point for Agent 4's AttributeValueResolver
        // The resolver considers inheritance chain:
        // 1. Own value on product
        // 2. Value from parent product (variants)
        // 3. Value from hierarchy
        // 4. Empty

        if (app()->bound(\App\Services\Inheritance\AttributeValueResolver::class)) {
            try {
                $resolver = app(\App\Services\Inheritance\AttributeValueResolver::class);
                $resolved = $resolver->resolveAll($product);
                $options['resolvedValues'] = $resolved;
            } catch (\Throwable $e) {
                // Fallback: use direct attribute values
                report($e);
            }
        }

        // Collect hierarchy attribute assignments for group resolution
        if ($product->masterHierarchyNode) {
            $options['attributeAssignments'] = $product->masterHierarchyNode
                ->attributeAssignments()
                ->with('attribute')
                ->orderBy('collection_sort')
                ->orderBy('attribute_sort')
                ->get();
        }

        return $options;
    }

    // ─── Cache Management ───────────────────────────────────────

    /**
     * Build cache key for a product/mapping combination.
     */
    public function cacheKey(string $mappingId, string $productId): string
    {
        return self::CACHE_PREFIX . $mappingId . ':product:' . $productId;
    }

    /**
     * Cache tags for a product/mapping combination.
     */
    protected function cacheTags(string $mappingId, string $productId): array
    {
        return [
            'export',
            "export:mapping:{$mappingId}",
            "product:{$productId}",
        ];
    }

    /**
     * Invalidate cache for a specific product across all mappings.
     */
    public function invalidateProductCache(string $productId): void
    {
        Cache::tags(["product:{$productId}"])->flush();
    }

    /**
     * Invalidate cache for a specific mapping.
     */
    public function invalidateMappingCache(string $mappingId): void
    {
        Cache::tags(["export:mapping:{$mappingId}"])->flush();
    }

    /**
     * Invalidate all export caches.
     */
    public function invalidateAll(): void
    {
        Cache::tags(['export'])->flush();
    }

    // ─── Default Mapping ────────────────────────────────────────

    /**
     * Get the default mapping or first available.
     */
    protected function getDefaultMapping(): ?PublixxExportMapping
    {
        return PublixxExportMapping::first();
    }
}
