<?php

declare(strict_types=1);

namespace App\Services\ExcelDesigner;

use App\Models\ExcelTemplate;
use App\Models\Product;
use App\Models\SearchProfile;
use App\Services\Report\ElementRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ExcelDataCollector
{
    public function __construct(
        private readonly ElementRenderer $elementRenderer,
    ) {}

    /**
     * Produkte sammeln und in die Sheet-Gruppenstruktur des Templates einordnen.
     *
     * @return array{grouped: array, total: int}
     */
    public function collect(ExcelTemplate $template, ?SearchProfile $searchProfile = null, ?int $limit = null): array
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

    /**
     * Gesamtanzahl der Produkte ermitteln (ohne Relations zu laden).
     */
    public function countProducts(ExcelTemplate $template, ?SearchProfile $searchProfile = null): int
    {
        return $this->buildQuery($searchProfile)->count();
    }

    /**
     * Produkte chunk-weise laden und gruppieren.
     * Spart Speicher bei großen Datenmengen.
     *
     * @param  callable|null  $onChunk  fn(int $chunkCount): bool — false = abbrechen
     * @return array{grouped: array, total: int}
     */
    public function collectChunked(
        ExcelTemplate $template,
        ?SearchProfile $searchProfile = null,
        int $chunkSize = 500,
        ?callable $onChunk = null,
    ): array {
        $query = $this->buildQuery($searchProfile);
        $relations = $this->determineRelations($template->template_json);

        $allProducts = new Collection();
        $cancelled = false;

        $query->with($relations)->chunkById($chunkSize, function (Collection $chunk) use (&$allProducts, &$cancelled, $onChunk) {
            $allProducts = $allProducts->concat($chunk);

            if ($onChunk) {
                $continue = $onChunk($chunk->count());
                if ($continue === false) {
                    $cancelled = true;
                    return false;
                }
            }
        });

        if ($cancelled) {
            return ['grouped' => [], 'total' => $allProducts->count()];
        }

        $grouped = $this->groupProducts($allProducts, $template->template_json, $template->language ?? 'de');

        return [
            'grouped' => $grouped,
            'total' => $allProducts->count(),
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

    /**
     * Benötigte Relationen anhand der Spalten-Typen bestimmen.
     */
    private function determineRelations(array $templateJson): array
    {
        $relations = ['productType', 'masterHierarchyNode'];
        $needs = [
            'attributes' => false, 'prices' => false, 'media' => false,
            'relations' => false, 'variants' => false,
            'parent' => false, 'manufacturer' => false,
        ];

        $this->walkColumns($templateJson['sheets'] ?? [], function (array $column) use (&$needs) {
            $type = $column['type'] ?? '';
            $field = $column['field'] ?? '';

            match ($type) {
                'attribute', 'collection' => $needs['attributes'] = true,
                'price' => $needs['prices'] = true,
                'image', 'media' => $needs['media'] = true,
                'relation' => $needs['relations'] = true,
                'variant' => $needs['variants'] = true,
                default => null,
            };

            // Erweiterte Stammfelder prüfen
            if ($type === 'field') {
                if (in_array($field, ['parent_sku', 'parent_name'])) {
                    $needs['parent'] = true;
                }
                if ($field === 'manufacturer') {
                    $needs['manufacturer'] = true;
                }
            }
        });

        // Gruppierungsfelder prüfen
        $this->walkSheets($templateJson['sheets'] ?? [], function (array $sheet) use (&$needs) {
            if (str_starts_with($sheet['field'] ?? '', 'attribute:')) {
                $needs['attributes'] = true;
            }
        });

        if ($needs['attributes']) {
            $relations[] = 'attributeValues.attribute';
            $relations[] = 'attributeValues.unit';
            $relations[] = 'attributeValues.valueListEntry';
            $relations[] = 'attributeValues.dictionaryEntry';
        }
        if ($needs['prices']) {
            $relations[] = 'prices.priceType';
        }
        if ($needs['media']) {
            $relations[] = 'media';
            $relations[] = 'mediaAssignments.usageType';
        }
        if ($needs['relations']) {
            $relations[] = 'outgoingRelations.targetProduct';
            $relations[] = 'outgoingRelations.relationType';
        }
        if ($needs['variants']) {
            $relations[] = 'variants';
        }
        if ($needs['parent']) {
            $relations[] = 'parentProduct';
        }
        if ($needs['manufacturer']) {
            $relations[] = 'manufacturer';
        }

        return $relations;
    }

    /**
     * Alle Spalten in allen Sheets durchlaufen.
     */
    private function walkColumns(array $sheets, callable $callback): void
    {
        foreach ($sheets as $sheet) {
            foreach ($sheet['columns'] ?? [] as $column) {
                $callback($column);
            }
            // Verschachtelte Sheets (Untergruppen)
            if (!empty($sheet['sheets'])) {
                $this->walkColumns($sheet['sheets'], $callback);
            }
        }
    }

    /**
     * Alle Sheet-Definitionen durchlaufen.
     */
    private function walkSheets(array $sheets, callable $callback): void
    {
        foreach ($sheets as $sheet) {
            $callback($sheet);
            if (!empty($sheet['sheets'])) {
                $this->walkSheets($sheet['sheets'], $callback);
            }
        }
    }

    /**
     * Produkte in die Gruppenstruktur einordnen.
     */
    private function groupProducts(Collection $products, array $templateJson, string $language): array
    {
        $sheets = $templateJson['sheets'] ?? [];

        if (empty($sheets)) {
            return [[
                'definition' => ['columns' => [], 'headerRows' => [], 'footerRows' => []],
                'label' => '',
                'value' => '',
                'products' => $products->all(),
                'subgroups' => [],
                'count' => $products->count(),
            ]];
        }

        return $this->buildGroupLevel($products, $sheets, $language);
    }

    private function buildGroupLevel(Collection $products, array $sheetDefs, string $language): array
    {
        if (empty($sheetDefs)) {
            return [];
        }

        $result = [];

        foreach ($sheetDefs as $sheetDef) {
            $field = $sheetDef['field'] ?? 'none';

            if ($field === 'none') {
                $result[] = [
                    'definition' => $sheetDef,
                    'label' => $sheetDef['label'] ?? '',
                    'value' => '',
                    'products' => $products->all(),
                    'subgroups' => !empty($sheetDef['sheets'])
                        ? $this->buildGroupLevel($products, $sheetDef['sheets'], $language)
                        : [],
                    'count' => $products->count(),
                ];
                continue;
            }

            $grouped = $products->groupBy(
                fn (Product $p) => $this->elementRenderer->resolveGroupValue($p, $field, $language)
            );

            $sortOrder = $sheetDef['sortOrder'] ?? 'asc';
            $grouped = $sortOrder === 'desc' ? $grouped->sortKeysDesc() : $grouped->sortKeys();

            foreach ($grouped as $groupValue => $groupProducts) {
                $subgroups = [];
                if (!empty($sheetDef['sheets'])) {
                    $subgroups = $this->buildGroupLevel($groupProducts, $sheetDef['sheets'], $language);
                }

                $result[] = [
                    'definition' => $sheetDef,
                    'label' => $sheetDef['label'] ?? '',
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
