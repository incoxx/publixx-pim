<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Traits;

use App\Models\Attribute;
use App\Models\HierarchyNode;
use Illuminate\Support\Facades\DB;

/**
 * Shared product search/filter logic used by ProductSearchController and ProductExportController.
 */
trait ProductSearchFilters
{
    protected function applyTextSearch($query, string $term, string $mode): void
    {
        $searchColumns = ['name', 'sku', 'ean'];

        switch ($mode) {
            case 'soundex':
                $query->where(function ($q) use ($term, $searchColumns) {
                    foreach ($searchColumns as $col) {
                        $q->orWhereRaw("SOUNDEX({$col}) = SOUNDEX(?)", [$term]);
                        $q->orWhere($col, 'LIKE', '%' . $term . '%');
                    }
                });
                break;

            case 'regex':
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

    protected function applyCategoryFilter($query, array $categoryIds, bool $includeDescendants): void
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

    protected function applyAttributeFilter($query, array $filter, int $idx, string $language): void
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

    protected function getValueColumn(string $dataType): string
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
