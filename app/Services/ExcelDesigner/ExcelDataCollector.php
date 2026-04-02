<?php

declare(strict_types=1);

namespace App\Services\ExcelDesigner;

use App\Models\ExcelTemplate;
use App\Models\Product;
use App\Models\SearchProfile;
use App\Services\Report\ElementRenderer;
use App\Services\Search\SearchProfileQueryBuilder;
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
     * Produkte chunk-weise streamen, ohne sie im RAM zu akkumulieren.
     * Für nicht-gruppierte Exports — jeder Chunk wird direkt verarbeitet und freigegeben.
     *
     * @param  callable  $callback  fn(Collection $products): bool — false = abbrechen
     */
    public function streamProducts(
        ExcelTemplate $template,
        ?SearchProfile $searchProfile = null,
        int $chunkSize = 500,
        callable $callback = null,
    ): void {
        $query = $this->buildQuery($searchProfile);
        $relations = $this->determineRelations($template->template_json);

        $query->with($relations)->chunkById($chunkSize, function ($chunk) use ($callback) {
            if ($callback) {
                $continue = $callback($chunk);
                if ($continue === false) {
                    return false;
                }
            }
        });
    }

    /**
     * Produkte chunk-weise laden und gruppieren.
     * Für gruppierte Exports — Produkte müssen im RAM bleiben für groupBy.
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
        if (!$searchProfile) {
            return Product::query()->where('status', 'active');
        }

        return app(SearchProfileQueryBuilder::class)
            ->forProducts($searchProfile, mainProductsOnly: false, applySort: true);
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
