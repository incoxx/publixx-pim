<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\Product;
use App\Models\ReportTemplate;
use App\Models\SearchProfile;
use App\Services\Search\SearchProfileQueryBuilder;
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

    private function buildQuery(?SearchProfile $searchProfile): Builder
    {
        if (!$searchProfile) {
            return Product::query()->where('status', 'active');
        }

        return app(SearchProfileQueryBuilder::class)
            ->forProducts($searchProfile, mainProductsOnly: false, applySort: true);
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
            $relations[] = 'attributeValues.dictionaryEntry';
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
