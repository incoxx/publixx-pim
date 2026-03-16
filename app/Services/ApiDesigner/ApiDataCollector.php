<?php

declare(strict_types=1);

namespace App\Services\ApiDesigner;

use App\Models\ApiTemplate;
use App\Models\Product;
use App\Models\SearchProfile;
use App\Services\Report\ElementRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ApiDataCollector
{
    public function __construct(
        private readonly ElementRenderer $elementRenderer,
    ) {}

    /**
     * Collect products and organize them into the group structure defined by the template.
     *
     * @return array{grouped: array, total: int}
     */
    public function collect(ApiTemplate $template, ?SearchProfile $searchProfile = null, ?int $limit = null): array
    {
        $query = $this->buildQuery($searchProfile);
        $relations = $this->determineRelations($template->template_json);

        $productQuery = $query->with($relations);
        if ($limit) {
            $productQuery->limit($limit);
        }
        $products = $productQuery->get();

        $grouped = $this->groupProducts($products, $template->template_json, $template->language ?? 'de');

        return [
            'grouped' => $grouped,
            'total' => $products->count(),
        ];
    }

    private function buildQuery(?SearchProfile $searchProfile): Builder
    {
        $query = Product::query();

        if (!$searchProfile) {
            return $query->where('status', 'active');
        }

        if ($searchProfile->status_filter) {
            $query->where('status', $searchProfile->status_filter);
        }

        if ($searchProfile->search_text) {
            $term = $searchProfile->search_text;
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('sku', 'LIKE', "%{$term}%")
                  ->orWhere('ean', 'LIKE', "%{$term}%");
            });
        }

        if (!empty($searchProfile->category_ids)) {
            if ($searchProfile->include_descendants) {
                $query->whereHas('masterHierarchyNode', function (Builder $q) use ($searchProfile) {
                    $q->whereIn('id', $searchProfile->category_ids)
                      ->orWhere(function (Builder $sub) use ($searchProfile) {
                          foreach ($searchProfile->category_ids as $catId) {
                              $sub->orWhere('path', 'LIKE', "%{$catId}%");
                          }
                      });
                });
            } else {
                $query->whereIn('master_hierarchy_node_id', $searchProfile->category_ids);
            }
        }

        if (!empty($searchProfile->attribute_filters)) {
            foreach ($searchProfile->attribute_filters as $filter) {
                $attrId = $filter['attribute_id'] ?? null;
                $value = $filter['value'] ?? null;
                $operator = $filter['operator'] ?? 'eq';

                if (!$attrId || $value === null) {
                    continue;
                }

                $query->whereHas('attributeValues', function (Builder $q) use ($attrId, $value, $operator) {
                    $q->where('attribute_id', $attrId);
                    $column = is_numeric($value) ? 'value_number' : 'value_string';
                    $sqlOp = match ($operator) {
                        'gte' => '>=', 'lte' => '<=', 'gt' => '>', 'lt' => '<',
                        'contains' => 'LIKE', 'neq' => '!=',
                        default => '=',
                    };
                    $sqlValue = $operator === 'contains' ? "%{$value}%" : $value;
                    $q->where($column, $sqlOp, $sqlValue);
                });
            }
        }

        $sortField = $searchProfile->sort_field ?? 'name';
        $sortOrder = $searchProfile->sort_order ?? 'asc';
        $query->orderBy($sortField, $sortOrder);

        return $query;
    }

    private function determineRelations(array $templateJson): array
    {
        $relations = ['productType', 'masterHierarchyNode'];
        $hasAttributes = false;

        $this->walkGroupElements($templateJson['groups'] ?? [], function (array $element) use (&$hasAttributes) {
            if ($element['type'] === 'attribute') {
                $hasAttributes = true;
            }
        });

        $this->walkGroups($templateJson['groups'] ?? [], function (array $group) use (&$hasAttributes) {
            if (str_starts_with($group['field'] ?? '', 'attribute:')) {
                $hasAttributes = true;
            }
        });

        if ($hasAttributes) {
            $relations[] = 'attributeValues.attribute';
            $relations[] = 'attributeValues.unit';
            $relations[] = 'attributeValues.valueListEntry';
        }

        return $relations;
    }

    private function walkGroupElements(array $groups, callable $callback): void
    {
        foreach ($groups as $group) {
            foreach (['header', 'detail', 'footer'] as $section) {
                foreach ($group[$section]['elements'] ?? [] as $element) {
                    $callback($element);
                }
            }
            if (!empty($group['groups'])) {
                $this->walkGroupElements($group['groups'], $callback);
            }
        }
    }

    private function walkGroups(array $groups, callable $callback): void
    {
        foreach ($groups as $group) {
            $callback($group);
            if (!empty($group['groups'])) {
                $this->walkGroups($group['groups'], $callback);
            }
        }
    }

    private function groupProducts(Collection $products, array $templateJson, string $language): array
    {
        $groups = $templateJson['groups'] ?? [];

        if (empty($groups)) {
            return [[
                'definition' => [
                    'header' => ['elements' => []],
                    'detail' => ['elements' => $templateJson['detail']['elements'] ?? []],
                    'footer' => ['elements' => []],
                ],
                'label' => '',
                'value' => '',
                'products' => $products->all(),
                'subgroups' => [],
                'count' => $products->count(),
            ]];
        }

        return $this->buildGroupLevel($products, $groups, $language);
    }

    private function buildGroupLevel(Collection $products, array $groupDefs, string $language): array
    {
        if (empty($groupDefs)) {
            return [];
        }

        $result = [];

        foreach ($groupDefs as $groupDef) {
            $field = $groupDef['field'] ?? 'none';

            if ($field === 'none') {
                $result[] = [
                    'definition' => $groupDef,
                    'label' => $groupDef['label'] ?? '',
                    'value' => '',
                    'products' => $products->all(),
                    'subgroups' => [],
                    'count' => $products->count(),
                ];
                continue;
            }

            $grouped = $products->groupBy(fn (Product $p) => $this->elementRenderer->resolveGroupValue($p, $field, $language));

            $sortOrder = $groupDef['sortOrder'] ?? 'asc';
            $grouped = $sortOrder === 'desc' ? $grouped->sortKeysDesc() : $grouped->sortKeys();

            foreach ($grouped as $groupValue => $groupProducts) {
                $subgroups = [];
                if (!empty($groupDef['groups'])) {
                    $subgroups = $this->buildGroupLevel($groupProducts, $groupDef['groups'], $language);
                }

                $result[] = [
                    'definition' => $groupDef,
                    'label' => $groupDef['label'] ?? '',
                    'value' => (string) $groupValue,
                    'products' => empty($subgroups) ? $groupProducts->values()->all() : [],
                    'subgroups' => $subgroups,
                    'count' => $groupProducts->count(),
                ];
            }
        }

        return $result;
    }
}
