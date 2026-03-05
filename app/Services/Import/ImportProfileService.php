<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Attribute;
use App\Models\AttributeView;
use App\Models\AttributeViewAssignment;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\ImportProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Service für Import-Profile: Dateianalyse, Mapping-Anwendung, Vorschau, Auto-Generate.
 */
class ImportProfileService
{
    /**
     * Analysiert eine Excel-Datei und gibt Sheet-Namen + Spalten-Header + erkannte Datentypen zurück.
     *
     * @return array{sheets: array<string, array{name: string, headers: array, row_count: int}>}
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
            $maxCol = Coordinate::columnIndexFromString($highestColumn);

            // Header aus Zeile 1 lesen
            $headers = [];
            $colIndices = [];
            for ($col = 1; $col <= $maxCol; $col++) {
                $value = $worksheet->getCell([$col, 1])->getValue();
                if ($value !== null && $value !== '') {
                    $headerName = trim((string) $value);
                    $headers[] = $headerName;
                    $colIndices[$headerName] = $col;
                }
            }

            // Datentyp pro Spalte erkennen (max. 100 Zeilen scannen)
            $detectedTypes = $this->detectColumnTypes($worksheet, $colIndices, $highestRow);

            $sheets[$sheetName] = [
                'name' => $sheetName,
                'headers' => $headers,
                'detected_types' => $detectedTypes,
                'row_count' => max(0, $highestRow - 1),
            ];
        }

        // Verfügbare PIM-Attribute für Auto-Matching
        $attributes = Attribute::query()
            ->select(['id', 'technical_name', 'name_de', 'name_en', 'data_type'])
            ->orderBy('name_de')
            ->get()
            ->toArray();

        // Verfügbare AttributeViews (als Gruppen)
        $attributeViews = AttributeView::query()
            ->select(['id', 'technical_name', 'name_de', 'name_en'])
            ->orderBy('name_de')
            ->get()
            ->toArray();

        return [
            'sheets' => $sheets,
            'available_attributes' => $attributes,
            'available_attribute_views' => $attributeViews,
        ];
    }

    /**
     * Erkennt den Datentyp einer Spalte aus den Excel-Daten.
     *
     * @param  array<string, int>  $colIndices  header_name → column_index
     * @return array<string, array{type: string, confidence: int, samples: int}>
     */
    private function detectColumnTypes(Worksheet $worksheet, array $colIndices, int $highestRow): array
    {
        $scanRows = min($highestRow, 101); // Max 100 Datenzeilen scannen
        $result = [];

        foreach ($colIndices as $headerName => $colIdx) {
            $stats = ['total' => 0, 'empty' => 0, 'integer' => 0, 'float' => 0, 'date' => 0, 'boolean' => 0, 'string' => 0];

            for ($row = 2; $row <= $scanRows; $row++) {
                $cell = $worksheet->getCell([$colIdx, $row]);
                $rawValue = $cell->getValue();

                if ($rawValue === null || $rawValue === '') {
                    $stats['empty']++;
                    continue;
                }

                $stats['total']++;

                // Excel-Datumsformat erkennen (numerisch gespeichert mit Datumsformat)
                if (is_numeric($rawValue) && ExcelDate::isDateTime($cell)) {
                    $stats['date']++;
                    continue;
                }

                // Boolean-Werte
                if (is_bool($rawValue)) {
                    $stats['boolean']++;
                    continue;
                }

                $strValue = trim((string) $rawValue);
                $strLower = mb_strtolower($strValue);

                // Boolean-Strings
                if (in_array($strLower, ['ja', 'nein', 'yes', 'no', 'true', 'false', '0', '1'], true)) {
                    $stats['boolean']++;
                    continue;
                }

                // Numerisch
                if (is_numeric($rawValue)) {
                    // Ganzzahl vs. Dezimal
                    if (is_int($rawValue) || (is_float($rawValue) && floor($rawValue) == $rawValue && !str_contains((string) $rawValue, '.'))) {
                        $stats['integer']++;
                    } else {
                        $stats['float']++;
                    }
                    continue;
                }

                // Datum-Strings (gängige Formate)
                if (preg_match('/^\d{1,4}[-.\\/]\d{1,2}[-.\\/]\d{1,4}$/', $strValue)) {
                    $stats['date']++;
                    continue;
                }

                $stats['string']++;
            }

            $result[$headerName] = $this->resolveDataType($stats);
        }

        return $result;
    }

    /**
     * Bestimmt den PIM-Datentyp aus den Statistiken.
     *
     * @return array{type: string, confidence: int, samples: int}
     */
    private function resolveDataType(array $stats): array
    {
        $total = $stats['total'];
        if ($total === 0) {
            return ['type' => 'String', 'confidence' => 0, 'samples' => 0];
        }

        // Prüfe dominanten Typ (>= 80% der nicht-leeren Werte)
        $threshold = 0.8;
        $types = [
            'Date' => $stats['date'],
            'Flag' => $stats['boolean'],
            'Float' => $stats['float'],
            'Number' => $stats['integer'],
        ];

        // Integer + Float zusammen = numerisch
        $numericCount = $stats['integer'] + $stats['float'];

        foreach ($types as $type => $count) {
            if ($count / $total >= $threshold) {
                return ['type' => $type, 'confidence' => (int) round(($count / $total) * 100), 'samples' => $total];
            }
        }

        // Gemischt Integer + Float → Float
        if ($numericCount / $total >= $threshold) {
            return ['type' => 'Float', 'confidence' => (int) round(($numericCount / $total) * 100), 'samples' => $total];
        }

        // Fallback: String
        return ['type' => 'String', 'confidence' => (int) round(($stats['string'] / $total) * 100), 'samples' => $total];
    }

    /**
     * Auto-Generate: Erstellt fehlende Attribute, ordnet sie der AttributeView und der Kategorie zu.
     *
     * @param  array  $columns  [{header: string, auto_generate: bool, detected_type: string, override_type?: string}]
     * @return array{created: array, existing: array, assigned_to_category: int, assigned_to_view: int}
     */
    public function autoGenerateAttributes(
        array $columns,
        string $hierarchyNodeId,
        string $attributeViewId,
        ?string $attributeTypeId = null,
    ): array {
        $hierarchyNode = HierarchyNode::findOrFail($hierarchyNodeId);
        $attributeView = AttributeView::findOrFail($attributeViewId);

        $created = [];
        $existing = [];
        $assignedToCategory = 0;
        $assignedToView = 0;

        // Bestehende Attribute laden für schnellen Lookup
        $existingAttributes = Attribute::all()->keyBy(fn ($a) => mb_strtolower($a->technical_name));

        // Bestehende Kategorie-Zuordnungen laden
        $existingNodeAssignments = HierarchyNodeAttributeAssignment::where('hierarchy_node_id', $hierarchyNodeId)
            ->pluck('attribute_id')
            ->toArray();

        // Bestehende View-Zuordnungen laden
        $existingViewAssignments = AttributeViewAssignment::where('attribute_view_id', $attributeViewId)
            ->pluck('attribute_id')
            ->toArray();

        $maxSort = HierarchyNodeAttributeAssignment::where('hierarchy_node_id', $hierarchyNodeId)
            ->max('attribute_sort') ?? 0;

        foreach ($columns as $column) {
            if (empty($column['auto_generate'])) {
                continue;
            }

            $headerName = trim($column['header']);
            $technicalName = $this->toTechnicalName($headerName);
            $dataType = $column['override_type'] ?? $column['detected_type'] ?? 'String';

            // Attribut suchen oder erstellen
            $attribute = $existingAttributes[mb_strtolower($technicalName)] ?? null;

            if ($attribute) {
                $existing[] = [
                    'id' => $attribute->id,
                    'technical_name' => $attribute->technical_name,
                    'name_de' => $attribute->name_de,
                    'data_type' => $attribute->data_type,
                ];
            } else {
                $attribute = Attribute::create([
                    'technical_name' => $technicalName,
                    'name_de' => $headerName,
                    'name_en' => $headerName,
                    'data_type' => $dataType,
                    'attribute_type_id' => $attributeTypeId,
                    'is_translatable' => false,
                    'is_searchable' => true,
                    'is_mandatory' => false,
                    'is_unique' => false,
                    'is_inheritable' => true,
                    'status' => 'active',
                ]);

                $created[] = [
                    'id' => $attribute->id,
                    'technical_name' => $attribute->technical_name,
                    'name_de' => $attribute->name_de,
                    'data_type' => $attribute->data_type,
                ];
            }

            // Attribut der Kategorie (HierarchyNode) zuordnen
            if (!in_array($attribute->id, $existingNodeAssignments)) {
                $maxSort++;
                HierarchyNodeAttributeAssignment::create([
                    'hierarchy_node_id' => $hierarchyNodeId,
                    'attribute_id' => $attribute->id,
                    'collection_name' => $attributeView->name_de,
                    'attribute_sort' => $maxSort,
                    'access_hierarchy' => 'visible',
                    'access_product' => 'editable',
                    'access_variant' => 'editable',
                    'dont_inherit' => false,
                ]);
                $existingNodeAssignments[] = $attribute->id;
                $assignedToCategory++;
            }

            // Attribut der AttributeView zuordnen
            if (!in_array($attribute->id, $existingViewAssignments)) {
                AttributeViewAssignment::create([
                    'attribute_id' => $attribute->id,
                    'attribute_view_id' => $attributeViewId,
                ]);
                $existingViewAssignments[] = $attribute->id;
                $assignedToView++;
            }
        }

        return [
            'created' => $created,
            'existing' => $existing,
            'assigned_to_category' => $assignedToCategory,
            'assigned_to_view' => $assignedToView,
        ];
    }

    /**
     * Konvertiert einen Spaltennamen in einen technical_name.
     * z.B. "Farbe (RAL)" → "farbe_ral"
     */
    private function toTechnicalName(string $header): string
    {
        $name = mb_strtolower($header);
        // Umlaute ersetzen
        $name = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $name);
        // Alles was kein Buchstabe/Zahl ist → Unterstrich
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);
        // Mehrere Unterstriche zusammenfassen, Anfang/Ende trimmen
        $name = trim(preg_replace('/_+/', '_', $name), '_');

        return $name ?: 'attr_' . Str::random(6);
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
        $maxCol = Coordinate::columnIndexFromString($worksheet->getHighestColumn());

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
