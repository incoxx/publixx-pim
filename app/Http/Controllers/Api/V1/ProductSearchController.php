<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'status' => 'nullable|string|in:active,draft,inactive,discontinued',
            'attribute_filters' => 'nullable|array',
            'attribute_filters.*.attribute_id' => 'required|string|uuid',
            'attribute_filters.*.value' => 'required',
            'attribute_filters.*.operator' => 'nullable|string|in:eq,like,gt,lt,gte,lte,regex,soundex',
            'sort' => 'nullable|string|in:name,sku,status,updated_at,created_at',
            'order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|integer|min:1',
            'language' => 'nullable|string|max:5',
        ]);

        $searchTerm = $validated['search'] ?? null;
        $searchMode = $validated['search_mode'] ?? 'like';
        $categoryIds = $validated['category_ids'] ?? [];
        $includeDescendants = $validated['include_descendants'] ?? true;
        $status = $validated['status'] ?? null;
        $attributeFilters = $validated['attribute_filters'] ?? [];
        $sortField = $validated['sort'] ?? 'updated_at';
        $sortOrder = $validated['order'] ?? 'desc';
        $perPage = $validated['per_page'] ?? 50;
        $language = $validated['language'] ?? 'de';

        $query = Product::query()
            ->with('productType')
            ->where('product_type_ref', 'product');

        // ── Status filter ──
        if ($status) {
            $query->where('status', $status);
        }

        // ── Text search with multiple modes ──
        if ($searchTerm) {
            $this->applyTextSearch($query, $searchTerm, $searchMode);
        }

        // ── Category filter (with descendants via materialized path) ──
        if (!empty($categoryIds)) {
            $this->applyCategoryFilter($query, $categoryIds, $includeDescendants);
        }

        // ── Attribute filters (subquery-based) ──
        foreach ($attributeFilters as $idx => $filter) {
            $this->applyAttributeFilter($query, $filter, $idx, $language);
        }

        // ── Sorting ──
        $query->orderBy($sortField, $sortOrder);

        // ── Paginate ──
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
            ],
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

        $attributes = Attribute::where('is_searchable', true)
            ->where('is_internal', false)
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

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Apply text search with multiple modes: LIKE, SOUNDEX, REGEXP.
     */
    private function applyTextSearch($query, string $term, string $mode): void
    {
        $searchColumns = ['name', 'sku', 'ean'];

        switch ($mode) {
            case 'soundex':
                // SOUNDEX fuzzy matching — great for misspellings.
                // MySQL SOUNDEX works on first word; also combine with LIKE as fallback.
                $query->where(function ($q) use ($term, $searchColumns) {
                    foreach ($searchColumns as $col) {
                        $q->orWhereRaw("SOUNDEX({$col}) = SOUNDEX(?)", [$term]);
                        $q->orWhere($col, 'LIKE', '%' . $term . '%');
                    }
                });
                break;

            case 'regex':
                // MySQL REGEXP for pattern matching.
                // Safely pass user input as bound parameter.
                $query->where(function ($q) use ($term, $searchColumns) {
                    foreach ($searchColumns as $col) {
                        $q->orWhereRaw("{$col} REGEXP ?", [$term]);
                    }
                });
                break;

            default: // 'like'
                $like = '%' . $term . '%';
                $query->where(function ($q) use ($like, $searchColumns) {
                    foreach ($searchColumns as $col) {
                        $q->orWhere($col, 'LIKE', $like);
                    }
                });
                break;
        }
    }

    /**
     * Category filter with materialized-path descendant expansion.
     */
    private function applyCategoryFilter($query, array $categoryIds, bool $includeDescendants): void
    {
        if ($includeDescendants) {
            $selectedNodes = HierarchyNode::whereIn('id', $categoryIds)->get();
            $allNodeIds = collect($categoryIds);

            foreach ($selectedNodes as $node) {
                $descendantIds = HierarchyNode::where('path', 'like', $node->path . '%')
                    ->where('id', '!=', $node->id)
                    ->pluck('id');
                $allNodeIds = $allNodeIds->merge($descendantIds);
            }

            $query->whereIn('master_hierarchy_node_id', $allNodeIds->unique()->values());
        } else {
            $query->whereIn('master_hierarchy_node_id', $categoryIds);
        }
    }

    /**
     * Apply a single attribute filter using subquery.
     */
    private function applyAttributeFilter($query, array $filter, int $idx, string $language): void
    {
        $attrId = $filter['attribute_id'];
        $value = $filter['value'];
        $operator = $filter['operator'] ?? 'eq';

        $attribute = Attribute::find($attrId);
        if (!$attribute) {
            return;
        }

        $alias = "pav_{$idx}";

        $query->whereExists(function ($sub) use ($alias, $attrId, $value, $operator, $attribute, $language) {
            $sub->select(DB::raw(1))
                ->from("product_attribute_values as {$alias}")
                ->whereColumn("{$alias}.product_id", 'products.id')
                ->where("{$alias}.attribute_id", $attrId);

            if ($attribute->is_translatable) {
                $sub->where("{$alias}.language", $language);
            }

            $column = $this->getValueColumn($attribute->data_type);
            $fullColumn = "{$alias}.{$column}";

            switch ($operator) {
                case 'like':
                    $sub->where($fullColumn, 'LIKE', '%' . $value . '%');
                    break;
                case 'regex':
                    $sub->whereRaw("{$fullColumn} REGEXP ?", [$value]);
                    break;
                case 'soundex':
                    $sub->where(function ($q) use ($fullColumn, $value) {
                        $q->whereRaw("SOUNDEX({$fullColumn}) = SOUNDEX(?)", [$value])
                          ->orWhere($fullColumn, 'LIKE', '%' . $value . '%');
                    });
                    break;
                case 'gt':
                    $sub->where($fullColumn, '>', $value);
                    break;
                case 'lt':
                    $sub->where($fullColumn, '<', $value);
                    break;
                case 'gte':
                    $sub->where($fullColumn, '>=', $value);
                    break;
                case 'lte':
                    $sub->where($fullColumn, '<=', $value);
                    break;
                default: // eq
                    if ($attribute->data_type === 'String') {
                        $sub->where($fullColumn, 'LIKE', '%' . $value . '%');
                    } else {
                        $sub->where($fullColumn, $value);
                    }
                    break;
            }
        });
    }

    private function getValueColumn(string $dataType): string
    {
        return match ($dataType) {
            'Number', 'Float' => 'value_number',
            'Date' => 'value_date',
            'Flag' => 'value_flag',
            'Selection', 'Dictionary' => 'value_selection_id',
            default => 'value_string',
        };
    }
}
