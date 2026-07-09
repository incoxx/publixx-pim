<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Traits\ProductSearchFilters;
use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SQL-based product search — replaces PQL engine.
 *
 * Supports:
 *   - Text search: LIKE, SOUNDEX (fuzzy), REGEXP
 *   - Category search with descendant traversal (materialized path)
 *   - Attribute filters with typed value columns
 *   - Status filter
 */
class ProductSearchController extends Controller
{
    use ProductSearchFilters;

    /**
     * Quick-Lookup-Präfixfilter (LIKE 'wert%') auf sku/name/ean — unabhängig von der
     * kombinierten Freitextsuche (applyTextSearch), damit einzelne Spalten gezielt
     * gefiltert werden können (Quick Lookup im Such-Assistenten).
     */
    private function applyQuickLookupPrefixFilters($query, array $validated): void
    {
        foreach (['sku', 'name', 'ean'] as $field) {
            $value = $validated[$field] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $query->where($field, 'LIKE', addcslashes($value, '%_').'%');
        }
    }

    /**
     * POST /api/v1/products/search
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:500',
            'search_mode' => 'nullable|string|in:like,soundex,regex',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'string|uuid',
            'include_descendants' => 'nullable|boolean',
            'hierarchy_type' => 'nullable|string|in:master,output',
            'status' => 'nullable|string|in:active,draft,inactive,discontinued',
            'product_type_ids' => 'nullable|array',
            'product_type_ids.*' => 'string|uuid',
            'manufacturer_ids' => 'nullable|array',
            'manufacturer_ids.*' => 'string|uuid',
            'sku' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'ean' => 'nullable|string|max:255',
            'attribute_columns' => 'nullable|array',
            'attribute_columns.*' => 'string|uuid',
            'attribute_filters' => 'nullable|array',
            'attribute_filters.*.attribute_id' => 'required|string|uuid',
            'attribute_filters.*.value' => 'required',
            'attribute_filters.*.operator' => 'nullable|string|in:eq,neq,like,starts_with,ends_with,gt,lt,gte,lte,between,in,regex,soundex,exists,not_exists,exists_lang,not_exists_lang',
            'attribute_filter_groups' => 'nullable|array',
            'sort' => 'nullable|string|in:name,sku,status,updated_at,created_at',
            'order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|integer|min:1',
            'language' => 'nullable|string|max:5',
            'price_region_id' => 'nullable|string|uuid',
            'missing_translation' => 'nullable|array',
            'missing_translation.attribute_id' => 'required_with:missing_translation|string|uuid',
            'missing_translation.target_language' => 'required_with:missing_translation|string|max:5',
        ]);

        $searchTerm = $validated['search'] ?? null;
        $searchMode = $validated['search_mode'] ?? 'like';
        $categoryIds = $validated['category_ids'] ?? [];
        $includeDescendants = $validated['include_descendants'] ?? true;
        $hierarchyType = $validated['hierarchy_type'] ?? 'master';
        $status = $validated['status'] ?? null;
        $attributeColumns = $validated['attribute_columns'] ?? [];
        $attributeFilters = $validated['attribute_filters'] ?? [];
        $sortField = $validated['sort'] ?? 'updated_at';
        $sortOrder = $validated['order'] ?? 'desc';
        $perPage = $validated['per_page'] ?? 50;
        $language = $validated['language'] ?? 'de';

        $query = Product::query()
            ->with(['productType', 'manufacturer'])
            ->where('product_type_ref', 'product');

        // ── Status filter ──
        if ($status) {
            $query->where('status', $status);
        }

        // ── Product type filter ──
        $productTypeIds = $validated['product_type_ids'] ?? [];
        if (!empty($productTypeIds)) {
            $query->whereIn('product_type_id', $productTypeIds);
        }

        // ── Manufacturer filter ──
        $manufacturerIds = $validated['manufacturer_ids'] ?? [];
        if (!empty($manufacturerIds)) {
            $query->whereIn('manufacturer_id', $manufacturerIds);
        }

        // ── Quick-Lookup-Präfixfilter (sku/name/ean) ──
        $this->applyQuickLookupPrefixFilters($query, $validated);

        // ── Text search with multiple modes ──
        if ($searchTerm) {
            $this->applyTextSearch($query, $searchTerm, $searchMode);
        }

        // ── Category filter (with descendants via materialized path) ──
        if (!empty($categoryIds)) {
            $this->applyCategoryFilter($query, $categoryIds, $includeDescendants, $hierarchyType);
        }

        // ── Price region filter ──
        $priceRegionId = $validated['price_region_id'] ?? null;
        if ($priceRegionId) {
            $this->applyPriceRegionFilter($query, $priceRegionId);
        }

        // ── Missing translation filter ──
        $missingTranslation = $validated['missing_translation'] ?? null;
        if ($missingTranslation) {
            $this->applyMissingTranslationFilter($query, $missingTranslation['attribute_id'], $missingTranslation['target_language']);
        }

        // ── Attribute filters (flat, legacy) ──
        if (!empty($attributeFilters)) {
            // Alle referenzierten Attribute vorladen, um N+1 in getCachedAttribute() zu vermeiden
            $flatAttrIds = array_unique(array_filter(array_column($attributeFilters, 'attribute_id')));
            if (!empty($flatAttrIds)) {
                foreach (\App\Models\Attribute::whereIn('id', $flatAttrIds)->get() as $attr) {
                    $this->attributeCache[$attr->id] = $attr;
                }
            }
            foreach ($attributeFilters as $idx => $filter) {
                $this->applyAttributeFilter($query, $filter, $idx, $language);
            }
        }

        // ── Attribute filter groups (recursive AND/OR/NOT) ──
        $filterGroups = $validated['attribute_filter_groups'] ?? null;
        if ($filterGroups && !empty($filterGroups['rules'] ?? [])) {
            $this->validateFilterGroupDepth($filterGroups);
            $this->preloadFilterAttributes($filterGroups);
            $this->applyAttributeFilterGroups($query, $filterGroups, $language);
        }

        // ── Sorting ──
        $query->orderBy($sortField, $sortOrder);

        // ── Paginate ──
        $paginated = $query->paginate($perPage);

        $data = collect($paginated->items());

        // ── Attribute columns (optional) ──
        if (!empty($attributeColumns)) {
            $productIds = $data->pluck('id');
            $attrValues = ProductAttributeValue::whereIn('product_id', $productIds)
                ->whereIn('attribute_id', $attributeColumns)
                ->where(fn ($q) => $q->where('language', $language)->orWhereNull('language'))
                ->with('valueListEntry')
                ->get()
                ->groupBy('product_id');

            $data = $data->map(function ($product) use ($attrValues, $attributeColumns) {
                $attrs = [];
                $productAttrValues = $attrValues->get($product->id, collect());
                foreach ($attributeColumns as $attrId) {
                    $av = $productAttrValues->firstWhere('attribute_id', $attrId);
                    if (!$av) {
                        $attrs[$attrId] = null;
                    } elseif ($av->value_selection_id && $av->valueListEntry) {
                        $attrs[$attrId] = $av->valueListEntry->display_value_de ?? $av->valueListEntry->code ?? '';
                    } elseif ($av->value_flag !== null) {
                        $attrs[$attrId] = $av->value_flag ? 'Ja' : 'Nein';
                    } elseif ($av->value_date !== null) {
                        $attrs[$attrId] = $av->value_date;
                    } elseif ($av->value_number !== null) {
                        $attrs[$attrId] = $av->value_number;
                    } else {
                        $attrs[$attrId] = $av->value_string ?? '';
                    }
                }
                $product->setAttribute('attributes', $attrs);
                return $product;
            });
        }

        return response()->json([
            'data' => $data->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/products/search/ids
     *
     * Return all product IDs matching the current search/filter criteria.
     */
    public function allIds(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:500',
            'search_mode' => 'nullable|string|in:like,soundex,regex',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'string|uuid',
            'include_descendants' => 'nullable|boolean',
            'hierarchy_type' => 'nullable|string|in:master,output',
            'status' => 'nullable|string|in:active,draft,inactive,discontinued',
            'product_type_ids' => 'nullable|array',
            'product_type_ids.*' => 'string|uuid',
            'manufacturer_ids' => 'nullable|array',
            'manufacturer_ids.*' => 'string|uuid',
            'sku' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'ean' => 'nullable|string|max:255',
            'attribute_filters' => 'nullable|array',
            'attribute_filters.*.attribute_id' => 'required|string|uuid',
            'attribute_filters.*.value' => 'nullable',
            'attribute_filters.*.operator' => 'nullable|string',
            'attribute_filter_groups' => 'nullable|array',
            'missing_translation' => 'nullable|array',
            'missing_translation.attribute_id' => 'required_with:missing_translation|string|uuid',
            'missing_translation.target_language' => 'required_with:missing_translation|string|max:5',
            'language' => 'nullable|string|max:5',
        ]);

        $language = $validated['language'] ?? 'de';
        $query = Product::query()->where('product_type_ref', 'product');

        if ($status = $validated['status'] ?? null) {
            $query->where('status', $status);
        }

        $productTypeIds = $validated['product_type_ids'] ?? [];
        if (!empty($productTypeIds)) {
            $query->whereIn('product_type_id', $productTypeIds);
        }

        $manufacturerIds = $validated['manufacturer_ids'] ?? [];
        if (!empty($manufacturerIds)) {
            $query->whereIn('manufacturer_id', $manufacturerIds);
        }

        $this->applyQuickLookupPrefixFilters($query, $validated);

        $searchTerm = $validated['search'] ?? null;
        $searchMode = $validated['search_mode'] ?? 'like';
        if ($searchTerm) {
            $this->applyTextSearch($query, $searchTerm, $searchMode);
        }

        $categoryIds = $validated['category_ids'] ?? [];
        if (!empty($categoryIds)) {
            $includeDescendants = $validated['include_descendants'] ?? true;
            $hierarchyType = $validated['hierarchy_type'] ?? 'master';
            $this->applyCategoryFilter($query, $categoryIds, $includeDescendants, $hierarchyType);
        }

        if ($missingTranslation = $validated['missing_translation'] ?? null) {
            $this->applyMissingTranslationFilter($query, $missingTranslation['attribute_id'], $missingTranslation['target_language']);
        }

        $flatFilters = $validated['attribute_filters'] ?? [];
        if (!empty($flatFilters)) {
            $flatAttrIds = array_unique(array_filter(array_column($flatFilters, 'attribute_id')));
            if (!empty($flatAttrIds)) {
                foreach (\App\Models\Attribute::whereIn('id', $flatAttrIds)->get() as $attr) {
                    $this->attributeCache[$attr->id] = $attr;
                }
            }
            foreach ($flatFilters as $idx => $filter) {
                $this->applyAttributeFilter($query, $filter, $idx, $language);
            }
        }

        $filterGroups = $validated['attribute_filter_groups'] ?? null;
        if ($filterGroups && !empty($filterGroups['rules'] ?? [])) {
            $this->validateFilterGroupDepth($filterGroups);
            $this->preloadFilterAttributes($filterGroups);
            $this->applyAttributeFilterGroups($query, $filterGroups, $language);
        }

        return response()->json([
            'data' => $query->pluck('id'),
        ]);
    }

    /**
     * POST /api/v1/products/search/count
     *
     * Returns the count of products matching filter criteria (for live preview).
     */
    public function count(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:500',
            'search_mode' => 'nullable|string|in:like,soundex,regex',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'string|uuid',
            'include_descendants' => 'nullable|boolean',
            'hierarchy_type' => 'nullable|string|in:master,output',
            'status' => 'nullable|string|in:active,draft,inactive,discontinued',
            'product_type_ids' => 'nullable|array',
            'product_type_ids.*' => 'string|uuid',
            'manufacturer_ids' => 'nullable|array',
            'manufacturer_ids.*' => 'string|uuid',
            'sku' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'ean' => 'nullable|string|max:255',
            'attribute_filter_groups' => 'nullable|array',
            'attribute_filters' => 'nullable|array',
            'attribute_filters.*.attribute_id' => 'required|string|uuid',
            'attribute_filters.*.value' => 'nullable',
            'attribute_filters.*.operator' => 'nullable|string',
            'language' => 'nullable|string|max:5',
            'missing_translation' => 'nullable|array',
            'missing_translation.attribute_id' => 'required_with:missing_translation|string|uuid',
            'missing_translation.target_language' => 'required_with:missing_translation|string|max:5',
        ]);

        $query = Product::query()->where('product_type_ref', 'product');
        $language = $validated['language'] ?? 'de';

        if ($status = $validated['status'] ?? null) {
            $query->where('status', $status);
        }

        if (!empty($validated['product_type_ids'] ?? [])) {
            $query->whereIn('product_type_id', $validated['product_type_ids']);
        }

        if (!empty($validated['manufacturer_ids'] ?? [])) {
            $query->whereIn('manufacturer_id', $validated['manufacturer_ids']);
        }

        $this->applyQuickLookupPrefixFilters($query, $validated);

        if ($searchTerm = $validated['search'] ?? null) {
            $this->applyTextSearch($query, $searchTerm, $validated['search_mode'] ?? 'like');
        }

        $categoryIds = $validated['category_ids'] ?? [];
        if (!empty($categoryIds)) {
            $this->applyCategoryFilter($query, $categoryIds, $validated['include_descendants'] ?? true, $validated['hierarchy_type'] ?? 'master');
        }

        if ($missingTranslation = $validated['missing_translation'] ?? null) {
            $this->applyMissingTranslationFilter($query, $missingTranslation['attribute_id'], $missingTranslation['target_language']);
        }

        $flatFilters = $validated['attribute_filters'] ?? [];
        if (!empty($flatFilters)) {
            $flatAttrIds = array_unique(array_filter(array_column($flatFilters, 'attribute_id')));
            if (!empty($flatAttrIds)) {
                foreach (\App\Models\Attribute::whereIn('id', $flatAttrIds)->get() as $attr) {
                    $this->attributeCache[$attr->id] = $attr;
                }
            }
            foreach ($flatFilters as $idx => $filter) {
                $this->applyAttributeFilter($query, $filter, $idx, $language);
            }
        }

        $filterGroups = $validated['attribute_filter_groups'] ?? null;
        if ($filterGroups && !empty($filterGroups['rules'] ?? [])) {
            $this->validateFilterGroupDepth($filterGroups);
            $this->preloadFilterAttributes($filterGroups);
            $this->applyAttributeFilterGroups($query, $filterGroups, $language);
        }

        return response()->json(['count' => $query->count()]);
    }

    /**
     * POST /api/v1/products/bulk-delete
     *
     * Delete multiple products at once (Admin only).
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $this->authorize('delete', Product::class);

        if (!$request->user()->hasRole('Admin')) {
            return response()->json([
                'message' => 'Nur Administratoren dürfen Produkte in Masse löschen.',
            ], 403);
        }

        $validated = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'string|uuid',
        ]);

        $productIds = collect($validated['product_ids']);
        $deletedCount = 0;

        DB::transaction(function () use ($productIds, &$deletedCount) {
            // Delete related data in chunks to avoid too many placeholders
            foreach ($productIds->chunk(1000) as $chunk) {
                $ids = $chunk->values()->all();
                ProductAttributeValue::whereIn('product_id', $ids)->delete();
                ProductPrice::whereIn('product_id', $ids)->delete();

                DB::table('product_relations')
                    ->where(function ($q) use ($ids) {
                        $q->whereIn('source_product_id', $ids)
                            ->orWhereIn('target_product_id', $ids);
                    })
                    ->delete();

                DB::table('product_media_assignments')
                    ->whereIn('product_id', $ids)->delete();
                DB::table('output_hierarchy_product_assignments')
                    ->whereIn('product_id', $ids)->delete();
                DB::table('products_search_index')
                    ->whereIn('product_id', $ids)->delete();
                DB::table('watchlist_items')
                    ->whereIn('product_id', $ids)->delete();
                DB::table('product_versions')
                    ->whereIn('product_id', $ids)->delete();
                DB::table('variant_inheritance_rules')
                    ->whereIn('product_id', $ids)->delete();

                $deletedCount += Product::whereIn('id', $ids)->delete();
            }
        });

        Log::info('Bulk delete completed', [
            'user' => $request->user()->email,
            'requested' => $productIds->count(),
            'deleted' => $deletedCount,
        ]);

        return response()->json([
            'message' => "{$deletedCount} Produkte gelöscht.",
            'deleted_count' => $deletedCount,
        ]);
    }

    /**
     * GET /api/v1/products/search/attributes
     *
     * Returns searchable attributes with value list entries loaded.
     */
    public function searchableAttributes(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $attributes = Attribute::where('is_internal', false)
            ->where('status', 'active')
            ->with(['valueList.entries', 'attributeType', 'unitGroup'])
            ->orderBy('position')
            ->get();

        $result = $attributes->map(function ($attr) {
            $item = [
                'id' => $attr->id,
                'technical_name' => $attr->technical_name,
                'name_de' => $attr->name_de,
                'name_en' => $attr->name_en,
                'data_type' => $attr->data_type,
                'is_translatable' => $attr->is_translatable,
                'attribute_type' => $attr->attributeType ? [
                    'id' => $attr->attributeType->id,
                    'name_de' => $attr->attributeType->name_de,
                ] : null,
            ];

            if ($attr->valueList) {
                $item['value_list'] = [
                    'id' => $attr->valueList->id,
                    'name' => $attr->valueList->name,
                    'entries' => $attr->valueList->entries->map(fn($e) => [
                        'id' => $e->id,
                        'code' => $e->code,
                        'display_value_de' => $e->display_value_de,
                        'display_value_en' => $e->display_value_en,
                    ])->toArray(),
                ];
            }

            return $item;
        });

        return response()->json(['data' => $result]);
    }

}
