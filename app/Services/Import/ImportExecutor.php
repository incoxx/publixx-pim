<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\Media;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Schreibt validierte Import-Daten in die Datenbank.
 * Upsert-Logik: existierende Einträge werden aktualisiert, neue angelegt.
 */
class ImportExecutor
{
    private readonly ReferenceResolver $resolver;

    /** Zähler für das Import-Ergebnis. */
    private array $stats = [];

    /** Gesammelte Produkt-IDs (für ImportCompleted-Event). */
    private array $affectedProductIds = [];

    /** Übersprungene Zeilen mit Grund (für Ergebnis-Report). */
    private array $skippedDetails = [];

    public function __construct(?ReferenceResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new ReferenceResolver();
    }

    /**
     * Führt den Import für alle Sheets durch.
     * Die Reihenfolge ist wichtig wegen Abhängigkeiten.
     *
     * @param ParseResult $parseResult  Geparste Daten
     * @return ImportExecutionResult
     */
    public function execute(ParseResult $parseResult): ImportExecutionResult
    {
        $this->stats = [];
        $this->affectedProductIds = [];
        $this->skippedDetails = [];

        // Reihenfolge gemäß Abhängigkeiten (Spezifikation)
        $sheetOrder = [
            '01_Produkttypen',
            '02_Attributgruppen',
            '03_Einheiten',
            '04_Wertelisten',
            '05_Attribute',
            '06_Hierarchien',
            '07_Hierarchie_Attribute',
            '08_Produkte',
            '09_Produktwerte',
            '10_Varianten',
            '11_Produkt_Hierarchien',
            '12_Produktbeziehungen',
            '13_Preise',
            '14_Medien',
        ];

        DB::beginTransaction();

        try {
            foreach ($sheetOrder as $sheetKey) {
                if (!$parseResult->hasSheet($sheetKey)) {
                    continue;
                }

                $rows = $parseResult->getSheetData($sheetKey);
                if (empty($rows)) {
                    continue;
                }

                $this->stats[$sheetKey] = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

                Log::channel('import')->info("Importiere Sheet: {$sheetKey}", ['rows' => count($rows)]);

                // Nach Import bestimmter Sheets den Resolver-Cache leeren,
                // damit nachfolgende Sheets die neuen Einträge finden.
                $method = match ($sheetKey) {
                    '01_Produkttypen' => 'importProductTypes',
                    '02_Attributgruppen' => 'importAttributeTypes',
                    '03_Einheiten' => 'importUnits',
                    '04_Wertelisten' => 'importValueLists',
                    '05_Attribute' => 'importAttributes',
                    '06_Hierarchien' => 'importHierarchies',
                    '07_Hierarchie_Attribute' => 'importHierarchyAttributes',
                    '08_Produkte' => 'importProducts',
                    '09_Produktwerte' => 'importProductValues',
                    '10_Varianten' => 'importVariants',
                    '11_Produkt_Hierarchien' => 'importProductHierarchies',
                    '12_Produktbeziehungen' => 'importProductRelations',
                    '13_Preise' => 'importPrices',
                    '14_Medien' => 'importMedia',
                    default => null,
                };

                if ($method && method_exists($this, $method)) {
                    $this->{$method}($rows, $sheetKey);
                }

                // Cache nach Stammdaten-Import leeren
                if (in_array($sheetKey, [
                    '01_Produkttypen', '02_Attributgruppen', '03_Einheiten',
                    '04_Wertelisten', '05_Attribute', '06_Hierarchien', '08_Produkte',
                ])) {
                    $this->resolver->clearCache();
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return new ImportExecutionResult(
            stats: $this->stats,
            affectedProductIds: array_unique($this->affectedProductIds),
            skippedDetails: $this->skippedDetails,
        );
    }

    /**
     * Gibt die betroffenen Produkt-IDs zurück.
     */
    public function getAffectedProductIds(): array
    {
        return array_unique($this->affectedProductIds);
    }

    /**
     * Protokolliert und speichert eine übersprungene Zeile.
     */
    private function logSkipped(string $sheetKey, array $row, string $reason): void
    {
        $this->stats[$sheetKey]['skipped']++;

        $rowNum = $row['_row'] ?? '?';
        $detail = [
            'sheet' => $sheetKey,
            'row' => $rowNum,
            'reason' => $reason,
        ];
        $this->skippedDetails[] = $detail;

        Log::channel('import')->warning("Zeile {$rowNum} übersprungen in {$sheetKey}: {$reason}", [
            'data' => array_filter($row, fn ($k) => $k !== '_row', ARRAY_FILTER_USE_KEY),
        ]);
    }

    /**
     * Protokolliert einen Fehler bei der Verarbeitung einer Zeile.
     */
    private function logRowError(string $sheetKey, array $row, \Throwable $e): void
    {
        $this->stats[$sheetKey]['errors']++;

        $rowNum = $row['_row'] ?? '?';
        $this->skippedDetails[] = [
            'sheet' => $sheetKey,
            'row' => $rowNum,
            'reason' => 'Fehler: ' . $e->getMessage(),
        ];

        Log::channel('import')->error("Fehler in {$sheetKey} Zeile {$rowNum}: {$e->getMessage()}", [
            'data' => array_filter($row, fn ($k) => $k !== '_row', ARRAY_FILTER_USE_KEY),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    // ──────────────────────────────────────────────
    //  Sheet-spezifische Import-Methoden
    // ──────────────────────────────────────────────

    private function importProductTypes(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
                $existing = ProductType::where('technical_name', $row['technical_name'])->first();

                $data = [
                    'technical_name' => $row['technical_name'],
                    'name_de' => $row['name_de'],
                    'name_en' => $row['name_en'] ?? null,
                    'description' => $row['description'] ?? null,
                    'has_variants' => $this->toBool($row['has_variants'] ?? null),
                    'has_ean' => $this->toBool($row['has_ean'] ?? null),
                    'has_prices' => $this->toBool($row['has_prices'] ?? null),
                    'has_media' => $this->toBool($row['has_media'] ?? null),
                ];

                if ($existing) {
                    $existing->update($data);
                    $this->stats[$sheetKey]['updated']++;
                } else {
                    $data['id'] = Str::uuid()->toString();
                    ProductType::create($data);
                    $this->stats[$sheetKey]['created']++;
                }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    private function importAttributeTypes(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
                $existing = AttributeType::where('technical_name', $row['technical_name'])->first();

                $data = [
                    'technical_name' => $row['technical_name'],
                    'name_de' => $row['name_de'],
                    'name_en' => $row['name_en'] ?? null,
                    'description' => $row['description'] ?? null,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ];

                if ($existing) {
                    $existing->update($data);
                    $this->stats[$sheetKey]['updated']++;
                } else {
                    $data['id'] = Str::uuid()->toString();
                    AttributeType::create($data);
                    $this->stats[$sheetKey]['created']++;
                }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    private function importUnits(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
                // Erst die Gruppe anlegen/finden
                $group = UnitGroup::firstOrCreate(
                    ['technical_name' => $row['group_technical_name']],
                    [
                        'id' => Str::uuid()->toString(),
                        'technical_name' => $row['group_technical_name'],
                        'name_de' => $row['group_name_de'],
                    ]
                );

                // Dann die Einheit
                $existing = Unit::where('technical_name', $row['technical_name'])
                    ->where('unit_group_id', $group->id)
                    ->first();

                $data = [
                    'unit_group_id' => $group->id,
                    'technical_name' => $row['technical_name'],
                    'abbreviation' => $row['abbreviation'],
                    'conversion_factor' => $row['conversion_factor'] ?? 1,
                    'is_base_unit' => $this->toBool($row['is_base_unit'] ?? false),
                ];

                if ($existing) {
                    $existing->update($data);
                    $this->stats[$sheetKey]['updated']++;
                } else {
                    $data['id'] = Str::uuid()->toString();
                    Unit::create($data);
                    $this->stats[$sheetKey]['created']++;
                }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    private function importValueLists(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
                // Liste anlegen/finden
                $list = ValueList::firstOrCreate(
                    ['technical_name' => $row['list_technical_name']],
                    [
                        'id' => Str::uuid()->toString(),
                        'technical_name' => $row['list_technical_name'],
                        'name_de' => $row['list_name_de'],
                    ]
                );

                // Entry (falls vorhanden)
                if (!empty($row['entry_technical_name'])) {
                    $existingEntry = ValueListEntry::where('value_list_id', $list->id)
                        ->where('technical_name', $row['entry_technical_name'])
                        ->first();

                    $entryData = [
                        'value_list_id' => $list->id,
                        'technical_name' => $row['entry_technical_name'],
                        'display_value_de' => $row['display_value_de'] ?? $row['entry_technical_name'],
                        'display_value_en' => $row['display_value_en'] ?? null,
                        'sort_order' => (int) ($row['sort_order'] ?? 0),
                    ];

                    if ($existingEntry) {
                        $existingEntry->update($entryData);
                        $this->stats[$sheetKey]['updated']++;
                    } else {
                        $entryData['id'] = Str::uuid()->toString();
                        ValueListEntry::create($entryData);
                        $this->stats[$sheetKey]['created']++;
                    }
                } else {
                    $this->stats[$sheetKey]['created']++;
                }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    private function importAttributes(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
                $existing = Attribute::where('technical_name', $row['technical_name'])->first();

                $data = [
                    'technical_name' => $row['technical_name'],
                    'name_de' => $row['name_de'],
                    'name_en' => $row['name_en'] ?? null,
                    'description_de' => $row['description'] ?? null,
                    'data_type' => $row['data_type'],
                    'is_multipliable' => $this->toBool($row['is_multipliable'] ?? false),
                    'max_multiplied' => !empty($row['max_multiplied']) ? (int) $row['max_multiplied'] : null,
                    'is_translatable' => $this->toBool($row['is_translatable'] ?? false),
                    'is_mandatory' => $this->toBoolMandatory($row['is_mandatory'] ?? null),
                    'is_unique' => $this->toBool($row['is_unique'] ?? false),
                    'is_searchable' => $this->toBool($row['is_searchable'] ?? true),
                    'is_inheritable' => $this->toBool($row['is_inheritable'] ?? true),
                    'source_system' => $row['source_system'] ?? null,
                ];

                // Referenzen auflösen
                if (!empty($row['attribute_group'])) {
                    $result = $this->resolver->resolveAttributeType($row['attribute_group']);
                    if ($result->resolved()) {
                        $data['attribute_type_id'] = $result->id;
                    } else {
                        Log::channel('import')->warning("Attributgruppe nicht aufgelöst: '{$row['attribute_group']}' für Attribut '{$row['technical_name']}'");
                    }
                }

                if (!empty($row['value_list'])) {
                    $result = $this->resolver->resolveValueList($row['value_list']);
                    if ($result->resolved()) {
                        $data['value_list_id'] = $result->id;
                    } else {
                        Log::channel('import')->warning("Werteliste nicht aufgelöst: '{$row['value_list']}' für Attribut '{$row['technical_name']}'");
                    }
                }

                if (!empty($row['unit_group'])) {
                    $result = $this->resolver->resolveUnitGroup($row['unit_group']);
                    if ($result->resolved()) {
                        $data['unit_group_id'] = $result->id;
                    } else {
                        Log::channel('import')->warning("Einheitengruppe nicht aufgelöst: '{$row['unit_group']}' für Attribut '{$row['technical_name']}'");
                    }
                }

                if (!empty($row['default_unit'])) {
                    $result = $this->resolver->resolveUnit($row['default_unit']);
                    if ($result->resolved()) {
                        $data['default_unit_id'] = $result->id;
                    } else {
                        Log::channel('import')->warning("Standard-Einheit nicht aufgelöst: '{$row['default_unit']}' für Attribut '{$row['technical_name']}'");
                    }
                }

                if (!empty($row['parent_attribute'])) {
                    $result = $this->resolver->resolveAttribute($row['parent_attribute']);
                    if ($result->resolved()) {
                        $data['parent_attribute_id'] = $result->id;
                    } else {
                        Log::channel('import')->warning("Übergeordnetes Attribut nicht aufgelöst: '{$row['parent_attribute']}' für Attribut '{$row['technical_name']}'");
                    }
                }

                if ($existing) {
                    $existing->update($data);
                    $this->stats[$sheetKey]['updated']++;
                } else {
                    $data['id'] = Str::uuid()->toString();
                    Attribute::create($data);
                    $this->stats[$sheetKey]['created']++;
                }

                // Sichten zuordnen
                if (!empty($row['views'])) {
                    $this->assignAttributeViews(
                        $existing?->id ?? $data['id'],
                        $row['views']
                    );
                }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    private function importHierarchies(array $rows, string $sheetKey): void
    {
        // Gruppiere Zeilen nach Hierarchie-Name
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['hierarchy']][] = $row;
        }

        foreach ($grouped as $hierarchyName => $hierarchyRows) {
            try {
                $firstRow = $hierarchyRows[0];

                // Hierarchie anlegen/finden
                $hierarchy = Hierarchy::firstOrCreate(
                    ['technical_name' => $hierarchyName],
                    [
                        'id' => Str::uuid()->toString(),
                        'technical_name' => $hierarchyName,
                        'name_de' => $hierarchyName,
                        'hierarchy_type' => mb_strtolower($firstRow['type'] ?? 'master'),
                    ]
                );

                // Knoten anlegen (Hierarchie-Ebenen)
                foreach ($hierarchyRows as $row) {
                    $this->ensureHierarchyPath($hierarchy, $row);
                }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $hierarchyRows[0] ?? [], $e);
            }
        }

        $this->stats[$sheetKey]['created'] = count($rows);
    }

    private function importHierarchyAttributes(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
            $hierarchyResult = $this->resolver->resolveHierarchy($row['hierarchy']);
            if (!$hierarchyResult->resolved()) {
                $this->logSkipped($sheetKey, $row, "Hierarchie nicht gefunden: '{$row['hierarchy']}'");
                continue;
            }

            $nodeResult = $this->resolver->resolveHierarchyNode($row['hierarchy'], $row['node_path']);
            if (!$nodeResult->resolved()) {
                $this->logSkipped($sheetKey, $row, "Hierarchieknoten nicht gefunden: '{$row['hierarchy']}' Pfad '{$row['node_path']}'");
                continue;
            }

            $attrResult = $this->resolver->resolveAttribute($row['attribute']);
            if (!$attrResult->resolved()) {
                $this->logSkipped($sheetKey, $row, "Attribut nicht gefunden: '{$row['attribute']}'");
                continue;
            }

            $existing = HierarchyNodeAttributeAssignment::where('hierarchy_node_id', $nodeResult->id)
                ->where('attribute_id', $attrResult->id)
                ->first();

            $data = [
                'hierarchy_node_id' => $nodeResult->id,
                'attribute_id' => $attrResult->id,
                'collection_name' => $row['collection_name'] ?? null,
                'collection_sort' => (int) ($row['collection_sort'] ?? 0),
                'attribute_sort' => (int) ($row['attribute_sort'] ?? 0),
                'dont_inherit' => $this->toBool($row['dont_inherit'] ?? false),
            ];

            if ($existing) {
                $existing->update($data);
                $this->stats[$sheetKey]['updated']++;
            } else {
                $data['id'] = Str::uuid()->toString();
                HierarchyNodeAttributeAssignment::create($data);
                $this->stats[$sheetKey]['created']++;
            }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    private function importProducts(array $rows, string $sheetKey): void
    {
        // Resolve the 'name' attribute once for English name storage
        $nameAttribute = Attribute::where('technical_name', 'name')->first();

        foreach ($rows as $row) {
            try {
            $existing = Product::where('sku', $row['sku'])->first();

            $typeResult = $this->resolver->resolveProductType($row['product_type']);
            if (!$typeResult->resolved()) {
                $this->logSkipped($sheetKey, $row, "Produkttyp nicht gefunden: '{$row['product_type']}'");
                continue;
            }

            $data = [
                'sku' => $row['sku'],
                'name' => $row['name'],
                'product_type_id' => $typeResult->id,
                'ean' => $row['ean'] ?? null,
                'status' => mb_strtolower($row['status'] ?? 'draft'),
                'product_type_ref' => 'product',
            ];

            $productId = null;

            if ($existing) {
                $existing->update($data);
                $this->stats[$sheetKey]['updated']++;
                $this->affectedProductIds[] = $existing->id;
                $productId = $existing->id;
            } else {
                $data['id'] = Str::uuid()->toString();
                Product::create($data);
                $this->stats[$sheetKey]['created']++;
                $this->affectedProductIds[] = $data['id'];
                $productId = $data['id'];
            }

            // Save English name to EAV if provided
            if ($nameAttribute && !empty($row['name_en'])) {
                ProductAttributeValue::updateOrCreate(
                    [
                        'product_id' => $productId,
                        'attribute_id' => $nameAttribute->id,
                        'language' => 'en',
                    ],
                    [
                        'value_string' => $row['name_en'],
                    ]
                );
            }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    private function importProductValues(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
            $productResult = $this->resolver->resolveProduct($row['sku']);
            if (!$productResult->resolved()) {
                $this->logSkipped($sheetKey, $row, "Produkt nicht gefunden: SKU '{$row['sku']}'");
                continue;
            }

            $attrResult = $this->resolver->resolveAttribute($row['attribute']);
            if (!$attrResult->resolved()) {
                $this->logSkipped($sheetKey, $row, "Attribut nicht gefunden: '{$row['attribute']}'");
                continue;
            }

            $language = !empty($row['language']) ? mb_strtolower((string) $row['language']) : null;
            $index = (int) ($row['index'] ?? 0);

            $existing = ProductAttributeValue::where('product_id', $productResult->id)
                ->where('attribute_id', $attrResult->id)
                ->where('language', $language)
                ->where('multiplied_index', $index)
                ->first();

            // Attribut laden um Datentyp zu bestimmen
            $attribute = Attribute::find($attrResult->id);
            $valueData = $this->mapValueToColumns($row['value'], $attribute?->data_type ?? 'String');

            $data = array_merge([
                'product_id' => $productResult->id,
                'attribute_id' => $attrResult->id,
                'language' => $language,
                'multiplied_index' => $index,
            ], $valueData);

            // Selection-Werte: ValueListEntry auflösen und value_selection_id setzen
            if ($attribute && $attribute->data_type === 'Selection' && $attribute->value_list_id) {
                $entry = ValueListEntry::where('value_list_id', $attribute->value_list_id)
                    ->where('technical_name', (string) $row['value'])
                    ->first();
                if ($entry) {
                    $data['value_selection_id'] = $entry->id;
                    $data['value_string'] = $entry->technical_name;
                } else {
                    // Fallback: auch über display_value_de suchen
                    $entry = ValueListEntry::where('value_list_id', $attribute->value_list_id)
                        ->where('display_value_de', (string) $row['value'])
                        ->first();
                    if ($entry) {
                        $data['value_selection_id'] = $entry->id;
                        $data['value_string'] = $entry->technical_name;
                    }
                }
            }

            // Einheit auflösen
            if (!empty($row['unit'])) {
                $unitResult = $this->resolver->resolveUnit($row['unit']);
                if ($unitResult->resolved()) {
                    $data['unit_id'] = $unitResult->id;
                }
            }

            if ($existing) {
                $existing->update($data);
                $this->stats[$sheetKey]['updated']++;
            } else {
                $data['id'] = Str::uuid()->toString();
                ProductAttributeValue::create($data);
                $this->stats[$sheetKey]['created']++;
            }

            $this->affectedProductIds[] = $productResult->id;
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    private function importVariants(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
            $parentResult = $this->resolver->resolveProduct($row['parent_sku']);
            if (!$parentResult->resolved()) {
                $this->logSkipped($sheetKey, $row, "Eltern-Produkt nicht gefunden: SKU '{$row['parent_sku']}'");
                continue;
            }

            $existing = Product::where('sku', $row['variant_sku'])->first();

            // Ermittle den Produkttyp des Elternprodukts
            $parent = Product::find($parentResult->id);

            $data = [
                'sku' => $row['variant_sku'],
                'name' => $row['variant_name'],
                'product_type_id' => $parent?->product_type_id,
                'product_type_ref' => 'variant',
                'parent_product_id' => $parentResult->id,
                'ean' => $row['ean'] ?? null,
                'status' => mb_strtolower($row['status'] ?? 'draft'),
            ];

            if ($existing) {
                $existing->update($data);
                $this->stats[$sheetKey]['updated']++;
                $this->affectedProductIds[] = $existing->id;
            } else {
                $data['id'] = Str::uuid()->toString();
                Product::create($data);
                $this->stats[$sheetKey]['created']++;
                $this->affectedProductIds[] = $data['id'];
            }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    private function importProductHierarchies(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
            $productResult = $this->resolver->resolveProduct($row['sku']);
            if (!$productResult->resolved()) {
                $this->logSkipped($sheetKey, $row, "Produkt nicht gefunden: SKU '{$row['sku']}'");
                continue;
            }

            $nodeResult = $this->resolver->resolveHierarchyNode($row['hierarchy'], $row['node_path']);
            if (!$nodeResult->resolved()) {
                $this->logSkipped($sheetKey, $row, "Hierarchieknoten nicht gefunden: '{$row['hierarchy']}' Pfad '{$row['node_path']}'");
                continue;
            }

            // Hierarchie-Typ bestimmen → master → master_hierarchy_node_id, output → pivot
            $hierarchy = Hierarchy::where('technical_name', $row['hierarchy'])->first();

            if ($hierarchy && $hierarchy->hierarchy_type === 'master') {
                // Master: product.master_hierarchy_node_id setzen
                Product::where('id', $productResult->id)->update([
                    'master_hierarchy_node_id' => $nodeResult->id,
                ]);
                $this->stats[$sheetKey]['updated']++;
            } else {
                // Output: pivot-Tabelle
                $existing = OutputHierarchyProductAssignment::where('hierarchy_node_id', $nodeResult->id)
                    ->where('product_id', $productResult->id)
                    ->first();

                if (!$existing) {
                    OutputHierarchyProductAssignment::create([
                        'id' => Str::uuid()->toString(),
                        'hierarchy_node_id' => $nodeResult->id,
                        'product_id' => $productResult->id,
                        'sort_order' => 0,
                    ]);
                    $this->stats[$sheetKey]['created']++;
                } else {
                    $this->stats[$sheetKey]['skipped']++;
                }
            }

            $this->affectedProductIds[] = $productResult->id;
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    private function importProductRelations(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
            $sourceResult = $this->resolver->resolveProduct($row['source_sku']);
            $targetResult = $this->resolver->resolveProduct($row['target_sku']);

            if (!$sourceResult->resolved() || !$targetResult->resolved()) {
                $missing = [];
                if (!$sourceResult->resolved()) $missing[] = "Quell-SKU '{$row['source_sku']}'";
                if (!$targetResult->resolved()) $missing[] = "Ziel-SKU '{$row['target_sku']}'";
                $this->logSkipped($sheetKey, $row, "Produkt(e) nicht gefunden: " . implode(', ', $missing));
                continue;
            }

            // Beziehungstyp auflösen oder automatisch anlegen
            $typeResult = $this->resolver->resolveRelationType($row['relation_type']);
            if (!$typeResult->resolved()) {
                $relationType = ProductRelationType::firstOrCreate(
                    ['technical_name' => $row['relation_type']],
                    ['id' => Str::uuid()->toString(), 'name_de' => $row['relation_type']]
                );
                $this->resolver->clearCache('relation_type');
                $relationTypeId = $relationType->id;
            } else {
                $relationTypeId = $typeResult->id;
            }

            $existing = ProductRelation::where('source_product_id', $sourceResult->id)
                ->where('target_product_id', $targetResult->id)
                ->where('relation_type_id', $relationTypeId)
                ->first();

            if (!$existing) {
                ProductRelation::create([
                    'id' => Str::uuid()->toString(),
                    'source_product_id' => $sourceResult->id,
                    'target_product_id' => $targetResult->id,
                    'relation_type_id' => $relationTypeId,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ]);
                $this->stats[$sheetKey]['created']++;
            } else {
                $existing->update(['sort_order' => (int) ($row['sort_order'] ?? 0)]);
                $this->stats[$sheetKey]['updated']++;
            }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    private function importPrices(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
            $productResult = $this->resolver->resolveProduct($row['sku']);
            if (!$productResult->resolved()) {
                $this->logSkipped($sheetKey, $row, "Produkt nicht gefunden: SKU '{$row['sku']}'");
                continue;
            }

            // Preisart auflösen oder automatisch anlegen
            $priceTypeResult = $this->resolver->resolvePriceType($row['price_type']);
            if (!$priceTypeResult->resolved()) {
                $priceType = PriceType::firstOrCreate(
                    ['technical_name' => $row['price_type']],
                    ['id' => Str::uuid()->toString(), 'name_de' => $row['price_type']]
                );
                $this->resolver->clearCache('price_type');
                $priceTypeId = $priceType->id;
            } else {
                $priceTypeId = $priceTypeResult->id;
            }

            $currency = strtoupper((string) ($row['currency'] ?? 'EUR'));
            $validFrom = $row['valid_from'] ?? null;

            $query = ProductPrice::where('product_id', $productResult->id)
                ->where('price_type_id', $priceTypeId)
                ->where('currency', $currency);

            if ($validFrom) {
                $query->where('valid_from', $validFrom);
            }

            $existing = $query->first();

            $data = [
                'product_id' => $productResult->id,
                'price_type_id' => $priceTypeId,
                'amount' => (float) $row['amount'],
                'currency' => $currency,
                'valid_from' => $validFrom,
                'valid_to' => $row['valid_to'] ?? null,
                'country' => !empty($row['country']) ? strtoupper((string) $row['country']) : null,
                'scale_from' => !empty($row['scale_from']) ? (int) $row['scale_from'] : null,
                'scale_to' => !empty($row['scale_to']) ? (int) $row['scale_to'] : null,
            ];

            if ($existing) {
                $existing->update($data);
                $this->stats[$sheetKey]['updated']++;
            } else {
                $data['id'] = Str::uuid()->toString();
                ProductPrice::create($data);
                $this->stats[$sheetKey]['created']++;
            }

            $this->affectedProductIds[] = $productResult->id;
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    private function importMedia(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
            $productResult = $this->resolver->resolveProduct($row['sku']);
            if (!$productResult->resolved()) {
                $this->logSkipped($sheetKey, $row, "Produkt nicht gefunden: SKU '{$row['sku']}'");
                continue;
            }

            // Media-Eintrag finden oder anlegen
            $media = Media::where('file_name', $row['file_name'])->first();
            if (!$media) {
                $media = Media::create([
                    'id' => Str::uuid()->toString(),
                    'file_name' => $row['file_name'],
                    'file_path' => 'imports/' . $row['file_name'],
                    'mime_type' => $this->guessMimeType($row['file_name']),
                    'file_size' => 0,
                    'media_type' => $row['media_type'] ?? 'image',
                    'title_de' => $row['title_de'] ?? null,
                    'title_en' => $row['title_en'] ?? null,
                    'alt_text_de' => $row['alt_text_de'] ?? null,
                ]);
            }

            // Zuordnung
            $existing = ProductMediaAssignment::where('product_id', $productResult->id)
                ->where('media_id', $media->id)
                ->first();

            if (!$existing) {
                ProductMediaAssignment::create([
                    'id' => Str::uuid()->toString(),
                    'product_id' => $productResult->id,
                    'media_id' => $media->id,
                    'usage_type' => $row['usage_type'] ?? 'gallery',
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_primary' => $this->toBool($row['is_primary'] ?? false),
                ]);
                $this->stats[$sheetKey]['created']++;
            } else {
                $this->stats[$sheetKey]['skipped']++;
            }

            $this->affectedProductIds[] = $productResult->id;
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    // ──────────────────────────────────────────────
    //  Hilfs-Methoden
    // ──────────────────────────────────────────────

    /**
     * Konvertiert Ja/Nein/1/0 in boolean.
     */
    private function toBool(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $lower = mb_strtolower(trim((string) $value));
        return in_array($lower, ['ja', 'yes', '1', 'true', 'wahr', 'x'], true);
    }

    /**
     * Pflicht/Optional → boolean.
     */
    private function toBoolMandatory(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        $lower = mb_strtolower(trim((string) $value));
        return $lower === 'pflicht' || $lower === 'mandatory' || $this->toBool($value);
    }

    /**
     * Mappt einen Wert auf die korrekten DB-Spalten je nach Datentyp.
     */
    private function mapValueToColumns(mixed $value, string $dataType): array
    {
        return match ($dataType) {
            'Number', 'Float' => [
                'value_number' => is_numeric($value) ? (float) $value : null,
                'value_string' => null,
                'value_date' => null,
                'value_flag' => null,
            ],
            'Date' => [
                'value_date' => $value,
                'value_string' => null,
                'value_number' => null,
                'value_flag' => null,
            ],
            'Flag' => [
                'value_flag' => $this->toBool($value),
                'value_string' => null,
                'value_number' => null,
                'value_date' => null,
            ],
            default => [
                'value_string' => (string) $value,
                'value_number' => null,
                'value_date' => null,
                'value_flag' => null,
            ],
        };
    }

    /**
     * Stellt sicher, dass ein Hierarchie-Pfad vollständig existiert.
     */
    private function ensureHierarchyPath(Hierarchy $hierarchy, array $row): void
    {
        $parentId = null;
        $pathSegments = [];

        for ($level = 1; $level <= 6; $level++) {
            $name = $row["level_{$level}"] ?? null;
            if (empty($name)) {
                break;
            }

            $pathSegments[] = $name;
            $path = '/' . implode('/', $pathSegments) . '/';

            $existing = HierarchyNode::where('hierarchy_id', $hierarchy->id)
                ->where('path', $path)
                ->first();

            if ($existing) {
                // Hierarchieknoten: Skip bei Existenz (laut Spezifikation)
                $parentId = $existing->id;
                continue;
            }

            $nodeId = Str::uuid()->toString();
            HierarchyNode::create([
                'id' => $nodeId,
                'hierarchy_id' => $hierarchy->id,
                'parent_node_id' => $parentId,
                'name_de' => $name,
                'path' => $path,
                'depth' => $level - 1,
                'sort_order' => 0,
                'is_active' => true,
            ]);

            $parentId = $nodeId;
        }
    }

    /**
     * Ordnet Attribut-Sichten zu (kommasepariert).
     */
    private function assignAttributeViews(string $attributeId, string $views): void
    {
        $viewNames = array_map('trim', explode(',', $views));

        foreach ($viewNames as $viewName) {
            if (empty($viewName)) {
                continue;
            }

            $view = \App\Models\AttributeView::where('technical_name', $viewName)->first();
            if (!$view) {
                continue;
            }

            $existingAssignment = \App\Models\AttributeViewAssignment::where('attribute_id', $attributeId)
                ->where('attribute_view_id', $view->id)
                ->first();

            if (!$existingAssignment) {
                \App\Models\AttributeViewAssignment::create([
                    'id' => Str::uuid()->toString(),
                    'attribute_id' => $attributeId,
                    'attribute_view_id' => $view->id,
                ]);
            }
        }
    }

    private function guessMimeType(string $fileName): string
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            default => 'application/octet-stream',
        };
    }
}
