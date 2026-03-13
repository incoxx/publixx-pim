<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\AttributeView;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyAttributeAssignment;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\Media;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\MediaUsageType;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use App\Models\ProductRelation;
use App\Models\ProductRelationAttributeValue;
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

    /** Import-Modus: 'update' (Upsert) oder 'delete_insert' (löschen + neu anlegen). */
    private string $mode = 'update';

    /** Ab dieser Zeilenanzahl pro Sheet wird Bulk-Import genutzt. */
    private const BULK_THRESHOLD = 500;

    /** Optional progress callback: fn(string $phase, int $current, int $total, array $stats) */
    private $progressCallback = null;

    /** Optional heartbeat callback: wird regelmäßig aufgerufen um SSE-Verbindung offen zu halten */
    private $heartbeatCallback = null;

    public function __construct(?ReferenceResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new ReferenceResolver();
    }

    /**
     * Setzt eine Callback-Funktion für Fortschrittsmeldungen.
     *
     * @param callable(string $phase, int $current, int $total, array $stats): void $callback
     */
    public function setProgressCallback(callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    /**
     * Setzt eine Heartbeat-Funktion die regelmäßig aufgerufen wird (z.B. alle 50 Zeilen).
     * Verhindert Verbindungs-Timeouts bei lang laufenden Imports.
     */
    public function setHeartbeatCallback(callable $callback): void
    {
        $this->heartbeatCallback = $callback;
    }

    /**
     * Heartbeat senden (zeitbasiert, nicht bei jedem Aufruf).
     */
    private function heartbeat(): void
    {
        if ($this->heartbeatCallback) {
            ($this->heartbeatCallback)();
        }
    }

    /**
     * Setzt den Import-Modus.
     *
     * @param string $mode 'update' oder 'delete_insert'
     */
    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['update', 'delete_insert', 'delete']) ? $mode : 'update';
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
            '15_Attribut_Sichten',
            '05_Attribute',
            '16_Preistypen',
            '17_Beziehungstypen',
            '06_Hierarchien',
            '07_Hierarchie_Attribute',
            '07b_Hierarchie_Ebene_Attribute',
            '08_Produkte',
            '09_Produktwerte',
            '10_Varianten',
            '11_Produkt_Hierarchien',
            '12_Produktbeziehungen',
            '13_Preise',
            '14_Medien',
        ];

        // Bei delete_insert: betroffene Daten vorher löschen
        if ($this->mode === 'delete_insert') {
            DB::beginTransaction();
            try {
                $this->deleteExistingData($parseResult);
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }

        // Bei delete: nur löschen, kein Import
        if ($this->mode === 'delete') {
            DB::beginTransaction();
            try {
                $deleteStats = $this->deleteExistingData($parseResult);
                $this->stats['_delete'] = [
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => 0,
                    'deleted' => $deleteStats,
                ];
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            return new ImportExecutionResult(
                stats: $this->stats,
                affectedProductIds: $this->affectedProductIds,
                skippedDetails: $this->skippedDetails,
            );
        }

        // Count active sheets for progress reporting
        $activeSheets = array_filter($sheetOrder, fn ($k) => $parseResult->hasSheet($k) && !empty($parseResult->getSheetData($k)));
        $totalSheets = count($activeSheets);
        $completedSheets = 0;

        // Gesamtanzahl aller Zeilen berechnen für Bulk-Weiche
        $totalRows = 0;
        foreach ($sheetOrder as $sk) {
            if ($parseResult->hasSheet($sk)) {
                $totalRows += count($parseResult->getSheetData($sk));
            }
        }
        $useBulk = $totalRows >= self::BULK_THRESHOLD;

        if ($useBulk) {
            Log::channel('import')->info("Bulk-Import-Modus aktiviert ({$totalRows} Zeilen gesamt)");
        }

        foreach ($sheetOrder as $sheetKey) {
            if (!$parseResult->hasSheet($sheetKey)) {
                continue;
            }

            $rows = $parseResult->getSheetData($sheetKey);
            if (empty($rows)) {
                continue;
            }

            $this->stats[$sheetKey] = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

            Log::channel('import')->info("Importiere Sheet: {$sheetKey}", ['rows' => count($rows), 'bulk' => $useBulk]);

            if ($this->progressCallback) {
                ($this->progressCallback)($sheetKey, $completedSheets, $totalSheets, $this->stats);
            }

            $method = match ($sheetKey) {
                '01_Produkttypen' => 'importProductTypes',
                '02_Attributgruppen' => 'importAttributeTypes',
                '03_Einheiten' => 'importUnits',
                '04_Wertelisten' => 'importValueLists',
                '05_Attribute' => 'importAttributes',
                '06_Hierarchien' => 'importHierarchies',
                '07_Hierarchie_Attribute' => 'importHierarchyAttributes',
                '07b_Hierarchie_Ebene_Attribute' => 'importHierarchyLevelAttributes',
                '08_Produkte' => 'importProducts',
                '09_Produktwerte' => 'importProductValues',
                '10_Varianten' => 'importVariants',
                '11_Produkt_Hierarchien' => 'importProductHierarchies',
                '12_Produktbeziehungen' => 'importProductRelations',
                '13_Preise' => 'importPrices',
                '14_Medien' => 'importMedia',
                '15_Attribut_Sichten' => 'importAttributeViews',
                '16_Preistypen' => 'importPriceTypes',
                '17_Beziehungstypen' => 'importRelationTypes',
                default => null,
            };

            // Bulk-Varianten für große Imports (Weiche)
            $bulkMethod = match ($sheetKey) {
                '08_Produkte' => 'importProductsBulk',
                '09_Produktwerte' => 'importProductValuesBulk',
                '11_Produkt_Hierarchien' => 'importProductHierarchiesBulk',
                '13_Preise' => 'importPricesBulk',
                default => null,
            };

            $chosenMethod = ($useBulk && $bulkMethod) ? $bulkMethod : $method;

            // Per-Sheet Transaktion
            if ($chosenMethod && method_exists($this, $chosenMethod)) {
                DB::beginTransaction();
                try {
                    $this->{$chosenMethod}($rows, $sheetKey);
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    Log::channel('import')->error("Sheet {$sheetKey} fehlgeschlagen, übersprungen", [
                        'error' => $e->getMessage(),
                    ]);
                    $this->stats[$sheetKey]['errors'] = count($rows);
                }
            }

            $completedSheets++;

            // Cache nach Stammdaten-Import leeren
            if (in_array($sheetKey, [
                '01_Produkttypen', '02_Attributgruppen', '03_Einheiten',
                '04_Wertelisten', '05_Attribute', '06_Hierarchien', '08_Produkte',
                '15_Attribut_Sichten', '16_Preistypen', '17_Beziehungstypen',
            ])) {
                $this->resolver->clearCache();
            }
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
    //  Delete/Insert: Vorhandene Daten löschen
    // ──────────────────────────────────────────────

    /**
     * Löscht vorhandene Daten, die im Import enthalten sind.
     * Löscht nur Daten, die durch den Import betroffen werden (anhand SKUs).
     *
     * @return int Anzahl gelöschter Produkte
     */
    private function deleteExistingData(ParseResult $parseResult): int
    {
        Log::channel('import')->info('Delete/Insert-Modus: Lösche vorhandene Daten vor Neuanlage');

        // SKUs aus dem Produkt-Sheet sammeln
        $productSkus = [];
        if ($parseResult->hasSheet('08_Produkte')) {
            foreach ($parseResult->getSheetData('08_Produkte') as $row) {
                if (!empty($row['sku'])) {
                    $productSkus[] = $row['sku'];
                }
            }
        }

        // Varianten-SKUs sammeln
        $variantSkus = [];
        if ($parseResult->hasSheet('10_Varianten')) {
            foreach ($parseResult->getSheetData('10_Varianten') as $row) {
                if (!empty($row['variant_sku'])) {
                    $variantSkus[] = $row['variant_sku'];
                }
            }
        }

        $allSkus = array_merge($productSkus, $variantSkus);

        if (!empty($allSkus)) {
            $productIds = Product::whereIn('sku', $allSkus)->pluck('id')->toArray();

            if (!empty($productIds)) {
                // Abhängige Daten in korrekter Reihenfolge löschen
                ProductRelation::whereIn('source_product_id', $productIds)
                    ->orWhereIn('target_product_id', $productIds)
                    ->delete();

                ProductPrice::whereIn('product_id', $productIds)->delete();

                ProductMediaAssignment::whereIn('product_id', $productIds)->delete();

                ProductAttributeValue::whereIn('product_id', $productIds)->delete();

                OutputHierarchyProductAssignment::whereIn('product_id', $productIds)->delete();

                // Varianten vor Elternprodukten löschen
                Product::whereIn('id', $productIds)
                    ->where('product_type_ref', 'variant')
                    ->delete();

                Product::whereIn('id', $productIds)
                    ->where('product_type_ref', 'product')
                    ->delete();

                Log::channel('import')->info('Delete: Produkte und abhängige Daten gelöscht', [
                    'skus' => count($allSkus),
                    'product_ids' => count($productIds),
                ]);

                return count($productIds);
            }
        }

        return 0;
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
                    'source_attribute_key' => $row['source_attribute_key'] ?? null,
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

                if (!empty($row['composite_expression'])) {
                    $data['composite_expression'] = $row['composite_expression'];
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

    private function importHierarchyLevelAttributes(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
                $hierarchyResult = $this->resolver->resolveHierarchy($row['hierarchy']);
                if (!$hierarchyResult->resolved()) {
                    $this->logSkipped($sheetKey, $row, "Hierarchie nicht gefunden: '{$row['hierarchy']}'");
                    continue;
                }

                $attrResult = $this->resolver->resolveAttribute($row['attribute']);
                if (!$attrResult->resolved()) {
                    $this->logSkipped($sheetKey, $row, "Attribut nicht gefunden: '{$row['attribute']}'");
                    continue;
                }

                $existing = HierarchyAttributeAssignment::where('hierarchy_id', $hierarchyResult->id)
                    ->where('attribute_id', $attrResult->id)
                    ->first();

                $data = [
                    'hierarchy_id' => $hierarchyResult->id,
                    'attribute_id' => $attrResult->id,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ];

                if ($existing) {
                    $existing->update($data);
                    $this->stats[$sheetKey]['updated']++;
                } else {
                    $data['id'] = Str::uuid()->toString();
                    HierarchyAttributeAssignment::create($data);
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

        $rowCount = 0;
        foreach ($rows as $row) {
            if (++$rowCount % 50 === 0) {
                $this->heartbeat();
            }
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

    /**
     * Bulk-Import für Produkte: upsert() in Chunks statt Zeile-für-Zeile.
     */
    private function importProductsBulk(array $rows, string $sheetKey): void
    {
        $nameAttribute = Attribute::where('technical_name', 'name')->first();
        $now = now();
        $englishNameRows = [];

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->heartbeat();

            // Bestehende SKUs in diesem Chunk ermitteln für Stats
            $skusInChunk = array_column($chunk, 'sku');
            $existingSkus = Product::whereIn('sku', $skusInChunk)->pluck('sku')->flip()->all();

            $upsertData = [];
            foreach ($chunk as $row) {
                try {
                    $typeResult = $this->resolver->resolveProductType($row['product_type']);
                    if (!$typeResult->resolved()) {
                        $this->logSkipped($sheetKey, $row, "Produkttyp nicht gefunden: '{$row['product_type']}'");
                        continue;
                    }

                    $isUpdate = isset($existingSkus[$row['sku']]);
                    $upsertData[] = [
                        'id' => Str::uuid()->toString(),
                        'sku' => $row['sku'],
                        'name' => $row['name'],
                        'product_type_id' => $typeResult->id,
                        'ean' => $row['ean'] ?? null,
                        'status' => mb_strtolower($row['status'] ?? 'draft'),
                        'product_type_ref' => 'product',
                        'updated_at' => $now,
                        'created_at' => $now,
                    ];

                    if ($isUpdate) {
                        $this->stats[$sheetKey]['updated']++;
                    } else {
                        $this->stats[$sheetKey]['created']++;
                    }

                    // English name für spätere Batch-Verarbeitung sammeln
                    if ($nameAttribute && !empty($row['name_en'])) {
                        $englishNameRows[] = [
                            'sku' => $row['sku'],
                            'name_en' => $row['name_en'],
                        ];
                    }
                } catch (\Throwable $e) {
                    $this->logRowError($sheetKey, $row, $e);
                }
            }

            if (!empty($upsertData)) {
                Product::upsert(
                    $upsertData,
                    ['sku'],
                    ['name', 'product_type_id', 'ean', 'status', 'product_type_ref', 'updated_at']
                );
            }
        }

        // Produkt-IDs sammeln (für Event + English Names)
        $allSkus = array_column($rows, 'sku');
        $productIdMap = Product::whereIn('sku', $allSkus)->pluck('id', 'sku')->all();
        $this->affectedProductIds = array_merge($this->affectedProductIds, array_values($productIdMap));

        // English names batch-verarbeiten
        if ($nameAttribute && !empty($englishNameRows)) {
            $pavData = [];
            foreach ($englishNameRows as $enRow) {
                $productId = $productIdMap[$enRow['sku']] ?? null;
                if ($productId) {
                    $pavData[] = [
                        'id' => Str::uuid()->toString(),
                        'product_id' => $productId,
                        'attribute_id' => $nameAttribute->id,
                        'language' => 'en',
                        'multiplied_index' => 0,
                        'value_string' => $enRow['name_en'],
                        'updated_at' => $now,
                        'created_at' => $now,
                    ];
                }
            }
            if (!empty($pavData)) {
                foreach (array_chunk($pavData, 500) as $pavChunk) {
                    ProductAttributeValue::upsert(
                        $pavChunk,
                        ['product_id', 'attribute_id', 'language', 'multiplied_index'],
                        ['value_string', 'updated_at']
                    );
                }
            }
        }
    }

    private function importProductValues(array $rows, string $sheetKey): void
    {
        $rowCount = 0;
        foreach ($rows as $row) {
            if (++$rowCount % 50 === 0) {
                $this->heartbeat();
            }
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

    /**
     * Bulk-Import für Produktwerte: Caches vorwärmen + upsert() in Chunks.
     */
    private function importProductValuesBulk(array $rows, string $sheetKey): void
    {
        $now = now();

        // Attribut-Cache vorwärmen: alle Attribute laden (vermeidet Attribute::find() pro Zeile)
        $attributeById = Attribute::all()->keyBy('id');

        // ValueListEntry-Cache vorwärmen
        $valueListIds = $attributeById->whereNotNull('value_list_id')->pluck('value_list_id')->unique()->all();
        $valueListEntries = [];
        if (!empty($valueListIds)) {
            $entries = ValueListEntry::whereIn('value_list_id', $valueListIds)->get();
            foreach ($entries as $entry) {
                $valueListEntries[$entry->value_list_id][$entry->technical_name] = $entry;
                if ($entry->display_value_de) {
                    $valueListEntries[$entry->value_list_id]['_display_' . $entry->display_value_de] = $entry;
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->heartbeat();

            $upsertData = [];
            $affectedIds = [];

            foreach ($chunk as $row) {
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

                    // Attribut aus vorgewärmtem Cache laden
                    $attribute = $attributeById->get($attrResult->id);
                    $valueData = $this->mapValueToColumns($row['value'], $attribute?->data_type ?? 'String');

                    $data = array_merge([
                        'id' => Str::uuid()->toString(),
                        'product_id' => $productResult->id,
                        'attribute_id' => $attrResult->id,
                        'language' => $language,
                        'multiplied_index' => $index,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ], $valueData);

                    // Selection-Werte aus vorgewärmtem Cache auflösen
                    if ($attribute && $attribute->data_type === 'Selection' && $attribute->value_list_id) {
                        $vlEntries = $valueListEntries[$attribute->value_list_id] ?? [];
                        $entry = $vlEntries[(string) $row['value']] ?? $vlEntries['_display_' . (string) $row['value']] ?? null;
                        if ($entry) {
                            $data['value_selection_id'] = $entry->id;
                            $data['value_string'] = $entry->technical_name;
                        }
                    }

                    // Einheit auflösen (Resolver hat O(1)-Cache)
                    if (!empty($row['unit'])) {
                        $unitResult = $this->resolver->resolveUnit($row['unit']);
                        if ($unitResult->resolved()) {
                            $data['unit_id'] = $unitResult->id;
                        }
                    }

                    // NULL-language-Zeilen können bei SQLite-Tests Probleme mit upsert machen
                    // Separate Behandlung nicht nötig da wir hier immer language haben (BMEcat)
                    $upsertData[] = $data;
                    $affectedIds[] = $productResult->id;
                } catch (\Throwable $e) {
                    $this->logRowError($sheetKey, $row, $e);
                }
            }

            if (!empty($upsertData)) {
                // Bestehende Einträge zählen für Stats
                $countBefore = 0;
                $productIds = array_unique(array_column($upsertData, 'product_id'));
                if (!empty($productIds)) {
                    $countBefore = ProductAttributeValue::whereIn('product_id', $productIds)->count();
                }

                // Alle Spalten konsistent machen für upsert
                $allKeys = ['id', 'product_id', 'attribute_id', 'language', 'multiplied_index',
                    'value_string', 'value_number', 'value_date', 'value_flag',
                    'value_selection_id', 'unit_id', 'updated_at', 'created_at'];
                foreach ($upsertData as &$d) {
                    foreach ($allKeys as $key) {
                        if (!array_key_exists($key, $d)) {
                            $d[$key] = null;
                        }
                    }
                }
                unset($d);

                ProductAttributeValue::upsert(
                    $upsertData,
                    ['product_id', 'attribute_id', 'language', 'multiplied_index'],
                    ['value_string', 'value_number', 'value_date', 'value_flag', 'value_selection_id', 'unit_id', 'updated_at']
                );

                $countAfter = ProductAttributeValue::whereIn('product_id', $productIds)->count();
                $created = $countAfter - $countBefore;
                $updated = count($upsertData) - $created;
                $this->stats[$sheetKey]['created'] += max(0, $created);
                $this->stats[$sheetKey]['updated'] += max(0, $updated);
            }

            $this->affectedProductIds = array_merge($this->affectedProductIds, $affectedIds);
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
        $rowCount = 0;
        foreach ($rows as $row) {
            if (++$rowCount % 50 === 0) {
                $this->heartbeat();
            }
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

    /**
     * Bulk-Import für Produkt-Hierarchien: upsert() für Output-Hierarchien.
     */
    private function importProductHierarchiesBulk(array $rows, string $sheetKey): void
    {
        $now = now();
        $masterUpdates = []; // nodeId → [productIds]
        $outputUpsertData = [];

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->heartbeat();

            foreach ($chunk as $row) {
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

                    $hierarchy = Hierarchy::where('technical_name', $row['hierarchy'])->first();

                    if ($hierarchy && $hierarchy->hierarchy_type === 'master') {
                        $masterUpdates[$nodeResult->id][] = $productResult->id;
                    } else {
                        $outputUpsertData[] = [
                            'id' => Str::uuid()->toString(),
                            'hierarchy_node_id' => $nodeResult->id,
                            'product_id' => $productResult->id,
                            'sort_order' => 0,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ];
                    }

                    $this->affectedProductIds[] = $productResult->id;
                } catch (\Throwable $e) {
                    $this->logRowError($sheetKey, $row, $e);
                }
            }
        }

        // Master-Hierarchien batch-update
        foreach ($masterUpdates as $nodeId => $productIds) {
            Product::whereIn('id', $productIds)->update(['master_hierarchy_node_id' => $nodeId]);
            $this->stats[$sheetKey]['updated'] += count($productIds);
        }

        // Output-Hierarchien upsert
        if (!empty($outputUpsertData)) {
            foreach (array_chunk($outputUpsertData, 500) as $chunk) {
                OutputHierarchyProductAssignment::upsert(
                    $chunk,
                    ['hierarchy_node_id', 'product_id'],
                    ['sort_order', 'updated_at']
                );
            }
            $this->stats[$sheetKey]['created'] += count($outputUpsertData);
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
                $relation = ProductRelation::create([
                    'id' => Str::uuid()->toString(),
                    'source_product_id' => $sourceResult->id,
                    'target_product_id' => $targetResult->id,
                    'relation_type_id' => $relationTypeId,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ]);
                $this->stats[$sheetKey]['created']++;
            } else {
                $existing->update(['sort_order' => (int) ($row['sort_order'] ?? 0)]);
                $relation = $existing;
                $this->stats[$sheetKey]['updated']++;
            }

            // Import attribute values on the relation edge
            if (!empty($row['attribute_values']) && is_array($row['attribute_values'])) {
                $this->importRelationAttributeValues($relation, $row['attribute_values']);
            }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    private function importRelationAttributeValues(ProductRelation $relation, array $attrValues): void
    {
        foreach ($attrValues as $av) {
            $attrResult = $this->resolver->resolveAttribute($av['attribute'] ?? '');
            if (!$attrResult->resolved()) {
                continue;
            }

            $unitId = null;
            if (!empty($av['unit'])) {
                $unitResult = $this->resolver->resolveUnit($av['unit']);
                if ($unitResult->resolved()) {
                    $unitId = $unitResult->id;
                }
            }

            $key = [
                'product_relation_id' => $relation->id,
                'attribute_id' => $attrResult->id,
                'language' => $av['language'] ?? null,
                'multiplied_index' => $av['multiplied_index'] ?? 0,
            ];

            $data = array_filter([
                'value_string' => $av['value_string'] ?? null,
                'value_number' => $av['value_number'] ?? null,
                'value_date' => $av['value_date'] ?? null,
                'value_flag' => $av['value_flag'] ?? null,
                'value_selection_id' => $av['value_selection_id'] ?? null,
                'unit_id' => $unitId,
            ], fn ($v) => $v !== null);

            ProductRelationAttributeValue::updateOrCreate($key, $data);
        }
    }

    private function importPrices(array $rows, string $sheetKey): void
    {
        $rowCount = 0;
        foreach ($rows as $row) {
            if (++$rowCount % 50 === 0) {
                $this->heartbeat();
            }
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

    /**
     * Bulk-Import für Preise: upsert() in Chunks für Zeilen mit valid_from,
     * Fallback auf Einzel-Insert/Update für Zeilen ohne valid_from (NULL in UNIQUE-Index).
     */
    private function importPricesBulk(array $rows, string $sheetKey): void
    {
        $now = now();

        // Preistypen vorab auflösen und bei Bedarf anlegen
        $priceTypeNames = array_unique(array_column($rows, 'price_type'));
        $priceTypeMap = [];
        foreach ($priceTypeNames as $ptName) {
            $ptResult = $this->resolver->resolvePriceType($ptName);
            if ($ptResult->resolved()) {
                $priceTypeMap[$ptName] = $ptResult->id;
            } else {
                $priceType = PriceType::firstOrCreate(
                    ['technical_name' => $ptName],
                    ['id' => Str::uuid()->toString(), 'name_de' => $ptName]
                );
                $priceTypeMap[$ptName] = $priceType->id;
            }
        }
        $this->resolver->clearCache('price_type');

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->heartbeat();

            // Alle Produkt-IDs im Chunk sammeln
            $skusInChunk = array_unique(array_column($chunk, 'sku'));
            $productIdMap = [];
            foreach ($skusInChunk as $sku) {
                $r = $this->resolver->resolveProduct($sku);
                if ($r->resolved()) {
                    $productIdMap[$sku] = $r->id;
                }
            }

            $upsertData = [];
            $nullValidFromRows = [];
            $productIds = array_values($productIdMap);

            // Bestehende Preise laden für Fallback und Stats
            $existingPricesMap = [];
            if (!empty($productIds)) {
                $allExisting = ProductPrice::whereIn('product_id', $productIds)->get();
                foreach ($allExisting as $ep) {
                    $key = $ep->product_id . '|' . $ep->price_type_id . '|' . $ep->currency
                        . '|' . ($ep->valid_from ?? '') . '|' . ($ep->scale_from ?? '');
                    $existingPricesMap[$key] = $ep;
                }
            }

            foreach ($chunk as $row) {
                try {
                    $productId = $productIdMap[$row['sku']] ?? null;
                    if (!$productId) {
                        $this->logSkipped($sheetKey, $row, "Produkt nicht gefunden: SKU '{$row['sku']}'");
                        continue;
                    }

                    $priceTypeId = $priceTypeMap[$row['price_type']] ?? null;
                    if (!$priceTypeId) {
                        $this->logSkipped($sheetKey, $row, "Preistyp nicht gefunden: '{$row['price_type']}'");
                        continue;
                    }

                    $currency = strtoupper((string) ($row['currency'] ?? 'EUR'));
                    $validFrom = $row['valid_from'] ?? null;

                    $data = [
                        'product_id' => $productId,
                        'price_type_id' => $priceTypeId,
                        'amount' => (float) $row['amount'],
                        'currency' => $currency,
                        'valid_from' => $validFrom,
                        'valid_to' => $row['valid_to'] ?? null,
                        'country' => !empty($row['country']) ? strtoupper((string) $row['country']) : null,
                        'scale_from' => !empty($row['scale_from']) ? (int) $row['scale_from'] : null,
                        'scale_to' => !empty($row['scale_to']) ? (int) $row['scale_to'] : null,
                    ];

                    $scaleFrom = !empty($row['scale_from']) ? (int) $row['scale_from'] : null;
                    $lookupKey = $productId . '|' . $priceTypeId . '|' . $currency
                        . '|' . ($validFrom ?? '') . '|' . ($scaleFrom ?? '');
                    $isExisting = isset($existingPricesMap[$lookupKey]);

                    if ($validFrom === null || $scaleFrom === null) {
                        // NULL in unique-key Spalten: Fallback auf Einzel-Insert/Update
                        $existingPrice = $existingPricesMap[$lookupKey] ?? null;
                        if ($existingPrice) {
                            $existingPrice->update($data);
                            $this->stats[$sheetKey]['updated']++;
                        } else {
                            $data['id'] = Str::uuid()->toString();
                            $data['updated_at'] = $now;
                            $data['created_at'] = $now;
                            ProductPrice::create($data);
                            $this->stats[$sheetKey]['created']++;
                        }
                    } else {
                        // Alle unique-key Spalten non-null: sammeln für Batch-Upsert
                        $data['id'] = Str::uuid()->toString();
                        $data['updated_at'] = $now;
                        $data['created_at'] = $now;
                        $upsertData[] = $data;

                        if ($isExisting) {
                            $this->stats[$sheetKey]['updated']++;
                        } else {
                            $this->stats[$sheetKey]['created']++;
                        }
                    }

                    $this->affectedProductIds[] = $productId;
                } catch (\Throwable $e) {
                    $this->logRowError($sheetKey, $row, $e);
                }
            }

            // Preise mit valid_from als Batch-Upsert
            if (!empty($upsertData)) {
                ProductPrice::upsert(
                    $upsertData,
                    ['product_id', 'price_type_id', 'currency', 'valid_from', 'scale_from'],
                    ['amount', 'valid_to', 'country', 'scale_to', 'updated_at']
                );
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
                    'usage_type_id' => $this->resolveUsageTypeId($row['usage_type'] ?? 'gallery'),
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
     * Resolves a usage_type technical_name to its ID.
     */
    private function resolveUsageTypeId(string $technicalName): ?string
    {
        $type = MediaUsageType::where('technical_name', $technicalName)->first();

        if ($type) {
            return $type->id;
        }

        // Fallback: try 'gallery', then first available
        $fallback = MediaUsageType::where('technical_name', 'gallery')->first()
            ?? MediaUsageType::orderBy('sort_order')->first();

        return $fallback?->id;
    }

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

    // ── Attribut-Sichten ──────────────────────────────────────────────────

    private function importAttributeViews(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
                $existing = AttributeView::where('technical_name', $row['technical_name'])->first();

                $data = [
                    'technical_name' => $row['technical_name'],
                    'name_de' => $row['name_de'],
                    'name_en' => $row['name_en'] ?? null,
                    'description' => $row['description'] ?? null,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_write_protected' => $this->toBool($row['is_write_protected'] ?? null),
                ];

                if ($existing) {
                    $existing->update($data);
                    $this->stats[$sheetKey]['updated']++;
                } else {
                    $data['id'] = Str::uuid()->toString();
                    AttributeView::create($data);
                    $this->stats[$sheetKey]['created']++;
                }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    // ── Preistypen ────────────────────────────────────────────────────────

    private function importPriceTypes(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
                $existing = PriceType::where('technical_name', $row['technical_name'])->first();

                $data = [
                    'technical_name' => $row['technical_name'],
                    'name_de' => $row['name_de'],
                    'name_en' => $row['name_en'] ?? null,
                ];

                if ($existing) {
                    $existing->update($data);
                    $this->stats[$sheetKey]['updated']++;
                } else {
                    $data['id'] = Str::uuid()->toString();
                    PriceType::create($data);
                    $this->stats[$sheetKey]['created']++;
                }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }

    // ── Beziehungstypen ───────────────────────────────────────────────────

    private function importRelationTypes(array $rows, string $sheetKey): void
    {
        foreach ($rows as $row) {
            try {
                $existing = ProductRelationType::where('technical_name', $row['technical_name'])->first();

                $data = [
                    'technical_name' => $row['technical_name'],
                    'name_de' => $row['name_de'],
                    'name_en' => $row['name_en'] ?? null,
                    'is_bidirectional' => $this->toBool($row['is_bidirectional'] ?? null),
                ];

                if ($existing) {
                    $existing->update($data);
                    $this->stats[$sheetKey]['updated']++;
                } else {
                    $data['id'] = Str::uuid()->toString();
                    ProductRelationType::create($data);
                    $this->stats[$sheetKey]['created']++;
                }
            } catch (\Throwable $e) {
                $this->logRowError($sheetKey, $row, $e);
            }
        }
    }
}
