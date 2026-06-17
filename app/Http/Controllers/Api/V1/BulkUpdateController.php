<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BulkUpdateRequest;
use App\Http\Resources\Api\V1\AttributeResource;
use App\Jobs\UpdateSearchIndex;
use App\Models\Attribute;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductRelation;
use App\Services\Inheritance\HierarchyInheritanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Massendatenpflege — bulk update multiple products at once.
 *
 * Supports both:
 * - product_ids: explicit list of product IDs (for small sets)
 * - filter: search/filter parameters (for large sets, server resolves IDs)
 *
 * POST /products/bulk-update/preview  → dry-run summary
 * PUT  /products/bulk-update          → execute changes
 */
class BulkUpdateController extends Controller
{
    /**
     * Return attributes that are common (intersection) across all given products'
     * master hierarchy assignments.
     */
    public function commonAttributes(Request $request, HierarchyInheritanceService $service): JsonResponse
    {
        $this->authorize('bulkUpdate', Product::class);

        $request->validate([
            'product_ids' => 'nullable|array|min:1|max:500',
            'product_ids.*' => 'uuid',
            'filter' => 'nullable|array',
            'search' => 'nullable|string|max:255',
            'exclude_ids' => 'nullable|array',
            'exclude_ids.*' => 'uuid',
        ]);

        // Für commonAttributes: bei Filter nur eine Stichprobe nehmen (max 200)
        $productIds = $request->input('product_ids');
        if (!$productIds && $request->has('filter')) {
            $productIds = $this->resolveFilterToIds($request->input('filter'))
                ->take(200)
                ->all();
        }

        if (empty($productIds)) {
            return response()->json(['data' => [], 'warning' => 'Keine Produkte gefunden.']);
        }

        $products = Product::whereIn('id', array_slice($productIds, 0, 200))->get();
        $warning = null;

        // Compute intersection of attribute IDs across all products
        $attributeSets = [];
        foreach ($products as $product) {
            $attrs = $service->getProductAttributes($product);
            if ($attrs->isEmpty() && !$product->master_hierarchy_node_id) {
                $warning = 'Eines oder mehrere Produkte haben keine Master-Hierarchie-Zuordnung.';
            }
            $attributeSets[] = $attrs->pluck('attribute_id')->unique()->all();
        }

        if (empty($attributeSets)) {
            return response()->json(['data' => [], 'warning' => 'Keine Produkte gefunden.']);
        }

        $commonIds = array_shift($attributeSets);
        foreach ($attributeSets as $set) {
            $commonIds = array_intersect($commonIds, $set);
        }
        $commonIds = array_values($commonIds);

        if (empty($commonIds)) {
            return response()->json(['data' => [], 'warning' => $warning]);
        }

        // Load full attribute models, excluding Composite parent attributes
        $query = Attribute::whereIn('id', $commonIds)
            ->where('data_type', '!=', 'Composite')
            ->with('valueList.entries')
            ->orderBy('name_de');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name_de', 'like', "%{$search}%")
                  ->orWhere('technical_name', 'like', "%{$search}%");
            });
        }

        if ($excludeIds = $request->input('exclude_ids')) {
            $query->whereNotIn('id', $excludeIds);
        }

        $attributes = $query->get();

        return response()->json([
            'data' => AttributeResource::collection($attributes),
            'warning' => $warning,
        ]);
    }

    public function preview(BulkUpdateRequest $request): JsonResponse
    {
        $this->authorize('bulkUpdate', Product::class);

        $productIds = $this->resolveProductIds($request);
        $operations = $request->validated('operations');

        $summary = [
            'total_products' => count($productIds),
        ];

        // Bei sehr vielen Produkten: vereinfachte Vorschau (nur Zähler)
        if (count($productIds) > 500) {
            $summary['note'] = 'Vereinfachte Vorschau für ' . count($productIds) . ' Produkte.';
        }

        // Für Preview immer nur eine Stichprobe verarbeiten (max 5000)
        $previewIds = count($productIds) > 5000 ? array_slice($productIds, 0, 5000) : $productIds;

        if (!empty($operations['attributes'])) {
            $result = $this->processAttributes($previewIds, $operations['attributes'], false);
            // Hochrechnen wenn Stichprobe
            if (count($previewIds) < count($productIds)) {
                $factor = count($productIds) / count($previewIds);
                foreach ($result['details'] as &$detail) {
                    $detail['would_update'] = (int) round($detail['would_update'] * $factor);
                    $detail['would_skip'] = (int) round($detail['would_skip'] * $factor);
                    $detail['estimated'] = true;
                }
                $result['updated'] = (int) round($result['updated'] * $factor);
                $result['skipped'] = (int) round($result['skipped'] * $factor);
            }
            $summary['attributes'] = $result;
        }

        if (!empty($operations['relations'])) {
            $summary['relations'] = $this->processRelations($previewIds, $operations['relations'], false);
        }

        if (!empty($operations['output_hierarchy'])) {
            $summary['output_hierarchy'] = $this->processOutputHierarchy($previewIds, $operations['output_hierarchy'], false);
        }

        if (!empty($operations['status'])) {
            // Status-Preview geht effizient über alle IDs
            $summary['status'] = $this->processStatus($productIds, $operations['status'], false);
        }

        if (array_key_exists('master_hierarchy_node_id', $operations)) {
            $summary['master_hierarchy'] = $this->processMasterHierarchy($productIds, $operations['master_hierarchy_node_id'], false);
        }

        if (array_key_exists('manufacturer_id', $operations)) {
            $summary['manufacturer'] = $this->processManufacturer($productIds, $operations['manufacturer_id'], false);
        }

        if (!empty($operations['media'])) {
            $summary['media'] = $this->processMedia($previewIds, $operations['media'], false);
        }

        return response()->json(['summary' => $summary]);
    }

    public function execute(BulkUpdateRequest $request): JsonResponse
    {
        $this->authorize('bulkUpdate', Product::class);

        $productIds = $this->resolveProductIds($request);
        $operations = $request->validated('operations');
        $totalProducts = count($productIds);

        Log::channel('import')->info("Bulk-Update gestartet: {$totalProducts} Produkte", [
            'operations' => array_keys($operations),
        ]);

        $results = [];
        $allChangedProductIds = [];

        // ── Chunked Verarbeitung für große Datenmengen ──
        $chunkSize = 5000;
        $chunks = array_chunk($productIds, $chunkSize);
        $chunkCount = count($chunks);

        foreach ($chunks as $chunkIndex => $chunk) {
            Log::channel('import')->info("Bulk-Update Chunk " . ($chunkIndex + 1) . "/{$chunkCount}: " . count($chunk) . " Produkte");

            DB::transaction(function () use ($chunk, $operations, &$results, &$allChangedProductIds) {
                if (!empty($operations['attributes'])) {
                    $r = $this->processAttributes($chunk, $operations['attributes'], true);
                    $this->mergeResults($results, 'attributes', $r);
                    if (!empty($r['changed_product_ids'])) {
                        array_push($allChangedProductIds, ...$r['changed_product_ids']);
                    }
                }

                if (!empty($operations['relations'])) {
                    $r = $this->processRelations($chunk, $operations['relations'], true);
                    $this->mergeResults($results, 'relations', $r);
                }

                if (!empty($operations['output_hierarchy'])) {
                    $r = $this->processOutputHierarchy($chunk, $operations['output_hierarchy'], true);
                    $this->mergeResults($results, 'output_hierarchy', $r);
                }

                if (!empty($operations['status'])) {
                    $r = $this->processStatus($chunk, $operations['status'], true);
                    $this->mergeResults($results, 'status', $r);
                    // Status changes affect search index (status field + visibility)
                    if (($r['would_change'] ?? 0) > 0) {
                        array_push($allChangedProductIds, ...$chunk);
                    }
                }

                if (array_key_exists('master_hierarchy_node_id', $operations)) {
                    $r = $this->processMasterHierarchy($chunk, $operations['master_hierarchy_node_id'], true);
                    $this->mergeResults($results, 'master_hierarchy', $r);
                    // Hierarchy changes affect search index (hierarchy_path field)
                    array_push($allChangedProductIds, ...$chunk);
                }

                if (array_key_exists('manufacturer_id', $operations)) {
                    $r = $this->processManufacturer($chunk, $operations['manufacturer_id'], true);
                    $this->mergeResults($results, 'manufacturer', $r);
                }

                if (!empty($operations['media'])) {
                    $r = $this->processMedia($chunk, $operations['media'], true);
                    $this->mergeResults($results, 'media', $r);
                    // Media changes affect search index (primary_image field)
                    array_push($allChangedProductIds, ...$chunk);
                }
            });
        }

        // ── Gebatchte Event-Verarbeitung ──
        // Statt 110K einzelne Events: gebatcht in Chunks invalidieren + indexieren
        if (!empty($allChangedProductIds)) {
            $changedAttrIds = collect($operations['attributes'] ?? [])->pluck('attribute_id')->unique()->toArray();
            $this->dispatchBulkInvalidation(array_unique($allChangedProductIds), $changedAttrIds);
        }

        // Remove internal tracking data from response
        if (isset($results['attributes'])) {
            unset($results['attributes']['changed_product_ids']);
        }

        Log::channel('import')->info("Bulk-Update abgeschlossen: {$totalProducts} Produkte", [
            'results' => $results,
        ]);

        return response()->json([
            'message' => 'Bulk update completed.',
            'results' => $results,
            'total_products' => $totalProducts,
        ]);
    }

    // ── Product ID Resolution ────────────────────────────

    /**
     * Resolve product IDs from either explicit list or filter parameters.
     */
    private function resolveProductIds(BulkUpdateRequest $request): array
    {
        if ($request->has('product_ids') && !empty($request->input('product_ids'))) {
            $ids = $request->input('product_ids');
            // Validate existence in bulk (faster than per-ID exists rule for large sets)
            if (count($ids) > 500) {
                $existingIds = Product::whereIn('id', $ids)->pluck('id')->all();
                return $existingIds;
            }
            return $ids;
        }

        if ($request->has('filter')) {
            return $this->resolveFilterToIds($request->input('filter'))->all();
        }

        return [];
    }

    /**
     * Build a product query from filter parameters and return IDs.
     */
    private function resolveFilterToIds(array $filter): \Illuminate\Support\Collection
    {
        $query = Product::query()->where('product_type_ref', 'product');

        if ($status = $filter['status'] ?? null) {
            $query->where('status', $status);
        }

        if (!empty($filter['product_type_ids'])) {
            $query->whereIn('product_type_id', $filter['product_type_ids']);
        }

        if (!empty($filter['manufacturer_ids'])) {
            $query->whereIn('manufacturer_id', $filter['manufacturer_ids']);
        }

        if ($search = $filter['search'] ?? null) {
            $mode = $filter['search_mode'] ?? 'like';
            // Simplified text search for ID resolution
            $query->where(function ($q) use ($search) {
                $q->whereExists(function ($sub) use ($search) {
                    $sub->select(DB::raw(1))
                        ->from('products_search_index')
                        ->whereColumn('products_search_index.product_id', 'products.id')
                        ->where('products_search_index.searchable_text', 'LIKE', "%{$search}%");
                })
                ->orWhere('products.sku', 'LIKE', "%{$search}%")
                ->orWhere('products.name', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($filter['category_ids'])) {
            $categoryIds = $filter['category_ids'];
            $includeDescendants = $filter['include_descendants'] ?? true;
            $hierarchyType = $filter['hierarchy_type'] ?? 'master';

            if ($includeDescendants) {
                $allNodeIds = DB::table('hierarchy_nodes')
                    ->whereIn('id', $categoryIds)
                    ->orWhereIn('parent_node_id', $categoryIds)
                    ->pluck('id')
                    ->all();
                // Simple 2-level descendant lookup
                if (!empty($allNodeIds)) {
                    $deeperIds = DB::table('hierarchy_nodes')
                        ->whereIn('parent_node_id', $allNodeIds)
                        ->pluck('id')
                        ->all();
                    $allNodeIds = array_unique(array_merge($allNodeIds, $deeperIds));
                }
                $categoryIds = $allNodeIds;
            }

            if ($hierarchyType === 'master') {
                $query->whereIn('master_hierarchy_node_id', $categoryIds);
            } else {
                $query->whereExists(function ($sub) use ($categoryIds) {
                    $sub->select(DB::raw(1))
                        ->from('output_hierarchy_product_assignments')
                        ->whereColumn('output_hierarchy_product_assignments.product_id', 'products.id')
                        ->whereIn('output_hierarchy_product_assignments.hierarchy_node_id', $categoryIds);
                });
            }
        }

        return $query->pluck('id');
    }

    // ── Bulk Invalidation ────────────────────────────────

    /**
     * Dispatch cache invalidation + search index updates in batches.
     * Replaces per-product event dispatch with efficient bulk processing.
     */
    private function dispatchBulkInvalidation(array $productIds, array $attributeIds): void
    {
        $count = count($productIds);
        Log::channel('import')->info("Bulk-Invalidierung: {$count} Produkte, " . count($attributeIds) . " Attribute");

        // 1. Cache invalidieren (gebatcht)
        foreach ($productIds as $pid) {
            try {
                Cache::tags(['product:' . $pid])->flush();
            } catch (\Throwable) {
                // ignore
            }
        }

        // 2. Varianten-IDs gebatcht laden (1 Query statt N)
        $variantIds = [];
        foreach (array_chunk($productIds, 10000) as $pidChunk) {
            $variants = Product::whereIn('parent_product_id', $pidChunk)->pluck('id')->all();
            array_push($variantIds, ...$variants);
        }

        // Varianten-Caches invalidieren
        foreach ($variantIds as $vid) {
            try {
                Cache::tags(['product:' . $vid])->flush();
            } catch (\Throwable) {
                // ignore
            }
        }

        // 3. Search-Index-Jobs gebatcht dispatchen
        $allIds = array_unique(array_merge($productIds, $variantIds));
        foreach ($allIds as $id) {
            dispatch(new UpdateSearchIndex($id));
        }

        // 4. Composite-Attribut-Neuberechnung per Event (gebatcht in Chunks)
        // Nur feuern wenn Attribute geändert wurden
        if (!empty($attributeIds)) {
            foreach (array_chunk($productIds, 1000) as $pidChunk) {
                foreach ($pidChunk as $pid) {
                    event(new \App\Events\AttributeValuesChanged($pid, $attributeIds));
                }
            }
        }

        Log::channel('import')->info("Bulk-Invalidierung abgeschlossen: {$count} Produkte + " . count($variantIds) . " Varianten");
    }

    // ── Result Merging ───────────────────────────────────

    /**
     * Merge chunk results into the overall results array.
     */
    private function mergeResults(array &$results, string $key, array $chunkResult): void
    {
        if (!isset($results[$key])) {
            $results[$key] = $chunkResult;
            return;
        }

        foreach ($chunkResult as $field => $value) {
            if ($field === 'details') {
                // Merge detail arrays for attributes
                if (!isset($results[$key]['details'])) {
                    $results[$key]['details'] = $value;
                } else {
                    foreach ($value as $i => $detail) {
                        if (isset($results[$key]['details'][$i])) {
                            $results[$key]['details'][$i]['would_update'] += $detail['would_update'];
                            $results[$key]['details'][$i]['would_skip'] += $detail['would_skip'];
                        } else {
                            $results[$key]['details'][$i] = $detail;
                        }
                    }
                }
            } elseif ($field === 'changed_product_ids') {
                if (!isset($results[$key]['changed_product_ids'])) {
                    $results[$key]['changed_product_ids'] = $value;
                } else {
                    $results[$key]['changed_product_ids'] = array_merge($results[$key]['changed_product_ids'], $value);
                }
            } elseif (is_numeric($value)) {
                $results[$key][$field] = ($results[$key][$field] ?? 0) + $value;
            }
        }
    }

    // ── Attributes ──────────────────────────────────────

    private function processAttributes(array $productIds, array $ops, bool $persist): array
    {
        $details = [];
        $totalUpdated = 0;
        $totalSkipped = 0;
        $changedProductIds = [];

        $attributes = Attribute::whereIn('id', collect($ops)->pluck('attribute_id'))->get()->keyBy('id');

        foreach ($ops as $op) {
            $attribute = $attributes->get($op['attribute_id']);
            if (!$attribute || $attribute->is_readonly) {
                continue;
            }

            $mode = $op['mode'];
            $language = $op['language'] ?? null;

            // Load existing values for this attribute across all products (chunked for large sets)
            $existing = collect();
            foreach (array_chunk($productIds, 10000) as $pidChunk) {
                $chunk = ProductAttributeValue::whereIn('product_id', $pidChunk)
                    ->where('attribute_id', $attribute->id)
                    ->where(function ($q) use ($language) {
                        if ($language) {
                            $q->where('language', $language);
                        } else {
                            $q->whereNull('language');
                        }
                    })
                    ->where('multiplied_index', 0)
                    ->get()
                    ->keyBy('product_id');
                $existing = $existing->merge($chunk);
            }

            // Determine which products should be updated
            $toUpdate = [];
            $wouldSkip = 0;

            foreach ($productIds as $pid) {
                $existingValue = $existing->get($pid);
                $hasValue = $existingValue !== null && $this->extractValue($existingValue) !== null;

                $shouldUpdate = match ($mode) {
                    'overwrite' => true,
                    'fill_empty' => !$hasValue,
                    'clear' => $hasValue,
                    default => false,
                };

                if ($shouldUpdate) {
                    $toUpdate[] = $pid;
                } else {
                    $wouldSkip++;
                }
            }

            $wouldUpdate = count($toUpdate);

            if ($persist && $wouldUpdate > 0) {
                if ($mode === 'clear') {
                    // Bulk delete (chunked)
                    foreach (array_chunk($toUpdate, 10000) as $deleteChunk) {
                        ProductAttributeValue::whereIn('product_id', $deleteChunk)
                            ->where('attribute_id', $attribute->id)
                            ->where(function ($q) use ($language) {
                                if ($language) {
                                    $q->where('language', $language);
                                } else {
                                    $q->whereNull('language');
                                }
                            })
                            ->where('multiplied_index', 0)
                            ->delete();
                    }
                } else {
                    // Bulk upsert
                    $valueData = $this->resolveValueColumns($attribute, $op['value']);
                    $rows = [];
                    foreach ($toUpdate as $pid) {
                        $rows[] = array_merge([
                            // upsert() umgeht Model-Events → UUID-PK explizit vergeben
                            'id' => (string) Str::uuid(),
                            'product_id' => $pid,
                            'attribute_id' => $attribute->id,
                            'language' => $language,
                            'multiplied_index' => 0,
                            'output_hierarchy_id' => null,
                            'is_inherited' => false,
                            'inherited_from_node_id' => null,
                            'inherited_from_product_id' => null,
                        ], $valueData);
                    }

                    // Upsert in chunks — Konflikt-Target muss dem Unique-Index
                    // pav_product_attr_lang_idx_oh_unique entsprechen (inkl. output_hierarchy_id,
                    // siehe Migration 2026_03_11_000001)
                    foreach (array_chunk($rows, 2000) as $chunk) {
                        ProductAttributeValue::upsert(
                            $chunk,
                            ['product_id', 'attribute_id', 'language', 'multiplied_index', 'output_hierarchy_id'],
                            array_merge(array_keys($valueData), ['is_inherited', 'inherited_from_node_id', 'inherited_from_product_id'])
                        );
                    }
                }

                // Track changed products for events
                foreach ($toUpdate as $pid) {
                    $changedProductIds[$pid] = true;
                }
            }

            $totalUpdated += $wouldUpdate;
            $totalSkipped += $wouldSkip;

            $details[] = [
                'attribute_name' => $attribute->name_de ?: $attribute->technical_name,
                'mode' => $mode,
                'would_update' => $wouldUpdate,
                'would_skip' => $wouldSkip,
            ];
        }

        $result = [
            'updated' => $totalUpdated,
            'skipped' => $totalSkipped,
            'details' => $details,
        ];

        if ($persist) {
            $result['changed_product_ids'] = array_keys($changedProductIds);
        }

        return $result;
    }

    // ── Relations ───────────────────────────────────────

    private function processRelations(array $productIds, array $ops, bool $persist): array
    {
        $added = 0;
        $removed = 0;
        $alreadyExists = 0;

        foreach ($ops as $op) {
            // Bulk-load existing relations (chunked)
            $existingSet = [];
            foreach (array_chunk($productIds, 10000) as $pidChunk) {
                $found = ProductRelation::whereIn('source_product_id', $pidChunk)
                    ->where('target_product_id', $op['target_product_id'])
                    ->where('relation_type_id', $op['relation_type_id'])
                    ->pluck('source_product_id')
                    ->all();
                foreach ($found as $id) {
                    $existingSet[$id] = true;
                }
            }

            if ($op['action'] === 'add') {
                $toAdd = [];
                foreach ($productIds as $pid) {
                    if (isset($existingSet[$pid])) {
                        $alreadyExists++;
                    } else {
                        $added++;
                        $toAdd[] = [
                            // insert() umgeht Model-Events → UUID-PK explizit vergeben
                            'id' => (string) Str::uuid(),
                            'source_product_id' => $pid,
                            'target_product_id' => $op['target_product_id'],
                            'relation_type_id' => $op['relation_type_id'],
                            'sort_order' => 0,
                        ];
                    }
                }
                if ($persist && !empty($toAdd)) {
                    foreach (array_chunk($toAdd, 2000) as $chunk) {
                        ProductRelation::insert($chunk);
                    }
                }
            } elseif ($op['action'] === 'remove') {
                $toRemove = [];
                foreach ($productIds as $pid) {
                    if (isset($existingSet[$pid])) {
                        $removed++;
                        $toRemove[] = $pid;
                    }
                }
                if ($persist && !empty($toRemove)) {
                    foreach (array_chunk($toRemove, 10000) as $chunk) {
                        ProductRelation::whereIn('source_product_id', $chunk)
                            ->where('target_product_id', $op['target_product_id'])
                            ->where('relation_type_id', $op['relation_type_id'])
                            ->delete();
                    }
                }
            }
        }

        return compact('added', 'removed', 'alreadyExists');
    }

    // ── Output Hierarchy ────────────────────────────────

    private function processOutputHierarchy(array $productIds, array $ops, bool $persist): array
    {
        $assigned = 0;
        $removed = 0;
        $alreadyAssigned = 0;

        foreach ($ops as $op) {
            $existingSet = [];
            foreach (array_chunk($productIds, 10000) as $pidChunk) {
                $found = OutputHierarchyProductAssignment::whereIn('product_id', $pidChunk)
                    ->where('hierarchy_node_id', $op['hierarchy_node_id'])
                    ->pluck('product_id')
                    ->all();
                foreach ($found as $id) {
                    $existingSet[$id] = true;
                }
            }

            if ($op['action'] === 'assign') {
                $toAssign = [];
                foreach ($productIds as $pid) {
                    if (isset($existingSet[$pid])) {
                        $alreadyAssigned++;
                    } else {
                        $assigned++;
                        $toAssign[] = [
                            // insert() umgeht Model-Events → UUID-PK explizit vergeben
                            'id' => (string) Str::uuid(),
                            'hierarchy_node_id' => $op['hierarchy_node_id'],
                            'product_id' => $pid,
                            'sort_order' => 0,
                        ];
                    }
                }
                if ($persist && !empty($toAssign)) {
                    foreach (array_chunk($toAssign, 2000) as $chunk) {
                        OutputHierarchyProductAssignment::insert($chunk);
                    }
                }
            } elseif ($op['action'] === 'remove') {
                $toRemove = [];
                foreach ($productIds as $pid) {
                    if (isset($existingSet[$pid])) {
                        $removed++;
                        $toRemove[] = $pid;
                    }
                }
                if ($persist && !empty($toRemove)) {
                    foreach (array_chunk($toRemove, 10000) as $chunk) {
                        OutputHierarchyProductAssignment::whereIn('product_id', $chunk)
                            ->where('hierarchy_node_id', $op['hierarchy_node_id'])
                            ->delete();
                    }
                }
            }
        }

        return compact('assigned', 'removed', 'alreadyAssigned');
    }

    // ── Status ──────────────────────────────────────────

    private function processStatus(array $productIds, string $status, bool $persist): array
    {
        // Chunked queries für große Sets
        $alreadyTarget = 0;
        foreach (array_chunk($productIds, 10000) as $pidChunk) {
            $alreadyTarget += Product::whereIn('id', $pidChunk)->where('status', $status)->count();
        }
        $wouldChange = count($productIds) - $alreadyTarget;

        if ($persist && $wouldChange > 0) {
            foreach (array_chunk($productIds, 10000) as $pidChunk) {
                Product::whereIn('id', $pidChunk)
                    ->where('status', '!=', $status)
                    ->update(['status' => $status]);
            }
        }

        return [
            'would_change' => $wouldChange,
            'already_target' => $alreadyTarget,
        ];
    }

    // ── Master Hierarchy ────────────────────────────────

    private function processMasterHierarchy(array $productIds, ?string $nodeId, bool $persist): array
    {
        $alreadyTarget = 0;

        if ($nodeId === null) {
            foreach (array_chunk($productIds, 10000) as $pidChunk) {
                $alreadyTarget += Product::whereIn('id', $pidChunk)->whereNull('master_hierarchy_node_id')->count();
            }
            $wouldChange = count($productIds) - $alreadyTarget;

            if ($persist && $wouldChange > 0) {
                foreach (array_chunk($productIds, 10000) as $pidChunk) {
                    Product::whereIn('id', $pidChunk)
                        ->whereNotNull('master_hierarchy_node_id')
                        ->update(['master_hierarchy_node_id' => null]);
                }
            }
        } else {
            foreach (array_chunk($productIds, 10000) as $pidChunk) {
                $alreadyTarget += Product::whereIn('id', $pidChunk)->where('master_hierarchy_node_id', $nodeId)->count();
            }
            $wouldChange = count($productIds) - $alreadyTarget;

            if ($persist && $wouldChange > 0) {
                foreach (array_chunk($productIds, 10000) as $pidChunk) {
                    Product::whereIn('id', $pidChunk)
                        ->where(function ($q) use ($nodeId) {
                            $q->where('master_hierarchy_node_id', '!=', $nodeId)
                              ->orWhereNull('master_hierarchy_node_id');
                        })
                        ->update(['master_hierarchy_node_id' => $nodeId]);
                }
            }
        }

        return [
            'would_change' => $wouldChange,
            'already_target' => $alreadyTarget,
        ];
    }

    // ── Manufacturer ───────────────────────────────────────

    private function processManufacturer(array $productIds, ?string $manufacturerId, bool $persist): array
    {
        $alreadyTarget = 0;

        if ($manufacturerId === null) {
            foreach (array_chunk($productIds, 10000) as $pidChunk) {
                $alreadyTarget += Product::whereIn('id', $pidChunk)->whereNull('manufacturer_id')->count();
            }
            $wouldChange = count($productIds) - $alreadyTarget;

            if ($persist && $wouldChange > 0) {
                foreach (array_chunk($productIds, 10000) as $pidChunk) {
                    Product::whereIn('id', $pidChunk)
                        ->whereNotNull('manufacturer_id')
                        ->update(['manufacturer_id' => null]);
                }
            }
        } else {
            foreach (array_chunk($productIds, 10000) as $pidChunk) {
                $alreadyTarget += Product::whereIn('id', $pidChunk)->where('manufacturer_id', $manufacturerId)->count();
            }
            $wouldChange = count($productIds) - $alreadyTarget;

            if ($persist && $wouldChange > 0) {
                foreach (array_chunk($productIds, 10000) as $pidChunk) {
                    Product::whereIn('id', $pidChunk)
                        ->where(function ($q) use ($manufacturerId) {
                            $q->where('manufacturer_id', '!=', $manufacturerId)
                              ->orWhereNull('manufacturer_id');
                        })
                        ->update(['manufacturer_id' => $manufacturerId]);
                }
            }
        }

        return [
            'would_change' => $wouldChange,
            'already_target' => $alreadyTarget,
        ];
    }

    // ── Media ───────────────────────────────────────────

    private function processMedia(array $productIds, array $ops, bool $persist): array
    {
        $assigned = 0;
        $removed = 0;
        $alreadyAssigned = 0;

        foreach ($ops as $op) {
            $existingSet = [];
            foreach (array_chunk($productIds, 10000) as $pidChunk) {
                $query = ProductMediaAssignment::whereIn('product_id', $pidChunk)
                    ->where('media_id', $op['media_id']);

                if (!empty($op['usage_type_id'])) {
                    $query->where('usage_type_id', $op['usage_type_id']);
                }

                $found = $query->pluck('product_id')->all();
                foreach ($found as $id) {
                    $existingSet[$id] = true;
                }
            }

            if ($op['action'] === 'assign') {
                $toAssign = [];
                foreach ($productIds as $pid) {
                    if (isset($existingSet[$pid])) {
                        $alreadyAssigned++;
                    } else {
                        $assigned++;
                        $toAssign[] = [
                            // insert() umgeht Model-Events → UUID-PK explizit vergeben
                            'id' => (string) Str::uuid(),
                            'product_id' => $pid,
                            'media_id' => $op['media_id'],
                            'usage_type_id' => $op['usage_type_id'] ?? null,
                            'sort_order' => 0,
                            'is_primary' => false,
                        ];
                    }
                }
                if ($persist && !empty($toAssign)) {
                    foreach (array_chunk($toAssign, 2000) as $chunk) {
                        ProductMediaAssignment::insert($chunk);
                    }
                }
            } elseif ($op['action'] === 'remove') {
                $toRemove = [];
                foreach ($productIds as $pid) {
                    if (isset($existingSet[$pid])) {
                        $removed++;
                        $toRemove[] = $pid;
                    }
                }
                if ($persist && !empty($toRemove)) {
                    foreach (array_chunk($toRemove, 10000) as $chunk) {
                        $deleteQuery = ProductMediaAssignment::whereIn('product_id', $chunk)
                            ->where('media_id', $op['media_id']);
                        if (!empty($op['usage_type_id'])) {
                            $deleteQuery->where('usage_type_id', $op['usage_type_id']);
                        }
                        $deleteQuery->delete();
                    }
                }
            }
        }

        return compact('assigned', 'removed', 'alreadyAssigned');
    }

    // ── Shared Helpers ──────────────────────────────────

    private function extractValue(ProductAttributeValue $pav): mixed
    {
        if ($pav->value_string !== null) return $pav->value_string;
        if ($pav->value_number !== null) return $pav->value_number;
        if ($pav->value_date !== null) return $pav->value_date;
        if ($pav->value_flag !== null) return (bool) $pav->value_flag;
        if ($pav->value_selection_id !== null) return $pav->value_selection_id;
        return null;
    }

    private function resolveValueColumns(Attribute $attribute, mixed $value): array
    {
        $columns = [
            'value_string' => null,
            'value_number' => null,
            'value_date' => null,
            'value_flag' => null,
            'value_selection_id' => null,
        ];

        return match ($attribute->data_type) {
            'String', 'RichText', 'Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink' => array_merge($columns, ['value_string' => $value !== null ? (string) $value : null]),
            'Number', 'Float' => array_merge($columns, ['value_number' => $value !== null ? (float) $value : null]),
            'Date' => array_merge($columns, ['value_date' => $value]),
            'Flag' => array_merge($columns, ['value_flag' => $value !== null ? (bool) $value : null]),
            'Selection', 'Dictionary' => array_merge($columns, [
                'value_string' => $value !== null ? (string) $value : null,
                'value_selection_id' => $value,
            ]),
            default => array_merge($columns, ['value_string' => $value !== null ? (string) $value : null]),
        };
    }
}
