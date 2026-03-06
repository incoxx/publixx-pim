<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\Product;
use App\Models\ReportTemplate;
use App\Models\SearchProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportDataCollector
{
    public function __construct(
        private readonly ElementRenderer $elementRenderer,
    ) {}

    /**
     * Collect products and organize them into the group structure defined by the template.
     *
     * @return array{products: Collection, grouped: array, total: int}
     */
    public function collect(ReportTemplate $template, ?SearchProfile $searchProfile = null, ?int $limit = null, ?array $productIds = null): array
    {
        $query = $productIds
            ? Product::whereIn('id', $productIds)
            : $this->buildQuery($searchProfile);
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

    /**
     * Build the product query from search profile filters.
     */
    private function buildQuery(?SearchProfile $searchProfile): Builder
    {
        $query = Product::query();

        if (!$searchProfile) {
            return $query->where('status', 'active');
        }

        // Status filter
        if ($searchProfile->status_filter) {
            $query->where('status', $searchProfile->status_filter);
        }

        // Search text
        if ($searchProfile->search_text) {
            $term = $searchProfile->search_text;
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('sku', 'LIKE', "%{$term}%")
                  ->orWhere('ean', 'LIKE', "%{$term}%");
            });
        }

        // Category filter
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

        // Attribute filters
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
                        'gte' => '>=',
                        'lte' => '<=',
                        'gt' => '>',
                        'lt' => '<',
                        'contains' => 'LIKE',
                        'neq' => '!=',
                        default => '=',
                    };
                    $sqlValue = $operator === 'contains' ? "%{$value}%" : $value;
                    $q->where($column, $sqlOp, $sqlValue);
                });
            }
        }

        // Sort
        $sortField = $searchProfile->sort_field ?? 'name';
        $sortOrder = $searchProfile->sort_order ?? 'asc';
        $query->orderBy($sortField, $sortOrder);

        return $query;
    }

    /**
     * Determine which relations to eager-load based on template elements.
     */
    private function determineRelations(array $templateJson): array
    {
        $relations = ['productType', 'masterHierarchyNode'];

        $hasAttributes = false;
        $hasImages = false;

        $this->walkElements($templateJson, function (array $element) use (&$hasAttributes, &$hasImages) {
            if ($element['type'] === 'attribute') {
                $hasAttributes = true;
            }
            if ($element['type'] === 'image') {
                $hasImages = true;
            }
        });

        // Also check group fields for attribute-based grouping
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

        if ($hasImages) {
            $relations[] = 'mediaAssignments.media';
            $relations[] = 'mediaAssignments.usageType';
        }

        return $relations;
    }

    /**
     * Walk all elements in the template (header, detail, footer, nested groups).
     */
    private function walkElements(array $templateJson, callable $callback): void
    {
        // Page header/footer
        foreach (['pageHeader', 'pageFooter'] as $section) {
            foreach ($templateJson[$section]['elements'] ?? [] as $element) {
                $callback($element);
            }
        }

        // Groups
        $this->walkGroupElements($templateJson['groups'] ?? [], $callback);
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

    /**
     * Group products according to the template's group hierarchy.
     */
    private function groupProducts(Collection $products, array $templateJson, string $language): array
    {
        $groups = $templateJson['groups'] ?? [];

        if (empty($groups)) {
            // Provide a default group wrapping all products with the same structure
            // as grouped data to avoid missing-key errors in Blade/DocxWriter
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
                ];
                continue;
            }

            $grouped = $products->groupBy(fn (Product $p) => $this->elementRenderer->resolveGroupValue($p, $field, $language));

            // Sort groups
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
