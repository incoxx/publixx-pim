<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BulkUpdateRequest;
use App\Models\Attribute;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductRelation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Massendatenpflege — bulk update multiple products at once.
 *
 * POST /products/bulk-update/preview  → dry-run summary
 * PUT  /products/bulk-update          → execute changes
 */
class BulkUpdateController extends Controller
{
    public function preview(BulkUpdateRequest $request): JsonResponse
    {
        $this->authorize('update', Product::class);

        $productIds = $request->validated('product_ids');
        $operations = $request->validated('operations');

        $summary = [
            'total_products' => count($productIds),
        ];

        if (!empty($operations['attributes'])) {
            $summary['attributes'] = $this->processAttributes($productIds, $operations['attributes'], false);
        }

        if (!empty($operations['relations'])) {
            $summary['relations'] = $this->processRelations($productIds, $operations['relations'], false);
        }

        if (!empty($operations['output_hierarchy'])) {
            $summary['output_hierarchy'] = $this->processOutputHierarchy($productIds, $operations['output_hierarchy'], false);
        }

        if (!empty($operations['status'])) {
            $summary['status'] = $this->processStatus($productIds, $operations['status'], false);
        }

        if (array_key_exists('master_hierarchy_node_id', $operations)) {
            $summary['master_hierarchy'] = $this->processMasterHierarchy($productIds, $operations['master_hierarchy_node_id'], false);
        }

        if (!empty($operations['media'])) {
            $summary['media'] = $this->processMedia($productIds, $operations['media'], false);
        }

        return response()->json(['summary' => $summary]);
    }

    public function execute(BulkUpdateRequest $request): JsonResponse
    {
        $this->authorize('update', Product::class);

        $productIds = $request->validated('product_ids');
        $operations = $request->validated('operations');

        $results = [];

        DB::transaction(function () use ($productIds, $operations, &$results) {
            if (!empty($operations['attributes'])) {
                $results['attributes'] = $this->processAttributes($productIds, $operations['attributes'], true);
            }

            if (!empty($operations['relations'])) {
                $results['relations'] = $this->processRelations($productIds, $operations['relations'], true);
            }

            if (!empty($operations['output_hierarchy'])) {
                $results['output_hierarchy'] = $this->processOutputHierarchy($productIds, $operations['output_hierarchy'], true);
            }

            if (!empty($operations['status'])) {
                $results['status'] = $this->processStatus($productIds, $operations['status'], true);
            }

            if (array_key_exists('master_hierarchy_node_id', $operations)) {
                $results['master_hierarchy'] = $this->processMasterHierarchy($productIds, $operations['master_hierarchy_node_id'], true);
            }

            if (!empty($operations['media'])) {
                $results['media'] = $this->processMedia($productIds, $operations['media'], true);
            }
        });

        // Fire events only for products that actually had attribute changes
        if (!empty($results['attributes']['changed_product_ids'])) {
            $changedAttrIds = collect($operations['attributes'])->pluck('attribute_id')->unique()->toArray();
            foreach ($results['attributes']['changed_product_ids'] as $pid) {
                event(new \App\Events\AttributeValuesChanged($pid, $changedAttrIds));
            }
        }

        // Remove internal tracking data from response
        if (isset($results['attributes'])) {
            unset($results['attributes']['changed_product_ids']);
        }

        return response()->json([
            'message' => 'Bulk update completed.',
            'results' => $results,
        ]);
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
            if (!$attribute) {
                continue;
            }

            $mode = $op['mode'];
            $language = $op['language'] ?? null;

            // Load existing values for this attribute across all products
            $existing = ProductAttributeValue::whereIn('product_id', $productIds)
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
                    // Bulk delete
                    ProductAttributeValue::whereIn('product_id', $toUpdate)
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
                } else {
                    // Bulk upsert
                    $valueData = $this->resolveValueColumns($attribute, $op['value']);
                    $rows = [];
                    foreach ($toUpdate as $pid) {
                        $rows[] = array_merge([
                            'product_id' => $pid,
                            'attribute_id' => $attribute->id,
                            'language' => $language,
                            'multiplied_index' => 0,
                            'is_inherited' => false,
                            'inherited_from_node_id' => null,
                            'inherited_from_product_id' => null,
                        ], $valueData);
                    }

                    // Upsert in chunks to avoid hitting query size limits
                    foreach (array_chunk($rows, 500) as $chunk) {
                        ProductAttributeValue::upsert(
                            $chunk,
                            ['product_id', 'attribute_id', 'language', 'multiplied_index'],
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
            // Bulk-load existing relations for this operation across all products
            $existingSet = ProductRelation::whereIn('source_product_id', $productIds)
                ->where('target_product_id', $op['target_product_id'])
                ->where('relation_type_id', $op['relation_type_id'])
                ->pluck('source_product_id')
                ->flip()
                ->all();

            if ($op['action'] === 'add') {
                $toAdd = [];
                foreach ($productIds as $pid) {
                    if (isset($existingSet[$pid])) {
                        $alreadyExists++;
                    } else {
                        $added++;
                        $toAdd[] = [
                            'source_product_id' => $pid,
                            'target_product_id' => $op['target_product_id'],
                            'relation_type_id' => $op['relation_type_id'],
                            'sort_order' => 0,
                        ];
                    }
                }
                if ($persist && !empty($toAdd)) {
                    foreach (array_chunk($toAdd, 500) as $chunk) {
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
                    ProductRelation::whereIn('source_product_id', $toRemove)
                        ->where('target_product_id', $op['target_product_id'])
                        ->where('relation_type_id', $op['relation_type_id'])
                        ->delete();
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
            // Bulk-load existing assignments for this node across all products
            $existingSet = OutputHierarchyProductAssignment::whereIn('product_id', $productIds)
                ->where('hierarchy_node_id', $op['hierarchy_node_id'])
                ->pluck('product_id')
                ->flip()
                ->all();

            if ($op['action'] === 'assign') {
                $toAssign = [];
                foreach ($productIds as $pid) {
                    if (isset($existingSet[$pid])) {
                        $alreadyAssigned++;
                    } else {
                        $assigned++;
                        $toAssign[] = [
                            'hierarchy_node_id' => $op['hierarchy_node_id'],
                            'product_id' => $pid,
                            'sort_order' => 0,
                        ];
                    }
                }
                if ($persist && !empty($toAssign)) {
                    foreach (array_chunk($toAssign, 500) as $chunk) {
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
                    OutputHierarchyProductAssignment::whereIn('product_id', $toRemove)
                        ->where('hierarchy_node_id', $op['hierarchy_node_id'])
                        ->delete();
                }
            }
        }

        return compact('assigned', 'removed', 'alreadyAssigned');
    }

    // ── Status ──────────────────────────────────────────

    private function processStatus(array $productIds, string $status, bool $persist): array
    {
        $alreadyTarget = Product::whereIn('id', $productIds)->where('status', $status)->count();
        $wouldChange = count($productIds) - $alreadyTarget;

        if ($persist && $wouldChange > 0) {
            Product::whereIn('id', $productIds)
                ->where('status', '!=', $status)
                ->update(['status' => $status]);
        }

        return [
            'would_change' => $wouldChange,
            'already_target' => $alreadyTarget,
        ];
    }

    // ── Master Hierarchy ────────────────────────────────

    private function processMasterHierarchy(array $productIds, ?string $nodeId, bool $persist): array
    {
        if ($nodeId === null) {
            // Clear master hierarchy: count those that already have null
            $alreadyTarget = Product::whereIn('id', $productIds)->whereNull('master_hierarchy_node_id')->count();
            $wouldChange = count($productIds) - $alreadyTarget;

            if ($persist && $wouldChange > 0) {
                Product::whereIn('id', $productIds)
                    ->whereNotNull('master_hierarchy_node_id')
                    ->update(['master_hierarchy_node_id' => null]);
            }
        } else {
            $alreadyTarget = Product::whereIn('id', $productIds)->where('master_hierarchy_node_id', $nodeId)->count();
            $wouldChange = count($productIds) - $alreadyTarget;

            if ($persist && $wouldChange > 0) {
                Product::whereIn('id', $productIds)
                    ->where(function ($q) use ($nodeId) {
                        $q->where('master_hierarchy_node_id', '!=', $nodeId)
                          ->orWhereNull('master_hierarchy_node_id');
                    })
                    ->update(['master_hierarchy_node_id' => $nodeId]);
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
            // Bulk-load existing assignments for this media across all products
            $query = ProductMediaAssignment::whereIn('product_id', $productIds)
                ->where('media_id', $op['media_id']);

            if (!empty($op['usage_type_id'])) {
                $query->where('usage_type_id', $op['usage_type_id']);
            }

            $existingSet = $query->pluck('product_id')->flip()->all();

            if ($op['action'] === 'assign') {
                $toAssign = [];
                foreach ($productIds as $pid) {
                    if (isset($existingSet[$pid])) {
                        $alreadyAssigned++;
                    } else {
                        $assigned++;
                        $toAssign[] = [
                            'product_id' => $pid,
                            'media_id' => $op['media_id'],
                            'usage_type_id' => $op['usage_type_id'] ?? null,
                            'sort_order' => 0,
                            'is_primary' => false,
                        ];
                    }
                }
                if ($persist && !empty($toAssign)) {
                    foreach (array_chunk($toAssign, 500) as $chunk) {
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
                    $deleteQuery = ProductMediaAssignment::whereIn('product_id', $toRemove)
                        ->where('media_id', $op['media_id']);
                    if (!empty($op['usage_type_id'])) {
                        $deleteQuery->where('usage_type_id', $op['usage_type_id']);
                    }
                    $deleteQuery->delete();
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
            'String', 'RichText' => array_merge($columns, ['value_string' => $value !== null ? (string) $value : null]),
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
