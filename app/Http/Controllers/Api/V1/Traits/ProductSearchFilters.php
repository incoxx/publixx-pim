<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Traits;

use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\OutputHierarchyProductAssignment;
use Illuminate\Support\Facades\DB;

/**
 * Shared product search/filter logic used by ProductSearchController and ProductExportController.
 */
trait ProductSearchFilters
{
    private int $filterIdx = 0;
    private array $attributeCache = [];

    /**
     * Get attribute by ID with caching to avoid N+1 queries.
     */
    private function getCachedAttribute(string $id): ?Attribute
    {
        if (!isset($this->attributeCache[$id])) {
            $this->attributeCache[$id] = Attribute::find($id);
        }
        return $this->attributeCache[$id];
    }

    /**
     * Pre-load all attributes referenced in filter rules to avoid N+1.
     */
    protected function preloadFilterAttributes(array $group): void
    {
        $this->filterIdx = 0;
        $ids = $this->collectAttributeIds($group);
        if (!empty($ids)) {
            $attributes = Attribute::whereIn('id', $ids)->get();
            foreach ($attributes as $attr) {
                $this->attributeCache[$attr->id] = $attr;
            }
        }
    }

    private function collectAttributeIds(array $group): array
    {
        $ids = [];
        foreach ($group['rules'] ?? [] as $rule) {
            if (($rule['type'] ?? 'rule') === 'group') {
                $ids = array_merge($ids, $this->collectAttributeIds($rule));
            } elseif (!empty($rule['attribute_id'])) {
                $ids[] = $rule['attribute_id'];
            }
        }
        return array_unique($ids);
    }

    protected function validateFilterGroupDepth(array $group, int $depth = 0, int $maxDepth = 3): void
    {
        if ($depth > $maxDepth) {
            abort(422, 'Filtergruppen-Verschachtelung überschreitet die maximale Tiefe.');
        }
        foreach ($group['rules'] ?? [] as $rule) {
            if (($rule['type'] ?? 'rule') === 'group') {
                $this->validateFilterGroupDepth($rule, $depth + 1, $maxDepth);
            }
        }
    }

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
                // Limit regex length to prevent DoS via complex patterns
                $safeTerm = mb_substr($term, 0, 200);
                $query->where(function ($q) use ($safeTerm, $searchColumns) {
                    foreach ($searchColumns as $col) {
                        $q->orWhereRaw("{$col} REGEXP ?", [$safeTerm]);
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

    protected function applyCategoryFilter($query, array $categoryIds, bool $includeDescendants, string $hierarchyType = 'master'): void
    {
        $nodeIds = collect($categoryIds);

        if ($includeDescendants) {
            $selectedNodes = HierarchyNode::whereIn('id', $categoryIds)->get();

            foreach ($selectedNodes as $node) {
                $descendantIds = HierarchyNode::where('hierarchy_id', $node->hierarchy_id)
                    ->where('path', 'like', $node->path . '%')
                    ->where('id', '!=', $node->id)
                    ->pluck('id');
                $nodeIds = $nodeIds->merge($descendantIds);
            }
        }

        $uniqueNodeIds = $nodeIds->unique()->values();

        if ($hierarchyType === 'output') {
            $productIds = OutputHierarchyProductAssignment::whereIn('hierarchy_node_id', $uniqueNodeIds)
                ->pluck('product_id');
            $query->whereIn('products.id', $productIds);
        } else {
            $query->whereIn('master_hierarchy_node_id', $uniqueNodeIds);
        }
    }

    /**
     * Apply a single attribute filter rule.
     */
    protected function applyAttributeFilter($query, array $filter, int $idx, string $language = 'de'): void
    {
        $attrId = $filter['attribute_id'] ?? null;
        $value = $filter['value'] ?? null;
        $operator = $filter['operator'] ?? 'eq';

        // Skip incomplete rules (no attribute selected)
        if (empty($attrId)) {
            return;
        }

        $attribute = $this->getCachedAttribute($attrId);
        if (!$attribute) {
            return;
        }

        $alias = "pav_{$idx}";
        $column = $this->getValueColumn($attribute->data_type);

        // Existence checks don't need whereExists — they use whereExists/whereNotExists
        if ($operator === 'exists') {
            $query->whereExists(function ($sub) use ($alias, $attrId, $attribute, $language, $column) {
                $sub->select(DB::raw(1))
                    ->from("product_attribute_values as {$alias}")
                    ->whereColumn("{$alias}.product_id", 'products.id')
                    ->where("{$alias}.attribute_id", $attrId)
                    ->whereNotNull("{$alias}.{$column}");
                if ($column === 'value_string') {
                    $sub->where("{$alias}.{$column}", '!=', '');
                }
                if ($attribute->is_translatable) {
                    $sub->where("{$alias}.language", $language);
                }
            });
            return;
        }

        if ($operator === 'not_exists') {
            $query->whereNotExists(function ($sub) use ($alias, $attrId, $attribute, $language, $column) {
                $sub->select(DB::raw(1))
                    ->from("product_attribute_values as {$alias}")
                    ->whereColumn("{$alias}.product_id", 'products.id')
                    ->where("{$alias}.attribute_id", $attrId)
                    ->whereNotNull("{$alias}.{$column}");
                if ($column === 'value_string') {
                    $sub->where("{$alias}.{$column}", '!=', '');
                }
                if ($attribute->is_translatable) {
                    $sub->where("{$alias}.language", $language);
                }
            });
            return;
        }

        if ($operator === 'exists_lang') {
            $targetLang = $value;
            $query->whereExists(function ($sub) use ($alias, $attrId, $column, $targetLang) {
                $sub->select(DB::raw(1))
                    ->from("product_attribute_values as {$alias}")
                    ->whereColumn("{$alias}.product_id", 'products.id')
                    ->where("{$alias}.attribute_id", $attrId)
                    ->where("{$alias}.language", $targetLang)
                    ->whereNotNull("{$alias}.{$column}");
                if ($column === 'value_string') {
                    $sub->where("{$alias}.{$column}", '!=', '');
                }
            });
            return;
        }

        if ($operator === 'not_exists_lang') {
            $targetLang = $value;
            $query->whereNotExists(function ($sub) use ($alias, $attrId, $column, $targetLang) {
                $sub->select(DB::raw(1))
                    ->from("product_attribute_values as {$alias}")
                    ->whereColumn("{$alias}.product_id", 'products.id')
                    ->where("{$alias}.attribute_id", $attrId)
                    ->where("{$alias}.language", $targetLang)
                    ->whereNotNull("{$alias}.{$column}");
                if ($column === 'value_string') {
                    $sub->where("{$alias}.{$column}", '!=', '');
                }
            });
            return;
        }

        // Value-based operators — skip if value is empty (incomplete rule)
        if ($value === null || $value === '') {
            return;
        }

        $query->whereExists(function ($sub) use ($alias, $attrId, $value, $operator, $attribute, $language, $column) {
            $sub->select(DB::raw(1))
                ->from("product_attribute_values as {$alias}")
                ->whereColumn("{$alias}.product_id", 'products.id')
                ->where("{$alias}.attribute_id", $attrId);

            if ($attribute->is_translatable) {
                $sub->where("{$alias}.language", $language);
            }

            $fullColumn = "{$alias}.{$column}";

            switch ($operator) {
                case 'like':
                    $sub->where($fullColumn, 'LIKE', '%' . $value . '%');
                    break;
                case 'starts_with':
                    $sub->where($fullColumn, 'LIKE', $value . '%');
                    break;
                case 'ends_with':
                    $sub->where($fullColumn, 'LIKE', '%' . $value);
                    break;
                case 'neq':
                    $sub->where($fullColumn, '!=', $value);
                    break;
                case 'regex':
                    $sub->whereRaw("{$fullColumn} REGEXP ?", [mb_substr($value, 0, 200)]);
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
                case 'between':
                    if (is_array($value) && count($value) === 2) {
                        $sub->whereBetween($fullColumn, [$value[0], $value[1]]);
                    }
                    break;
                case 'in':
                    if (is_array($value)) {
                        $sub->whereIn($fullColumn, $value);
                    }
                    break;
                default: // eq
                    $sub->where($fullColumn, $value);
                    break;
            }
        });
    }

    /**
     * Apply recursive attribute filter groups (AND/OR/NOT logic).
     */
    protected function applyAttributeFilterGroups($query, array $group, string $language = 'de'): void
    {
        $operator = $group['operator'] ?? 'AND';
        $rules = $group['rules'] ?? [];

        if (empty($rules)) {
            return;
        }

        $buildGroup = function ($q) use ($operator, $rules, $language) {
            foreach ($rules as $i => $rule) {
                $ruleMethod = ($operator === 'OR' && $i > 0) ? 'orWhere' : 'where';

                if (($rule['type'] ?? 'rule') === 'group') {
                    $q->$ruleMethod(function ($sub) use ($rule, $language) {
                        $this->applyAttributeFilterGroups($sub, $rule, $language);
                    });
                } else {
                    $idx = $this->filterIdx++;
                    $q->$ruleMethod(function ($sub) use ($rule, $idx, $language) {
                        $this->applyAttributeFilter($sub, $rule, $idx, $language);
                    });
                }
            }
        };

        if ($operator === 'NOT') {
            $query->whereNot($buildGroup);
        } else {
            $query->where($buildGroup);
        }
    }

    protected function applyMissingTranslationFilter($query, string $attributeId, string $targetLanguage): void
    {
        $attribute = $this->getCachedAttribute($attributeId);
        if (!$attribute || !$attribute->is_translatable) {
            return;
        }

        $column = $this->getValueColumn($attribute->data_type);

        $query->whereNotExists(function ($sub) use ($attributeId, $targetLanguage, $column) {
            $sub->select(DB::raw(1))
                ->from('product_attribute_values as pav_missing')
                ->whereColumn('pav_missing.product_id', 'products.id')
                ->where('pav_missing.attribute_id', $attributeId)
                ->where('pav_missing.language', $targetLanguage)
                ->whereNotNull("pav_missing.{$column}")
                ->where("pav_missing.{$column}", '!=', '');
        });
    }

    protected function applyPriceRegionFilter($query, string $priceRegionId): void
    {
        $query->whereHas('prices', function ($q) use ($priceRegionId) {
            $q->where('price_region_id', $priceRegionId);
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
