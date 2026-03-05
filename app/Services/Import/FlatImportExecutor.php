<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['update', 'delete_insert', 'delete']) ? $mode : 'update';
    }

    /**
     * Führt den Flat-Import aus.
     *
     * @param string $filePath        Pfad zur Excel-Datei
     * @param array  $columnMappings  [{source, target_attribute_id, language}]
     * @param string $skuColumn       Name der SKU-Spalte
     * @param string|null $productTypeId  Optional: Produkttyp-ID
     */
    public function execute(
        string $filePath,
        array $columnMappings,
        string $skuColumn = 'SKU',
        ?string $productTypeId = null,
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
                continue;
            }

            $attribute = Attribute::find($mapping['target_attribute_id'] ?? '');
            if (!$attribute) {
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

        Log::channel('import')->info('Flat-Import gestartet', [
            'rows' => $highestRow - 1,
            'mappings' => count($mappings),
            'mode' => $this->mode,
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
                $this->deleteProductsBySkus($skus);

                if ($this->mode === 'delete') {
                    DB::commit();
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
                        continue;
                    }

                    // Produkt erstellen oder aktualisieren
                    $product = Product::where('sku', $sku)->first();

                    // Name aus der ersten String-Mapping-Spalte oder SKU
                    $name = $sku;
                    foreach ($mappings as $m) {
                        if ($m['attribute']->technical_name === 'name') {
                            $val = $worksheet->getCell([$m['colIdx'], $row])->getValue();
                            if ($val !== null && $val !== '') {
                                $name = (string) $val;
                            }
                            break;
                        }
                    }

                    if ($product) {
                        $product->update([
                            'name' => $name,
                            'product_type_id' => $productType?->id ?? $product->product_type_id,
                        ]);
                        $this->stats['updated']++;
                    } else {
                        $product = Product::create([
                            'id' => Str::uuid()->toString(),
                            'sku' => $sku,
                            'name' => $name,
                            'product_type_id' => $productType?->id,
                            'product_type_ref' => 'product',
                            'status' => 'draft',
                        ]);
                        $this->stats['created']++;
                    }

                    $this->affectedProductIds[] = $product->id;

                    // Attributwerte schreiben
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
                    }
                } catch (\Throwable $e) {
                    $this->stats['errors']++;
                    $this->skippedDetails[] = [
                        'sheet' => 'flat',
                        'row' => $row,
                        'reason' => 'Fehler: ' . $e->getMessage(),
                    ];
                    Log::channel('import')->error("Flat-Import Zeile {$row}: {$e->getMessage()}");
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        Log::channel('import')->info('Flat-Import abgeschlossen', $this->stats);

        return new ImportExecutionResult(
            stats: ['flat_import' => $this->stats],
            affectedProductIds: array_unique($this->affectedProductIds),
            skippedDetails: $this->skippedDetails,
        );
    }

    private function deleteProductsBySkus(array $skus): void
    {
        if (empty($skus)) {
            return;
        }

        $productIds = Product::whereIn('sku', $skus)->pluck('id')->toArray();
        if (empty($productIds)) {
            return;
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
        Log::channel('import')->info('Flat-Import: Produkte gelöscht', ['count' => count($productIds)]);
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
