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
     * @return array{grouped: array, total: int, count: int, offset: int, limit: int|null}
     */
    public function collect(
        ApiTemplate $template,
        ?SearchProfile $searchProfile = null,
        ?int $limit = null,
        int $offset = 0,
        ?\DateTimeInterface $since = null,
        ?string $sortField = null,
        ?string $sortOrder = null,
        ?string $language = null,
    ): array {
        $lang = $language ?? $template->language ?? 'de';

        $query = $this->buildQuery($searchProfile, $since, $sortField, $sortOrder, $lang);
        $total = (clone $query)->count();

        $relations = $this->determineRelations($template->template_json);

        $productQuery = $query->with($relations);
        if ($limit !== null) {
            // MySQL erlaubt kein OFFSET ohne LIMIT
            $productQuery->skip($offset)->limit($limit);
        }
        $products = $productQuery->get();

        $grouped = $this->groupProducts($products, $template->template_json, $lang);

        return [
            'grouped'  => $grouped,
            'total'    => $total,
            'count'    => $products->count(),
            'offset'   => $offset,
            'limit'    => $limit,
        ];
    }

    /**
     * Wie collect(), aber für eine vorgegebene Liste von Produkt-IDs (z.B. aus der MCP-Suche).
     * Reihenfolge der IDs wird beibehalten.
     *
     * @param  string[] $productIds
     * @return array{grouped: array, total: int, count: int, offset: int, limit: int|null}
     */
    public function collectByIds(ApiTemplate $template, array $productIds, ?string $language = null): array
    {
        if (empty($productIds)) {
            return ['grouped' => [], 'total' => 0, 'count' => 0, 'offset' => 0, 'limit' => null];
        }

        $lang      = $language ?? $template->language ?? 'de';
        $relations = $this->determineRelations($template->template_json);

        // Reihenfolge aus $productIds beibehalten via FIELD()-Sort (MySQL) oder PHP-Sort
        $products = Product::with($relations)
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy(fn ($p) => array_search($p->id, $productIds, true))
            ->values();

        $grouped = $this->groupProducts($products, $template->template_json, $lang);

        return [
            'grouped' => $grouped,
            'total'   => count($productIds),
            'count'   => $products->count(),
            'offset'  => 0,
            'limit'   => null,
        ];
    }

    /** Erlaubte Sortierfelder (direkte Produktspalten). */
    private const SORT_FIELDS = ['sku', 'name', 'status', 'created_at', 'updated_at'];

    private function buildQuery(
        ?SearchProfile $searchProfile,
        ?\DateTimeInterface $since = null,
        ?string $sortField = null,
        ?string $sortOrder = null,
        string $language = 'de',
    ): Builder {
        // Basis-Query
        if ($searchProfile) {
            // SearchProfile-Sort nur übernehmen wenn kein Override
            $applyProfileSort = ($sortField === null);
            $query = app(SearchProfileQueryBuilder::class)
                ->forProducts($searchProfile, language: $language, mainProductsOnly: false, applySort: $applyProfileSort);
        } else {
            $query = Product::query()->where('status', 'active');
        }

        // Delta-Sync: nur Produkte ab Timestamp
        if ($since !== null) {
            $query->where('products.updated_at', '>=', $since);
        }

        // Sort-Override
        if ($sortField !== null && in_array($sortField, self::SORT_FIELDS, true)) {
            $direction = strtolower($sortOrder ?? 'asc') === 'desc' ? 'desc' : 'asc';
            $query->reorder()->orderBy('products.' . $sortField, $direction);
        }

        return $query;
    }

    private function determineRelations(array $templateJson): array
    {
        $relations = ['productType', 'masterHierarchyNode'];
        $hasAttributes = false;
        $hasPrices     = false;
        $hasMedia      = false;
        $hasRelations  = false;

        $this->walkGroupElements($templateJson['groups'] ?? [], function (array $element) use (
            &$hasAttributes, &$hasPrices, &$hasMedia, &$hasRelations
        ) {
            match ($element['type'] ?? '') {
                'attribute' => $hasAttributes = true,
                'price'     => $hasPrices     = true,
                'media'     => $hasMedia      = true,
                'relation'  => $hasRelations  = true,
                default     => null,
            };
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
        if ($hasPrices) {
            $relations[] = 'prices';
        }
        if ($hasMedia) {
            $relations[] = 'mediaAssignments.media';
        }
        if ($hasRelations) {
            $relations[] = 'outgoingRelations.targetProduct';
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
