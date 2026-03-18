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
use App\Exceptions\ImportCancelledException;
use Illuminate\Support\Facades\Cache;
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

    /** Import-ID für Cancel-Tracking. */
    private ?string $importId = null;

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
     * Setzt die Import-ID für Cancel-Tracking.
     */
    public function setImportId(string $importId): void
    {
        $this->importId = $importId;
    }

    /**
     * Batch-UPDATE via Temporary Table + JOIN — 1 UPDATE statt N Einzel-Queries.
     *
     * CREATE TEMPORARY TABLE _bulk_upd (...) ENGINE=MEMORY;
     * INSERT INTO _bulk_upd VALUES (...), (...);
     * UPDATE target_table t JOIN _bulk_upd u ON t.id = u.id SET t.col = u.col;
     * DROP TEMPORARY TABLE _bulk_upd;
     *
     * @param string   $table     Zieltabelle
     * @param array    $rows      Array von Zeilen, jede mit 'id' und den zu updatenden Spalten
     * @param string[] $columns   Spaltennamen die geupdated werden sollen
     * @param int      $batchSize Max Zeilen pro Temp-Table (Default 2000)
     */
    /** Erlaubte Tabellen für Bulk-Operationen (SQL-Injection-Schutz). */
    private const ALLOWED_BULK_TABLES = [
        'products', 'product_attribute_values', 'product_prices',
        'product_relations', 'product_relation_attribute_values',
        'product_media_assignments', 'output_hierarchy_product_assignments',
    ];

    private function bulkUpdateById(string $table, array $rows, array $columns, int $batchSize = 2000): void
    {
        if (empty($rows) || empty($columns)) {
            return;
        }
        if (!in_array($table, self::ALLOWED_BULK_TABLES, true)) {
            throw new \InvalidArgumentException("Tabelle '$table' ist nicht für Bulk-Operationen freigegeben");
        }
        // Spalten-Validierung: nur alphanumerische Zeichen + Unterstrich
        foreach ($columns as $col) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
                throw new \InvalidArgumentException("Ungültiger Spaltenname: '$col'");
            }
        }
        $startTime = microtime(true);

        foreach (array_chunk($rows, $batchSize) as $chunk) {
            // 1. Temp-Table erstellen
            $colDefs = ['`id` CHAR(36) NOT NULL PRIMARY KEY'];
            foreach ($columns as $col) {
                // Typ-Erkennung aus erstem Wert
                $sample = $chunk[0][$col] ?? null;
                if ($sample instanceof \DateTimeInterface || $sample instanceof \Carbon\Carbon) {
                    $colDefs[] = "`$col` DATETIME NULL";
                } elseif (is_float($sample)) {
                    $colDefs[] = "`$col` DECIMAL(20,6) NULL";
                } elseif (is_int($sample)) {
                    $colDefs[] = "`$col` BIGINT NULL";
                } else {
                    $colDefs[] = "`$col` TEXT NULL";
                }
            }

            DB::statement("CREATE TEMPORARY TABLE `_bulk_upd` (" . implode(', ', $colDefs) . ") ENGINE=MEMORY");

            try {
                // 2. Multi-row INSERT in Temp-Table
                $allCols = array_merge(['id'], $columns);
                $insertRows = [];
                foreach ($chunk as $row) {
                    $insertRow = [];
                    foreach ($allCols as $c) {
                        $val = $row[$c] ?? null;
                        // Carbon/DateTime zu String konvertieren
                        if ($val instanceof \DateTimeInterface) {
                            $val = $val->format('Y-m-d H:i:s');
                        }
                        $insertRow[$c] = $val;
                    }
                    $insertRows[] = $insertRow;
                }
                DB::table('_bulk_upd')->insert($insertRows);

                // 3. JOIN UPDATE: 1 Query für alle Zeilen
                $setParts = [];
                foreach ($columns as $col) {
                    $setParts[] = "`t`.`$col` = `u`.`$col`";
                }
                $sql = "UPDATE `$table` `t` JOIN `_bulk_upd` `u` ON `t`.`id` = `u`.`id` SET " . implode(', ', $setParts);
                DB::statement($sql);
            } finally {
                // 4. Temp-Table aufräumen
                DB::statement("DROP TEMPORARY TABLE IF EXISTS `_bulk_upd`");
            }
        }
        Log::channel('import')->debug("bulkUpdateById({$table}): " . count($rows) . " Zeilen", [
            'dauer_sek' => round(microtime(true) - $startTime, 2),
        ]);
    }

    /**
     * Batch-DELETE via Temporary Table + JOIN — 1 DELETE statt N whereIn-Chunks.
     *
     * @param string $table      Zieltabelle aus der gelöscht wird
     * @param string $joinColumn Spalte in $table die mit den IDs gematcht wird (z.B. 'product_id')
     * @param array  $ids        Array von IDs die gelöscht werden sollen
     * @param int    $batchSize  Max IDs pro Temp-Table (Default 10000)
     */
    private function bulkDeleteViaTempTable(string $table, string $joinColumn, array $ids, int $batchSize = 10000): void
    {
        if (empty($ids)) {
            return;
        }
        if (!in_array($table, self::ALLOWED_BULK_TABLES, true)) {
            throw new \InvalidArgumentException("Tabelle '$table' ist nicht für Bulk-Operationen freigegeben");
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $joinColumn)) {
            throw new \InvalidArgumentException("Ungültiger Spaltenname: '$joinColumn'");
        }
        $startTime = microtime(true);

        foreach (array_chunk($ids, $batchSize) as $idChunk) {
            DB::statement("CREATE TEMPORARY TABLE `_bulk_del` (`id` CHAR(36) NOT NULL PRIMARY KEY) ENGINE=MEMORY");

            try {
                // Multi-row INSERT der zu löschenden IDs
                $insertData = array_map(fn ($id) => ['id' => $id], $idChunk);
                DB::table('_bulk_del')->insert($insertData);

                // JOIN DELETE: 1 Query statt N whereIn-Chunks
                DB::statement("DELETE `t` FROM `$table` `t` JOIN `_bulk_del` `d` ON `t`.`$joinColumn` = `d`.`id`");
            } finally {
                DB::statement("DROP TEMPORARY TABLE IF EXISTS `_bulk_del`");
            }
        }
        Log::channel('import')->debug("bulkDeleteViaTempTable({$table}.{$joinColumn}): " . count($ids) . " IDs", [
            'dauer_sek' => round(microtime(true) - $startTime, 2),
        ]);
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
     * Prüft ob der Import abgebrochen wurde.
     *
     * @throws ImportCancelledException
     */
    private function checkCancelled(): void
    {
        if ($this->importId === null) {
            return;
        }

        if (Cache::get("bmecat_import_cancel_{$this->importId}")) {
            Cache::forget("bmecat_import_cancel_{$this->importId}");
            throw new ImportCancelledException($this->importId);
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

            // MySQL Session-Optimierung: Constraint-Checks deaktivieren
            // Spart erheblich Disk-I/O bei Sekundär-Indizes und FK-Prüfungen.
            // Daten-Integrität ist gewährleistet durch In-Memory Constraint-Checks.
            DB::statement("SET SESSION unique_checks=0");
            DB::statement("SET SESSION foreign_key_checks=0");
            Log::channel('import')->info('MySQL Session-Optimierung: unique_checks=0, foreign_key_checks=0');
        }

        try {

        foreach ($sheetOrder as $sheetKey) {
            if (!$parseResult->hasSheet($sheetKey)) {
                continue;
            }

            $rows = $parseResult->getSheetData($sheetKey);
            if (empty($rows)) {
                continue;
            }

            // Cancel-Check vor jedem Sheet
            $this->checkCancelled();

            $this->stats[$sheetKey] = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

            $sheetStartTime = microtime(true);
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
                '12_Produktbeziehungen' => 'importProductRelationsBulk',
                '13_Preise' => 'importPricesBulk',
                default => null,
            };

            $chosenMethod = ($useBulk && $bulkMethod) ? $bulkMethod : $method;

            if ($chosenMethod && method_exists($this, $chosenMethod)) {
                if ($useBulk && $bulkMethod) {
                    // Bulk-Methoden verwalten Transaktionen selbst per Chunk.
                    // Eine einzelne Transaktion über 110K+ Zeilen ist zu groß und
                    // verursacht Rollback-Verlust wenn irgendwas fehlschlägt.
                    try {
                        $this->{$chosenMethod}($rows, $sheetKey);
                    } catch (\Throwable $e) {
                        Log::channel('import')->error("Sheet {$sheetKey} fehlgeschlagen (Bulk)", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $this->stats[$sheetKey]['errors'] = count($rows);
                    }
                } else {
                    // Normale Methoden: Sheet-weite Transaktion (kleine Datenmengen)
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
            }

            $completedSheets++;
            $sheetDuration = round(microtime(true) - $sheetStartTime, 2);
            $rowsPerSec = $sheetDuration > 0 ? round(count($rows) / $sheetDuration) : '∞';
            Log::channel('import')->info("Sheet {$sheetKey} fertig", [
                'dauer_sek' => $sheetDuration,
                'zeilen' => count($rows),
                'zeilen_pro_sek' => $rowsPerSec,
                'stats' => $this->stats[$sheetKey],
            ]);

            // Cache nach Stammdaten-Import leeren
            if (in_array($sheetKey, [
                '01_Produkttypen', '02_Attributgruppen', '03_Einheiten',
                '04_Wertelisten', '05_Attribute', '06_Hierarchien', '08_Produkte',
                '15_Attribut_Sichten', '16_Preistypen', '17_Beziehungstypen',
            ])) {
                $this->resolver->clearCache();
            }
        }

        } finally {
            // MySQL Session-Optimierung zurücksetzen (egal ob Erfolg oder Fehler)
            if ($useBulk) {
                DB::statement("SET SESSION unique_checks=1");
                DB::statement("SET SESSION foreign_key_checks=1");
                Log::channel('import')->info('MySQL Session-Optimierung zurückgesetzt');
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
            // Produkt-IDs sammeln
            $productIds = [];
            foreach (array_chunk($allSkus, 5000) as $skuChunk) {
                $productIds = array_merge($productIds, Product::whereIn('sku', $skuChunk)->pluck('id')->toArray());
            }

            if (!empty($productIds)) {
                // Abhängige Daten via Temp-Table JOIN DELETE löschen (1 Query pro Tabelle)
                $this->bulkDeleteViaTempTable('product_relations', 'source_product_id', $productIds);
                $this->bulkDeleteViaTempTable('product_relations', 'target_product_id', $productIds);
                $this->bulkDeleteViaTempTable('product_prices', 'product_id', $productIds);
                $this->bulkDeleteViaTempTable('product_media_assignments', 'product_id', $productIds);
                $this->bulkDeleteViaTempTable('product_attribute_values', 'product_id', $productIds);
                $this->bulkDeleteViaTempTable('output_hierarchy_product_assignments', 'product_id', $productIds);

                // Varianten vor Elternprodukten löschen (Reihenfolge wichtig wegen parent_product_id FK)
                // Hier nutzen wir die id-Spalte direkt, mit Filter auf product_type_ref
                $variantIds = DB::table('products')
                    ->whereIn('id', $productIds)
                    ->where('product_type_ref', 'variant')
                    ->pluck('id')->toArray();
                if (!empty($variantIds)) {
                    $this->bulkDeleteViaTempTable('products', 'id', $variantIds);
                }

                $parentIds = DB::table('products')
                    ->whereIn('id', $productIds)
                    ->where('product_type_ref', 'product')
                    ->pluck('id')->toArray();
                if (!empty($parentIds)) {
                    $this->bulkDeleteViaTempTable('products', 'id', $parentIds);
                }

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
        $chunkSize = 2000;
        $totalRows = count($rows);
        $totalChunks = (int) ceil($totalRows / $chunkSize);
        $chunkIndex = 0;
        $isDeleteInsert = $this->mode === 'delete_insert';

        // ── In-Memory Constraint-Map: SKU → existing UUID ──
        $constraintMapStart = microtime(true);
        $existingSkuMap = []; // sku → id
        if (!$isDeleteInsert) {
            $allSkus = array_column($rows, 'sku');
            foreach (array_chunk($allSkus, 5000) as $skuChunk) {
                $existing = DB::table('products')->whereIn('sku', $skuChunk)->pluck('id', 'sku')->all();
                $existingSkuMap = array_merge($existingSkuMap, $existing);
                $this->heartbeat();
            }
            Log::channel('import')->info("Produkt-Constraint-Map geladen: " . count($existingSkuMap) . " bestehende Produkte", [
                'dauer_sek' => round(microtime(true) - $constraintMapStart, 2),
            ]);
        }

        $updateColumns = ['name', 'product_type_id', 'ean', 'status', 'product_type_ref', 'updated_at'];

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $this->heartbeat();
            $this->checkCancelled();
            $chunkIndex++;

            if ($this->progressCallback) {
                $processedRows = min($chunkIndex * $chunkSize, $totalRows);
                ($this->progressCallback)($sheetKey, $processedRows, $totalRows, $this->stats);
            }

            $insertBatch = [];
            $updateBatch = [];

            DB::beginTransaction();
            try {
                foreach ($chunk as $row) {
                    try {
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
                            'updated_at' => $now,
                        ];

                        if (isset($existingSkuMap[$row['sku']])) {
                            // UPDATE: existiert → ID aus Map
                            $data['id'] = $existingSkuMap[$row['sku']];
                            $updateBatch[] = $data;
                        } else {
                            // INSERT: neu → UUID generieren
                            $data['id'] = Str::uuid()->toString();
                            $data['created_at'] = $now;
                            $insertBatch[] = $data;
                            $existingSkuMap[$row['sku']] = $data['id'];
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

                // ── Reines INSERT für neue Produkte ──
                if (!empty($insertBatch)) {
                    try {
                        DB::table('products')->insert($insertBatch);
                        $this->stats[$sheetKey]['created'] += count($insertBatch);
                    } catch (\Throwable $batchError) {
                        // Fallback: Einzelinserts um möglichst viele Produkte zu retten
                        Log::channel('import')->warning("Produkt-Batch-Insert fehlgeschlagen, Fallback auf Einzelinserts", [
                            'error' => $batchError->getMessage(),
                            'batch_size' => count($insertBatch),
                        ]);
                        foreach ($insertBatch as $singleRow) {
                            try {
                                DB::table('products')->insert($singleRow);
                                $this->stats[$sheetKey]['created']++;
                            } catch (\Throwable $singleError) {
                                Log::channel('import')->warning("Produkt-Insert fehlgeschlagen: SKU '{$singleRow['sku']}'", [
                                    'error' => $singleError->getMessage(),
                                ]);
                                $this->stats[$sheetKey]['errors']++;
                            }
                        }
                    }
                }

                // ── Batch-UPDATE per CASE WHEN (1 Query statt N) ──
                if (!empty($updateBatch)) {
                    $this->bulkUpdateById('products', $updateBatch, $updateColumns);
                    $this->stats[$sheetKey]['updated'] += count($updateBatch);
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::channel('import')->error("Produkt-Chunk {$chunkIndex}/{$totalChunks} fehlgeschlagen", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->stats[$sheetKey]['errors'] += count($chunk);
            }
        }

        // Produkt-IDs sammeln (aus existingSkuMap — kein extra DB-Query nötig!)
        array_push($this->affectedProductIds, ...array_values($existingSkuMap));

        // Resolver-Cache neu aufbauen nach Produkt-Import
        $this->resolver->clearCache('product');

        // English names batch-verarbeiten (reines INSERT, da Attributwerte danach kommen)
        if ($nameAttribute && !empty($englishNameRows)) {
            $pavData = [];
            foreach ($englishNameRows as $enRow) {
                $productId = $existingSkuMap[$enRow['sku']] ?? null;
                if ($productId) {
                    $pavData[] = [
                        'id' => Str::uuid()->toString(),
                        'product_id' => $productId,
                        'attribute_id' => $nameAttribute->id,
                        'language' => 'en',
                        'multiplied_index' => 0,
                        'value_string' => $enRow['name_en'],
                        'value_number' => null,
                        'value_date' => null,
                        'value_flag' => null,
                        'value_selection_id' => null,
                        'unit_id' => null,
                        'output_hierarchy_id' => null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ];
                }
            }
            if (!empty($pavData)) {
                foreach (array_chunk($pavData, 2000) as $pavChunk) {
                    DB::table('product_attribute_values')->insert($pavChunk);
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

            $outputHierarchyId = $row['output_hierarchy_id'] ?? null;
            $existing = ProductAttributeValue::where('product_id', $productResult->id)
                ->where('attribute_id', $attrResult->id)
                ->where('language', $language)
                ->where('multiplied_index', $index)
                ->where('output_hierarchy_id', $outputHierarchyId)
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
        $chunkSize = 2000;
        $totalRows = count($rows);
        $totalChunks = (int) ceil($totalRows / $chunkSize);
        $chunkIndex = 0;
        $isDeleteInsert = $this->mode === 'delete_insert';

        // Attribut-Cache vorwärmen
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

        // ── In-Memory Constraint-Map aufbauen ──────────────────────────
        $constraintMapStart = microtime(true);
        $existingMap = [];
        if (!$isDeleteInsert) {
            // Nur die betroffenen Produkt-IDs laden (aus den Import-Daten)
            $allSkus = array_unique(array_column($rows, 'sku'));
            $affectedProductIds = [];
            foreach (array_chunk($allSkus, 5000) as $skuChunk) {
                foreach ($skuChunk as $sku) {
                    $r = $this->resolver->resolveProduct($sku);
                    if ($r->resolved()) {
                        $affectedProductIds[$sku] = $r->id;
                    }
                }
            }
            $uniqueProductIds = array_unique(array_values($affectedProductIds));

            $this->heartbeat();
            Log::channel('import')->info("Lade bestehende Attributwerte für " . count($uniqueProductIds) . " Produkte in Memory...");

            foreach (array_chunk($uniqueProductIds, 5000) as $pidChunk) {
                $existing = DB::table('product_attribute_values')
                    ->whereIn('product_id', $pidChunk)
                    ->select(['id', 'product_id', 'attribute_id', 'language', 'multiplied_index', 'output_hierarchy_id'])
                    ->get();
                foreach ($existing as $e) {
                    $key = $e->product_id . '|' . $e->attribute_id . '|' . ($e->language ?? '_NULL_') . '|' . $e->multiplied_index . '|' . ($e->output_hierarchy_id ?? '_NULL_');
                    $existingMap[$key] = $e->id;
                }
                $this->heartbeat();
            }

            Log::channel('import')->info("Constraint-Map geladen: " . count($existingMap) . " bestehende Werte", [
                'dauer_sek' => round(microtime(true) - $constraintMapStart, 2),
            ]);
        }

        // Default-Zeile für konsistente Spalten
        $defaultRow = [
            'id' => null, 'product_id' => null, 'attribute_id' => null,
            'language' => null, 'multiplied_index' => 0,
            'value_string' => null, 'value_number' => null, 'value_date' => null,
            'value_flag' => null, 'value_selection_id' => null, 'unit_id' => null,
            'output_hierarchy_id' => null,
            'updated_at' => $now, 'created_at' => $now,
        ];

        $updateColumns = ['value_string', 'value_number', 'value_date', 'value_flag', 'value_selection_id', 'unit_id', 'output_hierarchy_id', 'updated_at'];

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $this->heartbeat();
            $this->checkCancelled();
            $chunkIndex++;

            if ($this->progressCallback) {
                $processedRows = min($chunkIndex * $chunkSize, $totalRows);
                ($this->progressCallback)($sheetKey, $processedRows, $totalRows, $this->stats);
            }

            $insertBatch = [];
            $updateBatch = [];
            $affectedIds = [];

            DB::beginTransaction();
            try {

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

                    $attribute = $attributeById->get($attrResult->id);
                    $valueData = $this->mapValueToColumns($row['value'], $attribute?->data_type ?? 'String');

                    $data = $defaultRow;
                    $data['product_id'] = $productResult->id;
                    $data['attribute_id'] = $attrResult->id;
                    $data['language'] = $language;
                    $data['multiplied_index'] = $index;
                    foreach ($valueData as $k => $v) {
                        $data[$k] = $v;
                    }

                    // Selection-Werte auflösen
                    if ($attribute && $attribute->data_type === 'Selection' && $attribute->value_list_id) {
                        $vlEntries = $valueListEntries[$attribute->value_list_id] ?? [];
                        $entry = $vlEntries[(string) $row['value']] ?? $vlEntries['_display_' . (string) $row['value']] ?? null;
                        if ($entry) {
                            $data['value_selection_id'] = $entry->id;
                            $data['value_string'] = $entry->technical_name;
                        }
                    }

                    // Einheit auflösen
                    if (!empty($row['unit'])) {
                        $unitResult = $this->resolver->resolveUnit($row['unit']);
                        if ($unitResult->resolved()) {
                            $data['unit_id'] = $unitResult->id;
                        }
                    }

                    // ── In-Memory Constraint-Check ──
                    $outputHierarchyId = $data['output_hierarchy_id'] ?? null;
                    $constraintKey = $productResult->id . '|' . $attrResult->id . '|' . ($language ?? '_NULL_') . '|' . $index . '|' . ($outputHierarchyId ?? '_NULL_');

                    if (isset($existingMap[$constraintKey])) {
                        // UPDATE: existiert schon → ID übernehmen, in Update-Batch
                        $data['id'] = $existingMap[$constraintKey];
                        $updateBatch[] = $data;
                    } else {
                        // INSERT: neu → UUID generieren, in Insert-Batch
                        $data['id'] = Str::uuid()->toString();
                        $insertBatch[] = $data;
                        // In Map eintragen damit Duplikate im selben Chunk erkannt werden
                        $existingMap[$constraintKey] = $data['id'];
                    }

                    $affectedIds[] = $productResult->id;
                } catch (\Throwable $e) {
                    $this->logRowError($sheetKey, $row, $e);
                }
            }

            // ── Reines INSERT für neue Zeilen (kein ON DUPLICATE KEY overhead) ──
            if (!empty($insertBatch)) {
                foreach (array_chunk($insertBatch, 2000) as $batchChunk) {
                    try {
                        DB::table('product_attribute_values')->insert($batchChunk);
                        $this->stats[$sheetKey]['created'] += count($batchChunk);
                    } catch (\Throwable $batchError) {
                        // Fallback: Einzelinserts um möglichst viele Werte zu retten
                        Log::channel('import')->warning("Attributwerte-Batch-Insert fehlgeschlagen, Fallback auf Einzelinserts", [
                            'error' => $batchError->getMessage(),
                            'batch_size' => count($batchChunk),
                        ]);
                        foreach ($batchChunk as $singleRow) {
                            try {
                                DB::table('product_attribute_values')->insert($singleRow);
                                $this->stats[$sheetKey]['created']++;
                            } catch (\Throwable $singleError) {
                                $this->stats[$sheetKey]['errors']++;
                            }
                        }
                    }
                }
            }

            // ── Batch-UPDATE per CASE WHEN (1 Query statt N) ──
            if (!empty($updateBatch)) {
                $this->bulkUpdateById('product_attribute_values', $updateBatch, $updateColumns);
                $this->stats[$sheetKey]['updated'] += count($updateBatch);
            }

            // array_push statt array_merge: O(k) statt O(n) — vermeidet
            // progressiven Slowdown bei 1M+ Einträgen.
            array_push($this->affectedProductIds, ...$affectedIds);

            DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::channel('import')->error("Produktwerte-Chunk {$chunkIndex}/{$totalChunks} fehlgeschlagen", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->stats[$sheetKey]['errors'] += count($chunk);
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
            foreach (array_chunk($productIds, 5000) as $idChunk) {
                DB::beginTransaction();
                try {
                    Product::whereIn('id', $idChunk)->update(['master_hierarchy_node_id' => $nodeId]);
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    Log::channel('import')->error("Master-Hierarchie-Update fehlgeschlagen", ['error' => $e->getMessage()]);
                    $this->stats[$sheetKey]['errors'] += count($idChunk);
                }
            }
            $this->stats[$sheetKey]['updated'] += count($productIds);
        }

        // Output-Hierarchien upsert
        if (!empty($outputUpsertData)) {
            foreach (array_chunk($outputUpsertData, 500) as $chunk) {
                DB::beginTransaction();
                try {
                    OutputHierarchyProductAssignment::upsert(
                        $chunk,
                        ['hierarchy_node_id', 'product_id'],
                        ['sort_order', 'updated_at']
                    );
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    Log::channel('import')->error("Output-Hierarchie-Upsert fehlgeschlagen", ['error' => $e->getMessage()]);
                    $this->stats[$sheetKey]['errors'] += count($chunk);
                }
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

    /**
     * Bulk-Import für Produktbeziehungen: upsert() in Chunks statt Einzel-Queries.
     */
    private function importProductRelationsBulk(array $rows, string $sheetKey): void
    {
        $now = now();
        $chunkSize = 2000;
        $totalChunks = (int) ceil(count($rows) / $chunkSize);
        $chunkIndex = 0;
        $totalRows = count($rows);
        $isDeleteInsert = $this->mode === 'delete_insert';

        // Beziehungstypen vorab auflösen und bei Bedarf anlegen
        $relationTypeNames = array_unique(array_column($rows, 'relation_type'));
        $relationTypeMap = [];
        foreach ($relationTypeNames as $rtName) {
            $rtResult = $this->resolver->resolveRelationType($rtName);
            if ($rtResult->resolved()) {
                $relationTypeMap[$rtName] = $rtResult->id;
            } else {
                $relationType = ProductRelationType::firstOrCreate(
                    ['technical_name' => $rtName],
                    ['id' => Str::uuid()->toString(), 'name_de' => $rtName]
                );
                $relationTypeMap[$rtName] = $relationType->id;
            }
        }
        $this->resolver->clearCache('relation_type');

        // ── SKU → Product-ID Map einmalig aufbauen ─────────────────────
        $allSkus = array_unique(array_merge(
            array_column($rows, 'source_sku'),
            array_column($rows, 'target_sku')
        ));
        $productIdMap = [];
        foreach ($allSkus as $sku) {
            $r = $this->resolver->resolveProduct($sku);
            if ($r->resolved()) {
                $productIdMap[$sku] = $r->id;
            }
        }
        $this->heartbeat();

        // ── In-Memory Constraint-Map einmalig aufbauen ─────────────────
        $existingMap = [];
        if (!$isDeleteInsert) {
            $constraintMapStart = microtime(true);
            $sourceProductIds = [];
            foreach ($rows as $row) {
                $sid = $productIdMap[$row['source_sku']] ?? null;
                if ($sid) $sourceProductIds[$sid] = true;
            }
            $uniqueSourceIds = array_keys($sourceProductIds);

            Log::channel('import')->info("Lade bestehende Beziehungen für " . count($uniqueSourceIds) . " Quell-Produkte in Memory...");

            foreach (array_chunk($uniqueSourceIds, 5000) as $sidChunk) {
                $existing = DB::table('product_relations')
                    ->whereIn('source_product_id', $sidChunk)
                    ->select(['id', 'source_product_id', 'target_product_id', 'relation_type_id'])
                    ->get();
                foreach ($existing as $e) {
                    $key = $e->source_product_id . '|' . $e->target_product_id . '|' . $e->relation_type_id;
                    $existingMap[$key] = $e->id;
                }
                $this->heartbeat();
            }

            Log::channel('import')->info("Beziehungs-Constraint-Map geladen: " . count($existingMap) . " bestehende Beziehungen", [
                'dauer_sek' => round(microtime(true) - $constraintMapStart, 2),
            ]);
        }

        $defaultRow = [
            'id' => null, 'source_product_id' => null, 'target_product_id' => null,
            'relation_type_id' => null, 'sort_order' => 0,
            'updated_at' => $now, 'created_at' => $now,
        ];

        $updateColumns = ['sort_order', 'updated_at'];

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $this->heartbeat();
            $this->checkCancelled();
            $chunkIndex++;

            if ($this->progressCallback) {
                $processedRows = min($chunkIndex * $chunkSize, $totalRows);
                ($this->progressCallback)($sheetKey, $processedRows, $totalRows, $this->stats);
            }

            $insertBatch = [];
            $updateBatch = [];
            $relationAttrValues = [];

            DB::beginTransaction();
            try {

            foreach ($chunk as $row) {
                try {
                    $sourceId = $productIdMap[$row['source_sku']] ?? null;
                    $targetId = $productIdMap[$row['target_sku']] ?? null;

                    if (!$sourceId || !$targetId) {
                        $missing = [];
                        if (!$sourceId) $missing[] = "Quell-SKU '{$row['source_sku']}'";
                        if (!$targetId) $missing[] = "Ziel-SKU '{$row['target_sku']}'";
                        $this->logSkipped($sheetKey, $row, "Produkt(e) nicht gefunden: " . implode(', ', $missing));
                        continue;
                    }

                    $relationTypeId = $relationTypeMap[$row['relation_type']] ?? null;
                    if (!$relationTypeId) {
                        $this->logSkipped($sheetKey, $row, "Beziehungstyp nicht gefunden: '{$row['relation_type']}'");
                        continue;
                    }

                    $constraintKey = $sourceId . '|' . $targetId . '|' . $relationTypeId;

                    $data = $defaultRow;
                    $data['source_product_id'] = $sourceId;
                    $data['target_product_id'] = $targetId;
                    $data['relation_type_id'] = $relationTypeId;
                    $data['sort_order'] = (int) ($row['sort_order'] ?? 0);

                    if (isset($existingMap[$constraintKey])) {
                        $data['id'] = $existingMap[$constraintKey];
                        $updateBatch[] = $data;
                    } else {
                        $data['id'] = Str::uuid()->toString();
                        $insertBatch[] = $data;
                        $existingMap[$constraintKey] = $data['id'];
                    }

                    $relationId = $data['id'];

                    // Attributwerte für spätere Batch-Verarbeitung sammeln
                    if (!empty($row['attribute_values']) && is_array($row['attribute_values'])) {
                        foreach ($row['attribute_values'] as $av) {
                            $relationAttrValues[] = array_merge($av, ['_relation_id' => $relationId]);
                        }
                    }
                } catch (\Throwable $e) {
                    $this->logRowError($sheetKey, $row, $e);
                }
            }

            // ── INSERT neue Beziehungen ──
            if (!empty($insertBatch)) {
                foreach (array_chunk($insertBatch, 2000) as $batchChunk) {
                    try {
                        DB::table('product_relations')->insert($batchChunk);
                        $this->stats[$sheetKey]['created'] += count($batchChunk);
                    } catch (\Throwable $batchError) {
                        Log::channel('import')->warning("Beziehungen-Batch-Insert fehlgeschlagen, Fallback auf Einzelinserts", [
                            'error' => $batchError->getMessage(),
                            'batch_size' => count($batchChunk),
                        ]);
                        foreach ($batchChunk as $singleRow) {
                            try {
                                DB::table('product_relations')->insert($singleRow);
                                $this->stats[$sheetKey]['created']++;
                            } catch (\Throwable $singleError) {
                                $this->stats[$sheetKey]['errors']++;
                            }
                        }
                    }
                }
            }

            // ── UPDATE bestehende Beziehungen ──
            if (!empty($updateBatch)) {
                $this->bulkUpdateById('product_relations', $updateBatch, $updateColumns);
                $this->stats[$sheetKey]['updated'] += count($updateBatch);
            }

            // ── Beziehungs-Attributwerte ──
            if (!empty($relationAttrValues)) {
                $ravInsertData = [];
                foreach ($relationAttrValues as $av) {
                    $attrResult = $this->resolver->resolveAttribute($av['attribute'] ?? '');
                    if (!$attrResult->resolved()) continue;

                    $unitId = null;
                    if (!empty($av['unit'])) {
                        $unitResult = $this->resolver->resolveUnit($av['unit']);
                        if ($unitResult->resolved()) $unitId = $unitResult->id;
                    }

                    $ravInsertData[] = [
                        'id' => Str::uuid()->toString(),
                        'product_relation_id' => $av['_relation_id'],
                        'attribute_id' => $attrResult->id,
                        'language' => $av['language'] ?? null,
                        'multiplied_index' => $av['multiplied_index'] ?? 0,
                        'value_string' => $av['value_string'] ?? null,
                        'value_number' => $av['value_number'] ?? null,
                        'value_date' => $av['value_date'] ?? null,
                        'value_flag' => $av['value_flag'] ?? null,
                        'value_selection_id' => $av['value_selection_id'] ?? null,
                        'unit_id' => $unitId,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ];
                }

                if (!empty($ravInsertData)) {
                    foreach (array_chunk($ravInsertData, 2000) as $ravChunk) {
                        try {
                            DB::table('product_relation_attribute_values')->insert($ravChunk);
                        } catch (\Throwable $ravError) {
                            // Fallback: Einzelinserts
                            foreach ($ravChunk as $singleRav) {
                                try {
                                    DB::table('product_relation_attribute_values')->insert($singleRav);
                                } catch (\Throwable $e) {
                                    // Skip duplicate or invalid entries
                                }
                            }
                        }
                    }
                }
            }

            DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::channel('import')->error("Produktbeziehungen-Chunk {$chunkIndex}/{$totalChunks} fehlgeschlagen", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->stats[$sheetKey]['errors'] += count($chunk);
            }
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
                'price_region_id' => !empty($row['country']) ? $this->resolvePriceRegionId(strtoupper((string) $row['country'])) : null,
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
     * Bulk-Import für Preise: In-Memory Constraint-Check + reines INSERT/UPDATE.
     */
    private function importPricesBulk(array $rows, string $sheetKey): void
    {
        $now = now();
        $chunkSize = 2000;
        $totalRows = count($rows);
        $totalChunks = (int) ceil($totalRows / $chunkSize);
        $chunkIndex = 0;
        $isDeleteInsert = $this->mode === 'delete_insert';

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

        // ── In-Memory Constraint-Map: composite-key → existing UUID ──
        $constraintMapStart = microtime(true);
        $existingMap = []; // "product_id|price_type_id|currency|valid_from|scale_from" → id
        if (!$isDeleteInsert) {
            $allSkus = array_unique(array_column($rows, 'sku'));
            $affectedProductIds = [];
            foreach ($allSkus as $sku) {
                $r = $this->resolver->resolveProduct($sku);
                if ($r->resolved()) $affectedProductIds[] = $r->id;
            }
            $affectedProductIds = array_unique($affectedProductIds);

            $this->heartbeat();
            foreach (array_chunk($affectedProductIds, 5000) as $pidChunk) {
                $existing = DB::table('product_prices')
                    ->whereIn('product_id', $pidChunk)
                    ->select(['id', 'product_id', 'price_type_id', 'currency', 'valid_from', 'scale_from'])
                    ->get();
                foreach ($existing as $e) {
                    $key = $e->product_id . '|' . $e->price_type_id . '|' . $e->currency
                        . '|' . ($e->valid_from ?? '_NULL_') . '|' . ($e->scale_from ?? '_NULL_');
                    $existingMap[$key] = $e->id;
                }
                $this->heartbeat();
            }
            Log::channel('import')->info("Preis-Constraint-Map geladen: " . count($existingMap) . " bestehende Preise", [
                'dauer_sek' => round(microtime(true) - $constraintMapStart, 2),
            ]);
        }

        $updateColumns = ['amount', 'valid_to', 'price_region_id', 'scale_to', 'updated_at'];

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $chunkIndex++;
            $this->heartbeat();
            $this->checkCancelled();

            if ($this->progressCallback) {
                $processedRows = min($chunkIndex * $chunkSize, $totalRows);
                ($this->progressCallback)($sheetKey, $processedRows, $totalRows, $this->stats);
            }

            $insertBatch = [];
            $updateBatch = [];

            DB::beginTransaction();
            try {

                foreach ($chunk as $row) {
                    try {
                        $productResult = $this->resolver->resolveProduct($row['sku']);
                        if (!$productResult->resolved()) {
                            $this->logSkipped($sheetKey, $row, "Produkt nicht gefunden: SKU '{$row['sku']}'");
                            continue;
                        }
                        $productId = $productResult->id;

                        $priceTypeId = $priceTypeMap[$row['price_type']] ?? null;
                        if (!$priceTypeId) {
                            $this->logSkipped($sheetKey, $row, "Preistyp nicht gefunden: '{$row['price_type']}'");
                            continue;
                        }

                        $currency = strtoupper((string) ($row['currency'] ?? 'EUR'));
                        $validFrom = $row['valid_from'] ?? null;
                        $scaleFrom = !empty($row['scale_from']) ? (int) $row['scale_from'] : null;

                        $data = [
                            'product_id' => $productId,
                            'price_type_id' => $priceTypeId,
                            'amount' => (float) $row['amount'],
                            'currency' => $currency,
                            'valid_from' => $validFrom,
                            'valid_to' => $row['valid_to'] ?? null,
                            'price_region_id' => !empty($row['country']) ? $this->resolvePriceRegionId(strtoupper((string) $row['country'])) : null,
                            'scale_from' => $scaleFrom,
                            'scale_to' => !empty($row['scale_to']) ? (int) $row['scale_to'] : null,
                            'updated_at' => $now,
                        ];

                        // In-Memory Constraint-Check
                        $constraintKey = $productId . '|' . $priceTypeId . '|' . $currency
                            . '|' . ($validFrom ?? '_NULL_') . '|' . ($scaleFrom ?? '_NULL_');

                        if (isset($existingMap[$constraintKey])) {
                            $data['id'] = $existingMap[$constraintKey];
                            $updateBatch[] = $data;
                        } else {
                            $data['id'] = Str::uuid()->toString();
                            $data['created_at'] = $now;
                            $insertBatch[] = $data;
                            $existingMap[$constraintKey] = $data['id'];
                        }

                        $this->affectedProductIds[] = $productId;
                    } catch (\Throwable $e) {
                        $this->logRowError($sheetKey, $row, $e);
                    }
                }

                // Reines INSERT für neue Preise
                if (!empty($insertBatch)) {
                    foreach (array_chunk($insertBatch, 2000) as $batchChunk) {
                        DB::table('product_prices')->insert($batchChunk);
                    }
                    $this->stats[$sheetKey]['created'] += count($insertBatch);
                }

                // Batch-UPDATE per CASE WHEN (1 Query statt N)
                if (!empty($updateBatch)) {
                    $this->bulkUpdateById('product_prices', $updateBatch, $updateColumns);
                    $this->stats[$sheetKey]['updated'] += count($updateBatch);
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::channel('import')->error("Preise Chunk {$chunkIndex}/{$totalChunks} fehlgeschlagen", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'chunk_skus' => array_slice(array_column($chunk, 'sku'), 0, 10),
                ]);
                $this->stats[$sheetKey]['errors'] += count($chunk);
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
        $parentPath = '/';

        for ($level = 1; $level <= 6; $level++) {
            $name = $row["level_{$level}"] ?? null;
            if (empty($name)) {
                break;
            }

            // Find existing node by parent + name (independent of path format)
            $query = HierarchyNode::where('hierarchy_id', $hierarchy->id)
                ->where('name_de', $name);

            if ($parentId === null) {
                $query->whereNull('parent_node_id');
            } else {
                $query->where('parent_node_id', $parentId);
            }

            $existing = $query->first();

            if ($existing) {
                $parentId = $existing->id;
                $parentPath = $existing->path;

                // Repair name-based path to UUID-based path if needed
                $expectedPath = ($parentId === $existing->id && $level === 1)
                    ? "/{$existing->id}/"
                    : "{$parentPath}";
                // Recalculate correct path from parent
                if ($existing->parent_node_id === null) {
                    $correctPath = "/{$existing->id}/";
                } else {
                    $parent = HierarchyNode::find($existing->parent_node_id);
                    $correctPath = $parent ? "{$parent->path}{$existing->id}/" : "/{$existing->id}/";
                }
                if ($existing->path !== $correctPath) {
                    $existing->update(['path' => $correctPath]);
                }
                $parentPath = $correctPath;

                continue;
            }

            $nodeId = Str::uuid()->toString();
            // Build UUID-based path: parent's path + this node's ID
            $path = $parentId === null
                ? "/{$nodeId}/"
                : "{$parentPath}{$nodeId}/";

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
            $parentPath = $path;
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

    private function resolvePriceRegionId(string $code): ?string
    {
        $region = \App\Models\PriceRegion::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'type' => 'country']
        );

        return $region->id;
    }
}
