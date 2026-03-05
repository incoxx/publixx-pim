<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Attribute;
use App\Models\ImportProfile;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Service für Import-Profile: Dateianalyse, Mapping-Anwendung, Vorschau.
 */
class ImportProfileService
{
    /**
     * Analysiert eine Excel-Datei und gibt Sheet-Namen + Spalten-Header zurück.
     *
     * @return array{sheets: array<string, array{name: string, headers: string[], row_count: int}>}
     */
    public function analyzeFile(UploadedFile $file): array
    {
        $tmpPath = $file->getRealPath();
        $spreadsheet = IOFactory::load($tmpPath);

        $sheets = [];
        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $worksheet = $spreadsheet->getSheetByName($sheetName);
            if (!$worksheet) {
                continue;
            }

            $highestColumn = $worksheet->getHighestColumn();
            $highestRow = $worksheet->getHighestRow();

            // Header aus Zeile 1 lesen
            $headers = [];
            $colIndex = 1;
            $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            for ($col = 1; $col <= $maxCol; $col++) {
                $value = $worksheet->getCell([$col, 1])->getValue();
                if ($value !== null && $value !== '') {
                    $headers[] = trim((string) $value);
                }
            }

            $sheets[$sheetName] = [
                'name' => $sheetName,
                'headers' => $headers,
                'row_count' => max(0, $highestRow - 1), // minus header
            ];
        }

        // Verfügbare PIM-Attribute für Auto-Matching
        $attributes = Attribute::query()
            ->select(['id', 'technical_name', 'name_de', 'name_en', 'data_type'])
            ->orderBy('name_de')
            ->get()
            ->toArray();

        return [
            'sheets' => $sheets,
            'available_attributes' => $attributes,
        ];
    }

    /**
     * Wendet ein Import-Mapping auf eine Datei an und gibt eine Vorschau zurück.
     */
    public function preview(ImportProfile $profile, UploadedFile $file, int $maxRows = 20): array
    {
        $tmpPath = $file->getRealPath();
        $spreadsheet = IOFactory::load($tmpPath);

        $worksheet = $spreadsheet->getSheet(0);
        $highestRow = min($worksheet->getHighestRow(), $maxRows + 1);
        $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($worksheet->getHighestColumn());

        // Header lesen
        $headers = [];
        for ($col = 1; $col <= $maxCol; $col++) {
            $value = $worksheet->getCell([$col, 1])->getValue();
            $headers[$col] = trim((string) ($value ?? ''));
        }

        // Mapping-Lookup: source column name → attribute info
        $mappings = collect($profile->column_mappings ?? []);
        $attributeIds = $mappings->pluck('target_attribute_id')->unique()->toArray();
        $attributes = Attribute::whereIn('id', $attributeIds)->get()->keyBy('id');

        $skuColumnIndex = null;
        foreach ($headers as $colIdx => $header) {
            if (strcasecmp($header, $profile->sku_column ?? 'SKU') === 0) {
                $skuColumnIndex = $colIdx;
                break;
            }
        }

        $previewRows = [];
        $errors = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            $sku = $skuColumnIndex ? (string) $worksheet->getCell([$skuColumnIndex, $row])->getValue() : null;
            $rowData['_sku'] = $sku;
            $rowData['_row'] = $row;

            foreach ($mappings as $mapping) {
                $sourceCol = $mapping['source'] ?? '';
                $attrId = $mapping['target_attribute_id'] ?? '';
                $attr = $attributes[$attrId] ?? null;

                // Finde die Spalte
                $colIdx = array_search($sourceCol, $headers);
                if ($colIdx === false) {
                    $errors[] = [
                        'row' => $row,
                        'column' => $sourceCol,
                        'error' => "Quellspalte '{$sourceCol}' nicht in der Datei gefunden",
                    ];
                    continue;
                }

                $value = $worksheet->getCell([$colIdx, $row])->getValue();
                $rowData[$sourceCol] = [
                    'source_value' => $value,
                    'target_attribute' => $attr?->technical_name ?? $attrId,
                    'target_name' => $attr?->name_de ?? '',
                    'language' => $mapping['language'] ?? 'de',
                ];
            }

            $previewRows[] = $rowData;
        }

        return [
            'headers' => array_values($headers),
            'sku_column' => $profile->sku_column,
            'product_type' => $profile->productType?->name_de,
            'rows' => $previewRows,
            'mapped_columns' => $mappings->count(),
            'total_rows' => $worksheet->getHighestRow() - 1,
            'preview_rows' => count($previewRows),
            'errors' => $errors,
        ];
    }

    /**
     * Versucht automatisches Matching von Spalten zu PIM-Attributen.
     *
     * @return array<string, string|null>  column_name → attribute_id (oder null)
     */
    public function autoMatch(array $columnHeaders): array
    {
        $attributes = Attribute::all();
        $matches = [];

        foreach ($columnHeaders as $header) {
            $headerLower = mb_strtolower(trim($header));
            $bestMatch = null;
            $bestScore = 0;

            foreach ($attributes as $attr) {
                // Exakter Match auf technical_name
                if (mb_strtolower($attr->technical_name) === $headerLower) {
                    $bestMatch = $attr->id;
                    $bestScore = 100;
                    break;
                }

                // Exakter Match auf name_de
                if (mb_strtolower($attr->name_de) === $headerLower) {
                    $bestMatch = $attr->id;
                    $bestScore = 90;
                    continue;
                }

                // Teilmatch
                if ($bestScore < 70) {
                    $nameLower = mb_strtolower($attr->name_de);
                    if (str_contains($nameLower, $headerLower) || str_contains($headerLower, $nameLower)) {
                        $bestMatch = $attr->id;
                        $bestScore = 70;
                    }
                }
            }

            $matches[$header] = $bestMatch;
        }

        return $matches;
    }
}
