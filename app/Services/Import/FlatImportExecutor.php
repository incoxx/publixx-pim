<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Attribute;
use App\Models\ImportJob;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Importiert Flat-Excel-Dateien (einfache Tabellen mit SKU + Spalten)
 * anhand von Benutzer-definierten Spalten-Mappings.
 */
class FlatImportExecutor
{
    private array $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
    private array $affectedProductIds = [];
    private array $skippedDetails = [];
    private string $mode = 'update';
    private ?ImportLogger $logger = null;
    private ?ImportJob $importJob = null;

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['update', 'delete_insert', 'delete']) ? $mode : 'update';
    }

    public function setLogger(ImportLogger $logger, ImportJob $importJob): void
    {
        $this->logger = $logger;
        $this->importJob = $importJob;
    }

    private function log(string $level, string $message, ?string $sheet = null, ?int $row = null, ?string $column = null, array $context = []): void
    {
        if (!$this->logger || !$this->importJob) {
            return;
        }

        if ($sheet !== null || $row !== null) {
            $this->logger->logRow($this->importJob, 'execution', $level, $sheet ?? 'flat', $row ?? 0, $column, $message, $context);
        } else {
            match ($level) {
                'warning' => $this->logger->warning($this->importJob, 'execution', $message, $context),
                'error' => $this->logger->error($this->importJob, 'execution', $message, $context),
                default => $this->logger->info($this->importJob, 'execution', $message, $context),
            };
        }
    }

    /**
     * Führt den Flat-Import aus.
     *
     * @param string $filePath        Pfad zur Excel-Datei
     * @param array  $columnMappings  [{source, target_attribute_id, language}]
     * @param string $skuColumn       Name der SKU-Spalte
     * @param string|null $productTypeId  Optional: Produkttyp-ID
     * @param string|null $masterHierarchyNodeId  Optional: Master-Hierarchie-Knoten
     * @param string|null $nameColumn    Optional: Spalte für den Produktnamen
     * @param string|null $eanColumn     Optional: Spalte für die EAN
     */
    public function execute(
        string $filePath,
        array $columnMappings,
        string $skuColumn = 'SKU',
        ?string $productTypeId = null,
        ?string $masterHierarchyNodeId = null,
        ?string $nameColumn = null,
        ?string $eanColumn = null,
    ): ImportExecutionResult {
        $this->stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $this->affectedProductIds = [];
        $this->skippedDetails = [];

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getSheet(0);
        $highestRow = $worksheet->getHighestRow();
        $maxCol = Coordinate::columnIndexFromString($worksheet->getHighestColumn());

        // Header lesen
        $headers = [];
        for ($col = 1; $col <= $maxCol; $col++) {
            $value = $worksheet->getCell([$col, 1])->getValue();
            $headers[$col] = trim((string) ($value ?? ''));
        }

        // SKU-Spalten-Index finden
        $skuColIdx = null;
        foreach ($headers as $colIdx => $header) {
            if (strcasecmp($header, $skuColumn) === 0) {
                $skuColIdx = $colIdx;
                break;
            }
        }

        if ($skuColIdx === null) {
            throw new \RuntimeException("SKU-Spalte '{$skuColumn}' nicht in der Datei gefunden.");
        }

        // Stammdaten-Spalten-Indizes finden (Name, EAN)
        $nameColIdx = null;
        $eanColIdx = null;
        if ($nameColumn) {
            foreach ($headers as $colIdx => $header) {
                if (strcasecmp($header, $nameColumn) === 0) {
                    $nameColIdx = $colIdx;
                    break;
                }
            }
        }
        if ($eanColumn) {
            foreach ($headers as $colIdx => $header) {
                if (strcasecmp($header, $eanColumn) === 0) {
                    $eanColIdx = $colIdx;
                    break;
                }
            }
        }

        // Mapping vorbereiten: source → {colIdx, attribute, language}
        $mappings = [];
        foreach ($columnMappings as $mapping) {
            $source = $mapping['source'] ?? '';
            $colIdx = null;
            foreach ($headers as $idx => $h) {
                if (strcasecmp($h, $source) === 0) {
                    $colIdx = $idx;
                    break;
                }
            }
            if ($colIdx === null) {
                $this->log('warning', "Quellspalte '{$source}' nicht in der Datei gefunden — wird übersprungen");
                continue;
            }

            $attribute = Attribute::find($mapping['target_attribute_id'] ?? '');
            if (!$attribute) {
                $this->log('warning', "Attribut '{$mapping['target_attribute_id']}' nicht gefunden — Spalte '{$source}' wird übersprungen");
                continue;
            }

            $mappings[] = [
                'colIdx' => $colIdx,
                'attribute' => $attribute,
                'language' => !empty($mapping['language']) ? $mapping['language'] : null,
            ];
        }

        // Produkttyp laden (Fallback auf ersten verfügbaren)
        $productType = $productTypeId ? ProductType::find($productTypeId) : ProductType::first();

        $totalRows = $highestRow - 1;
        $this->log('info', "Flat-Import gestartet: {$totalRows} Zeilen, " . count($mappings) . " Mappings, Modus: {$this->mode}", context: [
            'rows' => $totalRows,
            'mappings' => count($mappings),
            'mode' => $this->mode,
            'product_type' => $productType?->name_de,
            'hierarchy_node' => $masterHierarchyNodeId,
        ]);

        DB::beginTransaction();

        try {
            // Delete-Modi: Erst vorhandene Produkte löschen
            if (in_array($this->mode, ['delete', 'delete_insert'])) {
                $skus = [];
                for ($row = 2; $row <= $highestRow; $row++) {
                    $sku = trim((string) ($worksheet->getCell([$skuColIdx, $row])->getValue() ?? ''));
                    if ($sku !== '') {
                        $skus[] = $sku;
                    }
                }
                $deletedCount = $this->deleteProductsBySkus($skus);
                $this->log('info', "{$deletedCount} Produkte gelöscht (Modus: {$this->mode})", context: ['deleted' => $deletedCount]);

                if ($this->mode === 'delete') {
                    DB::commit();
                    $this->log('info', "Lösch-Import abgeschlossen: {$deletedCount} Produkte entfernt", context: $this->stats);
                    return new ImportExecutionResult(
                        stats: ['flat_import' => $this->stats],
                        affectedProductIds: $this->affectedProductIds,
                        skippedDetails: $this->skippedDetails,
                    );
                }
            }

            // Datenzeilen importieren
            for ($row = 2; $row <= $highestRow; $row++) {
                try {
                    $sku = trim((string) ($worksheet->getCell([$skuColIdx, $row])->getValue() ?? ''));
                    if ($sku === '') {
                        $this->skippedDetails[] = [
                            'sheet' => 'flat',
                            'row' => $row,
                            'reason' => 'Leere SKU',
                        ];
                        $this->stats['skipped']++;
                        $this->log('warning', "Leere SKU — Zeile übersprungen", 'flat', $row);
                        continue;
                    }

                    // Produkt erstellen oder aktualisieren
                    $product = Product::where('sku', $sku)->first();

                    // Stammdaten aus zugeordneten Spalten lesen
                    $name = $nameColIdx
                        ? trim((string) ($worksheet->getCell([$nameColIdx, $row])->getValue() ?? ''))
                        : '';
                    if ($name === '') {
                        $name = $sku; // Fallback: SKU als Name
                    }

                    $ean = $eanColIdx
                        ? trim((string) ($worksheet->getCell([$eanColIdx, $row])->getValue() ?? ''))
                        : null;

                    if ($product) {
                        $updateData = [
                            'name' => $name,
                            'product_type_id' => $productType?->id ?? $product->product_type_id,
                            'master_hierarchy_node_id' => $masterHierarchyNodeId ?? $product->master_hierarchy_node_id,
                        ];
                        if ($ean !== null && $ean !== '') {
                            $updateData['ean'] = $ean;
                        }
                        $product->update($updateData);
                        $this->stats['updated']++;
                        $this->log('info', "Produkt aktualisiert: {$sku}", 'flat', $row, null, ['sku' => $sku, 'action' => 'updated']);
                    } else {
                        $product = Product::create([
                            'id' => Str::uuid()->toString(),
                            'sku' => $sku,
                            'ean' => ($ean !== null && $ean !== '') ? $ean : null,
                            'name' => $name,
                            'product_type_id' => $productType?->id,
                            'product_type_ref' => 'product',
                            'status' => 'draft',
                            'master_hierarchy_node_id' => $masterHierarchyNodeId,
                        ]);
                        $this->stats['created']++;
                        $this->log('info', "Produkt angelegt: {$sku}", 'flat', $row, null, ['sku' => $sku, 'action' => 'created']);
                    }

                    $this->affectedProductIds[] = $product->id;

                    // Attributwerte schreiben
                    $valuesWritten = 0;
                    foreach ($mappings as $m) {
                        $value = $worksheet->getCell([$m['colIdx'], $row])->getValue();
                        if ($value === null || $value === '') {
                            continue;
                        }

                        $valueData = $this->mapValueToColumns($value, $m['attribute']->data_type);

                        ProductAttributeValue::updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'attribute_id' => $m['attribute']->id,
                                'language' => $m['language'],
                                'multiplied_index' => 0,
                            ],
                            $valueData,
                        );
                        $valuesWritten++;
                    }
                } catch (\Throwable $e) {
                    $this->stats['errors']++;
                    $this->skippedDetails[] = [
                        'sheet' => 'flat',
                        'row' => $row,
                        'reason' => 'Fehler: ' . $e->getMessage(),
                    ];
                    $this->log('error', "Zeile {$row}: {$e->getMessage()}", 'flat', $row);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->log('error', "Import abgebrochen: {$e->getMessage()}");
            throw $e;
        }

        $this->log('info', "Flat-Import abgeschlossen: {$this->stats['created']} erstellt, {$this->stats['updated']} aktualisiert, {$this->stats['skipped']} übersprungen, {$this->stats['errors']} Fehler", context: $this->stats);

        return new ImportExecutionResult(
            stats: ['flat_import' => $this->stats],
            affectedProductIds: array_unique($this->affectedProductIds),
            skippedDetails: $this->skippedDetails,
        );
    }

    private function deleteProductsBySkus(array $skus): int
    {
        if (empty($skus)) {
            return 0;
        }

        $productIds = Product::whereIn('sku', $skus)->pluck('id')->toArray();
        if (empty($productIds)) {
            return 0;
        }

        \App\Models\ProductRelation::whereIn('source_product_id', $productIds)
            ->orWhereIn('target_product_id', $productIds)
            ->delete();
        \App\Models\ProductPrice::whereIn('product_id', $productIds)->delete();
        \App\Models\ProductMediaAssignment::whereIn('product_id', $productIds)->delete();
        ProductAttributeValue::whereIn('product_id', $productIds)->delete();
        \App\Models\OutputHierarchyProductAssignment::whereIn('product_id', $productIds)->delete();
        Product::whereIn('id', $productIds)->where('product_type_ref', 'variant')->delete();
        Product::whereIn('id', $productIds)->where('product_type_ref', 'product')->delete();

        $this->stats['deleted'] = count($productIds);
        return count($productIds);
    }

    private function mapValueToColumns(mixed $value, string $dataType): array
    {
        return match ($dataType) {
            'Number', 'Float', 'Integer' => [
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
            'Flag', 'Boolean' => [
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

    private function toBool(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        $lower = mb_strtolower(trim((string) $value));
        return in_array($lower, ['ja', 'yes', '1', 'true', 'wahr', 'x'], true);
    }
}
