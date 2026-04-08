<?php

declare(strict_types=1);

namespace App\Services\ApiDesigner;

use App\Models\ApiTemplate;
use App\Models\Product;
use App\Models\SearchProfile;
use App\Services\Report\ElementRenderer;
use App\Services\Search\SearchProfileQueryBuilder;
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
     * @return array{grouped: array, total: int, total_unfiltered: int, offset: int, limit: int|null}
     */
    public function collect(
        ApiTemplate $template,
        ?SearchProfile $searchProfile = null,
        ?int $limit = null,
        int $offset = 0,
    ): array {
        $query = $this->buildQuery($searchProfile);
        $total = (clone $query)->count();

        $relations = $this->determineRelations($template->template_json);

        $productQuery = $query->with($relations)->skip($offset);
        if ($limit !== null) {
            $productQuery->limit($limit);
        }
        $products = $productQuery->get();

        $grouped = $this->groupProducts($products, $template->template_json, $template->language ?? 'de');

        return [
            'grouped'  => $grouped,
            'total'    => $total,
            'count'    => $products->count(),
            'offset'   => $offset,
            'limit'    => $limit,
        ];
    }

    private function buildQuery(?SearchProfile $searchProfile): Builder
    {
        if (!$searchProfile) {
            return Product::query()->where('status', 'active');
        }

        return app(SearchProfileQueryBuilder::class)
            ->forProducts($searchProfile, mainProductsOnly: false, applySort: true);
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
