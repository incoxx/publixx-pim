<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\AttributeView;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use App\Models\ProductRelation;
use App\Models\ProductRelationType;
use App\Models\ProductType;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Exportiert PIM-Daten als gut lesbares JSON im Import-kompatiblen Format.
 *
 * Die Reihenfolge der Sektionen folgt den Abhängigkeiten:
 *   1. Einheiten (keine Abhängigkeiten)
 *   2. Attributsichten (keine Abhängigkeiten)
 *   3. Attributgruppen (keine Abhängigkeiten)
 *   4. Wertelisten (keine Abhängigkeiten)
 *   5. Attribute (abhängig von Gruppen, Wertelisten, Einheiten, Sichten)
 *   6. Produkttypen (keine Abhängigkeiten)
 *   7. Preisarten (keine Abhängigkeiten)
 *   8. Beziehungstypen (keine Abhängigkeiten)
 *   9. Hierarchien + Knoten (keine Abhängigkeiten)
 *  10. Hierarchie-Attribut-Zuordnungen (abhängig von Hierarchien, Attributen)
 *  11. Produkte (abhängig von Produkttypen)
 *  12. Produktwerte (abhängig von Produkten, Attributen)
 *  13. Varianten (abhängig von Produkten)
 *  14. Produkt-Hierarchie-Zuordnungen (abhängig von Produkten, Hierarchien)
 *  15. Produktbeziehungen (abhängig von Produkten)
 *  16. Preise (abhängig von Produkten, Preisarten)
 *  17. Medien-Zuordnungen (abhängig von Produkten)
 */
class JsonFormatExporter
{
    /** Sektionen in Abhängigkeitsreihenfolge. */
    private const array SECTION_ORDER = [
        'unit_groups',
        'units',
        'attribute_views',
        'attribute_groups',
        'value_lists',
        'attributes',
        'product_types',
        'price_types',
        'relation_types',
        'hierarchies',
        'hierarchy_attribute_assignments',
        'products',
        'product_attribute_values',
        'variants',
        'product_hierarchy_assignments',
        'product_relations',
        'prices',
        'media_assignments',
    ];

    /** Vorgeladene Hierarchie-Knoten (Cache). */
    private array $nodesByHierarchy = [];

    /**
     * Exportiert alle PIM-Daten als JSON-String.
     *
     * @param  array  $sections  Zu exportierende Sektionen (leer = alle)
     * @param  array  $filters   Filter für den Produkt-Export
     * @return string JSON-String (pretty-printed)
     */
    public function export(array $sections = [], array $filters = []): string
    {
        $startTime = microtime(true);
        Log::channel('export')->info('JSON-Export gestartet', [
            'sections' => $sections ?: 'alle',
            'filters' => $filters,
        ]);

        $activeSections = empty($sections) ? self::SECTION_ORDER : array_intersect(self::SECTION_ORDER, $sections);
        $data = $this->buildExportData($activeSections, $filters);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $duration = round(microtime(true) - $startTime, 2);
        Log::channel('export')->info("JSON-Export abgeschlossen in {$duration}s", [
            'sections' => count($activeSections),
            'size_bytes' => strlen($json),
        ]);

        return $json;
    }

    /**
     * Exportiert in eine Datei.
     */
    public function exportToFile(string $outputPath, array $sections = [], array $filters = []): string
    {
        $json = $this->export($sections, $filters);
        file_put_contents($outputPath, $json);

        Log::channel('export')->info("JSON-Export geschrieben nach: {$outputPath}");

        return $outputPath;
    }

    /**
     * Gibt die verfügbaren Sektionsnamen zurück.
     *
     * @return string[]
     */
    public static function availableSections(): array
    {
        return self::SECTION_ORDER;
    }

    /**
     * Baut das komplette Export-Array auf.
     */
    private function buildExportData(array $sections, array $filters): array
    {
        $data = [
            '_meta' => [
                'format' => 'publixx-pim-json',
                'version' => '1.0',
                'exported_at' => now()->toIso8601String(),
                'sections' => array_values($sections),
            ],
        ];

        foreach (self::SECTION_ORDER as $section) {
            if (!in_array($section, $sections)) {
                continue;
            }

            $startTime = microtime(true);
            $sectionData = $this->exportSection($section, $filters);
            $duration = round(microtime(true) - $startTime, 3);

            $data[$section] = $sectionData;

            Log::channel('export')->debug("Sektion '{$section}': " . count($sectionData) . " Einträge ({$duration}s)");
        }

        return $data;
    }

    /**
     * Exportiert eine einzelne Sektion.
     */
    private function exportSection(string $section, array $filters): array
    {
        return match ($section) {
            'unit_groups' => $this->exportUnitGroups(),
            'units' => $this->exportUnits(),
            'attribute_views' => $this->exportAttributeViews(),
            'attribute_groups' => $this->exportAttributeGroups(),
            'value_lists' => $this->exportValueLists(),
            'attributes' => $this->exportAttributes(),
            'product_types' => $this->exportProductTypes(),
            'price_types' => $this->exportPriceTypes(),
            'relation_types' => $this->exportRelationTypes(),
            'hierarchies' => $this->exportHierarchies(),
            'hierarchy_attribute_assignments' => $this->exportHierarchyAttributeAssignments(),
            'products' => $this->exportProducts($filters),
            'product_attribute_values' => $this->exportProductAttributeValues($filters),
            'variants' => $this->exportVariants($filters),
            'product_hierarchy_assignments' => $this->exportProductHierarchyAssignments($filters),
            'product_relations' => $this->exportProductRelations($filters),
            'prices' => $this->exportPrices($filters),
            'media_assignments' => $this->exportMediaAssignments($filters),
            default => [],
        };
    }

    // ─── Stammdaten (ohne Abhängigkeiten) ──────────────────────

    private function exportUnitGroups(): array
    {
        return UnitGroup::query()
            ->orderBy('technical_name')
            ->get()
            ->map(fn (UnitGroup $g) => [
                'technical_name' => $g->technical_name,
                'name_de' => $g->name_de,
                'name_en' => $g->name_en,
            ])
            ->toArray();
    }

    private function exportUnits(): array
    {
        return Unit::query()
            ->with('unitGroup')
            ->orderBy('unit_group_id')
            ->orderBy('technical_name')
            ->get()
            ->map(fn (Unit $u) => [
                'technical_name' => $u->technical_name,
                'abbreviation' => $u->abbreviation,
                'unit_group' => $u->unitGroup?->technical_name,
                'conversion_factor' => $u->conversion_factor,
                'is_base_unit' => $u->is_base_unit,
            ])
            ->toArray();
    }

    private function exportAttributeViews(): array
    {
        return AttributeView::query()
            ->orderBy('technical_name')
            ->get()
            ->map(fn (AttributeView $v) => [
                'technical_name' => $v->technical_name,
                'name_de' => $v->name_de,
                'name_en' => $v->name_en,
                'description' => $v->description,
            ])
            ->toArray();
    }

    private function exportAttributeGroups(): array
    {
        return AttributeType::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (AttributeType $g) => [
                'technical_name' => $g->technical_name,
                'name_de' => $g->name_de,
                'name_en' => $g->name_en,
                'description' => $g->description,
                'sort_order' => $g->sort_order,
            ])
            ->toArray();
    }

    private function exportValueLists(): array
    {
        return ValueList::query()
            ->with(['entries' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('technical_name')
            ->get()
            ->map(fn (ValueList $list) => [
                'technical_name' => $list->technical_name,
                'name_de' => $list->name_de,
                'name_en' => $list->name_en,
                'entries' => $list->entries->map(fn (ValueListEntry $e) => [
                    'technical_name' => $e->technical_name,
                    'display_value_de' => $e->display_value_de,
                    'display_value_en' => $e->display_value_en,
                    'sort_order' => $e->sort_order,
                ])->toArray(),
            ])
            ->toArray();
    }

    private function exportAttributes(): array
    {
        return Attribute::query()
            ->with(['attributeType', 'valueList', 'unitGroup', 'defaultUnit', 'parentAttribute', 'attributeViews'])
            ->orderBy('technical_name')
            ->get()
            ->map(fn (Attribute $a) => array_filter([
                'technical_name' => $a->technical_name,
                'name_de' => $a->name_de,
                'name_en' => $a->name_en,
                'description' => $a->description_de,
                'data_type' => $a->data_type,
                'attribute_group' => $a->attributeType?->technical_name,
                'value_list' => $a->valueList?->technical_name,
                'unit_group' => $a->unitGroup?->technical_name,
                'default_unit' => $a->defaultUnit?->technical_name,
                'is_multipliable' => $a->is_multipliable ?: null,
                'max_multiplied' => $a->max_multiplied,
                'is_translatable' => $a->is_translatable ?: null,
                'is_mandatory' => $a->is_mandatory ?: null,
                'is_unique' => $a->is_unique ?: null,
                'is_searchable' => $a->is_searchable ?: null,
                'is_inheritable' => $a->is_inheritable ?: null,
                'parent_attribute' => $a->parentAttribute?->technical_name,
                'source_system' => $a->source_system,
                'views' => $a->attributeViews->pluck('technical_name')->values()->toArray() ?: null,
            ], fn ($v) => $v !== null))
            ->toArray();
    }

    private function exportProductTypes(): array
    {
        return ProductType::query()
            ->orderBy('technical_name')
            ->get()
            ->map(fn (ProductType $t) => [
                'technical_name' => $t->technical_name,
                'name_de' => $t->name_de,
                'name_en' => $t->name_en,
                'description' => $t->description,
                'has_variants' => $t->has_variants,
                'has_ean' => $t->has_ean,
                'has_prices' => $t->has_prices,
                'has_media' => $t->has_media,
            ])
            ->toArray();
    }

    private function exportPriceTypes(): array
    {
        return PriceType::query()
            ->orderBy('technical_name')
            ->get()
            ->map(fn (PriceType $p) => [
                'technical_name' => $p->technical_name,
                'name_de' => $p->name_de,
                'name_en' => $p->name_en,
                'description' => $p->description,
            ])
            ->toArray();
    }

    private function exportRelationTypes(): array
    {
        return ProductRelationType::query()
            ->orderBy('technical_name')
            ->get()
            ->map(fn (ProductRelationType $r) => [
                'technical_name' => $r->technical_name,
                'name_de' => $r->name_de,
                'name_en' => $r->name_en,
                'is_bidirectional' => $r->is_bidirectional,
            ])
            ->toArray();
    }

    // ─── Hierarchien ───────────────────────────────────────────

    private function exportHierarchies(): array
    {
        $this->loadNodeCache();

        return Hierarchy::query()
            ->orderBy('technical_name')
            ->get()
            ->map(function (Hierarchy $h) {
                $nodes = $this->nodesByHierarchy[$h->id] ?? collect();
                return [
                    'technical_name' => $h->technical_name,
                    'name_de' => $h->name_de,
                    'name_en' => $h->name_en,
                    'hierarchy_type' => $h->hierarchy_type,
                    'nodes' => $nodes
                        ->filter(fn (HierarchyNode $n) => $n->depth > 0)
                        ->sortBy(['depth', 'sort_order'])
                        ->values()
                        ->map(fn (HierarchyNode $n) => [
                            'path' => $this->buildReadablePath($n, $nodes),
                            'name_de' => $n->name_de,
                            'name_en' => $n->name_en,
                            'depth' => $n->depth,
                            'sort_order' => $n->sort_order,
                        ])
                        ->toArray(),
                ];
            })
            ->toArray();
    }

    private function exportHierarchyAttributeAssignments(): array
    {
        $this->loadNodeCache();

        $result = [];

        HierarchyNodeAttributeAssignment::query()
            ->with(['hierarchyNode.hierarchy', 'attribute'])
            ->chunk(500, function ($assignments) use (&$result) {
                foreach ($assignments as $a) {
                    $node = $a->hierarchyNode;
                    if (!$node) {
                        continue;
                    }
                    $nodes = $this->nodesByHierarchy[$node->hierarchy_id] ?? collect();
                    $result[] = [
                        'hierarchy' => $node->hierarchy?->technical_name,
                        'node_path' => $this->buildReadablePath($node, $nodes),
                        'attribute' => $a->attribute?->technical_name,
                        'collection_name' => $a->collection_name,
                        'collection_sort' => $a->collection_sort,
                        'attribute_sort' => $a->attribute_sort,
                        'dont_inherit' => $a->dont_inherit,
                    ];
                }
            });

        return $result;
    }

    // ─── Produkte und abhängige Daten ──────────────────────────

    private function exportProducts(array $filters): array
    {
        $result = [];

        $this->buildProductQuery($filters)
            ->with('productType')
            ->orderBy('sku')
            ->chunk(500, function ($products) use (&$result) {
                foreach ($products as $product) {
                    $result[] = [
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'name_en' => $this->getProductNameEn($product),
                        'product_type' => $product->productType?->technical_name,
                        'ean' => $product->ean,
                        'status' => $product->status,
                    ];
                }
            });

        return $result;
    }

    private function exportProductAttributeValues(array $filters): array
    {
        $result = [];
        $productIds = $this->getFilteredProductIds($filters);

        $query = ProductAttributeValue::query()
            ->with(['product', 'attribute', 'unit', 'valueListEntry']);

        if ($productIds !== null) {
            $query->whereIn('product_id', $productIds);
        }

        $query->chunk(500, function ($values) use (&$result) {
            foreach ($values as $pav) {
                if (!$pav->product || !$pav->attribute) {
                    continue;
                }
                $result[] = array_filter([
                    'sku' => $pav->product->sku,
                    'attribute' => $pav->attribute->technical_name,
                    'value' => $this->resolveAttributeValue($pav),
                    'unit' => $pav->unit?->abbreviation,
                    'language' => $pav->language,
                    'index' => $pav->multiplied_index,
                ], fn ($v) => $v !== null);
            }
        });

        return $result;
    }

    private function exportVariants(array $filters): array
    {
        $result = [];
        $parentIds = $this->getFilteredProductIds($filters);

        $query = Product::query()
            ->where('product_type_ref', 'variant')
            ->with('parentProduct')
            ->orderBy('sku');

        if ($parentIds !== null) {
            $query->whereIn('parent_product_id', $parentIds);
        }

        $query->chunk(500, function ($variants) use (&$result) {
            foreach ($variants as $v) {
                $result[] = [
                    'parent_sku' => $v->parentProduct?->sku,
                    'sku' => $v->sku,
                    'name' => $v->name,
                    'name_en' => $this->getProductNameEn($v),
                    'ean' => $v->ean,
                    'status' => $v->status,
                ];
            }
        });

        return $result;
    }

    private function exportProductHierarchyAssignments(array $filters): array
    {
        $this->loadNodeCache();
        $result = [];
        $productIds = $this->getFilteredProductIds($filters);

        // Master-Hierarchie
        $masterQuery = Product::query()
            ->whereNotNull('master_hierarchy_node_id')
            ->with('masterHierarchyNode.hierarchy')
            ->orderBy('sku');

        if ($productIds !== null) {
            $masterQuery->whereIn('id', $productIds);
        }

        $masterQuery->chunk(500, function ($products) use (&$result) {
            foreach ($products as $product) {
                $node = $product->masterHierarchyNode;
                if (!$node) {
                    continue;
                }
                $nodes = $this->nodesByHierarchy[$node->hierarchy_id] ?? collect();
                $result[] = [
                    'sku' => $product->sku,
                    'hierarchy' => $node->hierarchy?->technical_name,
                    'node_path' => $this->buildReadablePath($node, $nodes),
                ];
            }
        });

        // Output-Hierarchien
        $outputQuery = OutputHierarchyProductAssignment::query()
            ->with(['product', 'hierarchyNode.hierarchy']);

        if ($productIds !== null) {
            $outputQuery->whereIn('product_id', $productIds);
        }

        $outputQuery->chunk(500, function ($assignments) use (&$result) {
            foreach ($assignments as $a) {
                if (!$a->product || !$a->hierarchyNode) {
                    continue;
                }
                $node = $a->hierarchyNode;
                $nodes = $this->nodesByHierarchy[$node->hierarchy_id] ?? collect();
                $result[] = [
                    'sku' => $a->product->sku,
                    'hierarchy' => $node->hierarchy?->technical_name,
                    'node_path' => $this->buildReadablePath($node, $nodes),
                ];
            }
        });

        return $result;
    }

    private function exportProductRelations(array $filters): array
    {
        $result = [];
        $productIds = $this->getFilteredProductIds($filters);

        $query = ProductRelation::query()
            ->with(['sourceProduct', 'targetProduct', 'relationType']);

        if ($productIds !== null) {
            $query->whereIn('source_product_id', $productIds);
        }

        $query->chunk(500, function ($relations) use (&$result) {
            foreach ($relations as $r) {
                if (!$r->sourceProduct || !$r->targetProduct) {
                    continue;
                }
                $result[] = [
                    'source_sku' => $r->sourceProduct->sku,
                    'target_sku' => $r->targetProduct->sku,
                    'relation_type' => $r->relationType?->technical_name,
                    'sort_order' => $r->sort_order,
                ];
            }
        });

        return $result;
    }

    private function exportPrices(array $filters): array
    {
        $result = [];
        $productIds = $this->getFilteredProductIds($filters);

        $query = ProductPrice::query()->with(['product', 'priceType']);

        if ($productIds !== null) {
            $query->whereIn('product_id', $productIds);
        }

        $query->chunk(500, function ($prices) use (&$result) {
            foreach ($prices as $p) {
                if (!$p->product) {
                    continue;
                }
                $result[] = array_filter([
                    'sku' => $p->product->sku,
                    'price_type' => $p->priceType?->technical_name,
                    'amount' => $p->amount,
                    'currency' => $p->currency,
                    'valid_from' => $p->valid_from?->format('Y-m-d'),
                    'valid_to' => $p->valid_to?->format('Y-m-d'),
                    'country' => $p->country,
                    'scale_from' => $p->scale_from,
                    'scale_to' => $p->scale_to,
                ], fn ($v) => $v !== null);
            }
        });

        return $result;
    }

    private function exportMediaAssignments(array $filters): array
    {
        $result = [];
        $productIds = $this->getFilteredProductIds($filters);

        $query = ProductMediaAssignment::query()->with(['product', 'media', 'usageType']);

        if ($productIds !== null) {
            $query->whereIn('product_id', $productIds);
        }

        $query->chunk(500, function ($assignments) use (&$result) {
            foreach ($assignments as $a) {
                if (!$a->product || !$a->media) {
                    continue;
                }
                $result[] = array_filter([
                    'sku' => $a->product->sku,
                    'file_name' => $a->media->file_name,
                    'media_type' => $a->media->media_type,
                    'usage_type' => $a->usageType?->technical_name,
                    'title_de' => $a->media->title_de,
                    'title_en' => $a->media->title_en,
                    'alt_text_de' => $a->media->alt_text_de,
                    'sort_order' => $a->sort_order,
                    'is_primary' => $a->is_primary ?: null,
                ], fn ($v) => $v !== null);
            }
        });

        return $result;
    }

    // ─── Filter-Hilfsmethoden ──────────────────────────────────

    /**
     * Baut die Produkt-Query basierend auf den Filtern.
     * Unterstützt: status, hierarchy_node, hierarchy_path, updated_after, search_text, product_type
     */
    private function buildProductQuery(array $filters): Builder
    {
        $query = Product::query()->where('product_type_ref', 'product');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['product_type'])) {
            $query->whereHas('productType', fn (Builder $q) => $q->where('technical_name', $filters['product_type']));
        }

        if (isset($filters['hierarchy_node'])) {
            $query->where('master_hierarchy_node_id', $filters['hierarchy_node']);
        }

        if (isset($filters['hierarchy_path'])) {
            $path = $filters['hierarchy_path'];
            $query->whereHas('masterHierarchyNode', fn (Builder $q) => $q->where('path', 'LIKE', "%{$path}%"));
        }

        if (isset($filters['updated_after'])) {
            $query->where('updated_at', '>=', $filters['updated_after']);
        }

        if (isset($filters['search_text'])) {
            $like = '%' . $filters['search_text'] . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'LIKE', $like)
                  ->orWhere('sku', 'LIKE', $like)
                  ->orWhere('ean', 'LIKE', $like);
            });
        }

        if (isset($filters['skus']) && is_array($filters['skus'])) {
            $query->whereIn('sku', $filters['skus']);
        }

        if (isset($filters['category_ids']) && is_array($filters['category_ids'])) {
            $query->whereIn('master_hierarchy_node_id', $filters['category_ids']);
        }

        return $query;
    }

    /**
     * Gibt Produkt-IDs zurück, die den Filtern entsprechen.
     * null = kein Filter → alle Produkte.
     */
    private function getFilteredProductIds(array $filters): ?array
    {
        if (empty($filters)) {
            return null;
        }

        return $this->buildProductQuery($filters)->pluck('id')->toArray();
    }

    // ─── Hierarchie-Hilfsmethoden ──────────────────────────────

    private function loadNodeCache(): void
    {
        if (!empty($this->nodesByHierarchy)) {
            return;
        }

        $this->nodesByHierarchy = HierarchyNode::all()
            ->groupBy('hierarchy_id')
            ->all();
    }

    private function buildReadablePath(HierarchyNode $node, Collection $allNodes): string
    {
        $segments = [];
        $current = $node;

        while ($current && $current->depth > 0) {
            array_unshift($segments, $current->name_de);
            $parentId = $current->parent_node_id;
            $current = $parentId ? $allNodes->firstWhere('id', $parentId) : null;
        }

        return empty($segments) ? '/' : '/' . implode('/', $segments) . '/';
    }

    // ─── Wert-Hilfsmethoden ────────────────────────────────────

    private function resolveAttributeValue(ProductAttributeValue $pav): mixed
    {
        if ($pav->value_selection_id !== null) {
            if ($pav->value_string !== null && $pav->value_string !== '') {
                return $pav->value_string;
            }
            $entry = $pav->valueListEntry ?? ValueListEntry::find($pav->value_selection_id);
            return $entry?->technical_name;
        }

        if ($pav->value_string !== null && $pav->value_string !== '') {
            return $pav->value_string;
        }

        if ($pav->value_number !== null) {
            return $pav->value_number;
        }

        if ($pav->value_date !== null) {
            return $pav->value_date->format('Y-m-d');
        }

        if ($pav->value_flag !== null) {
            return $pav->value_flag;
        }

        return null;
    }

    private function getProductNameEn(Product $product): ?string
    {
        return ProductAttributeValue::query()
            ->where('product_id', $product->id)
            ->where('language', 'en')
            ->whereHas('attribute', fn ($q) => $q->where('technical_name', 'name'))
            ->value('value_string');
    }
}
