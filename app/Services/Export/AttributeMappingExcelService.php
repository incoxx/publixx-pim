<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Attribute;
use App\Models\AttributeMapping;
use App\Models\AttributeMappingRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AttributeMappingExcelService
{
    /**
     * Exportiert Attribut-Mappings und bedingte Regeln als Excel (.xlsx).
     */
    public function export(string $sourceHierarchyId, string $targetHierarchyId): string
    {
        $spreadsheet = new Spreadsheet();

        $this->writeMappingsSheet($spreadsheet, $sourceHierarchyId, $targetHierarchyId);
        $this->writeRulesSheet($spreadsheet, $sourceHierarchyId, $targetHierarchyId);

        // Default-Sheet entfernen
        if ($spreadsheet->getSheetCount() > 1) {
            $spreadsheet->removeSheetByIndex(0);
        }

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'mapping_export_');
        $writer->save($tempFile);

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $content;
    }

    /**
     * Importiert Attribut-Mappings und bedingte Regeln aus einer Excel-Datei.
     */
    public function import(
        UploadedFile $file,
        string $sourceHierarchyId,
        string $targetHierarchyId
    ): array {
        $spreadsheet = IOFactory::load($file->getPathname());
        $stats = ['mappings_created' => 0, 'mappings_updated' => 0, 'rules_created' => 0, 'rules_updated' => 0, 'errors' => []];

        // Attribut-Lookup aufbauen (technical_name → id)
        $attributeIndex = Attribute::pluck('id', 'technical_name')->all();

        // Sheet 1: Attribut-Zuordnungen
        $mappingSheet = $this->findSheet($spreadsheet, 'Attribut-Zuordnungen');
        if ($mappingSheet) {
            $this->importMappings($mappingSheet, $sourceHierarchyId, $targetHierarchyId, $attributeIndex, $stats);
        }

        // Sheet 2: Bedingte Regeln
        $rulesSheet = $this->findSheet($spreadsheet, 'Bedingte Regeln');
        if ($rulesSheet) {
            $this->importRules($rulesSheet, $sourceHierarchyId, $targetHierarchyId, $attributeIndex, $stats);
        }

        return $stats;
    }

    // ─── Export: Sheets ────────────────────────────────────────

    private function writeMappingsSheet(Spreadsheet $spreadsheet, string $sourceHierarchyId, string $targetHierarchyId): void
    {
        $mappings = AttributeMapping::with(['sourceAttribute', 'targetAttribute'])
            ->where('source_hierarchy_id', $sourceHierarchyId)
            ->where('target_hierarchy_id', $targetHierarchyId)
            ->orderBy('created_at')
            ->get();

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Attribut-Zuordnungen');

        $headers = [
            'Quell-Attribut (Key)',
            'Quell-Attribut (Name)',
            'Ziel-Attribut (Key)',
            'Ziel-Attribut (Name)',
            'Transform',
            'Transform-Config (JSON)',
        ];
        $this->writeHeaders($sheet, $headers);

        $row = 2;
        foreach ($mappings as $m) {
            $sheet->setCellValue([1, $row], $m->sourceAttribute?->technical_name ?? '');
            $sheet->setCellValue([2, $row], $m->sourceAttribute?->name_de ?? '');
            $sheet->setCellValue([3, $row], $m->targetAttribute?->technical_name ?? '');
            $sheet->setCellValue([4, $row], $m->targetAttribute?->name_de ?? '');
            $sheet->setCellValue([5, $row], $m->transform_type ?? 'direct');
            $sheet->setCellValue([6, $row], $m->transform_config ? json_encode($m->transform_config, JSON_UNESCAPED_UNICODE) : '');
            $row++;
        }

        // Spaltenbreiten
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(50);
    }

    private function writeRulesSheet(Spreadsheet $spreadsheet, string $sourceHierarchyId, string $targetHierarchyId): void
    {
        $rules = AttributeMappingRule::with('conditionAttribute')
            ->where('source_hierarchy_id', $sourceHierarchyId)
            ->where('target_hierarchy_id', $targetHierarchyId)
            ->orderBy('sort_order')
            ->get();

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Bedingte Regeln');

        $headers = [
            'Name',
            'Bedingung-Attribut (Key)',
            'Bedingung-Attribut (Name)',
            'Operator',
            'Wert',
            'Aktionen (JSON)',
            'Aktiv',
        ];
        $this->writeHeaders($sheet, $headers);

        // Attribut-Lookup für Aktionen
        $attributeNames = Attribute::pluck('technical_name', 'id')->all();
        $attributeLabels = Attribute::pluck('name_de', 'id')->all();

        $row = 2;
        foreach ($rules as $rule) {
            // Aktionen mit technischen Namen anreichern
            $actions = collect($rule->actions)->map(function ($action) use ($attributeNames, $attributeLabels) {
                $targetId = $action['target_attribute_id'] ?? null;
                return [
                    'target_key' => $attributeNames[$targetId] ?? '',
                    'target_name' => $attributeLabels[$targetId] ?? '',
                    'value' => $action['value'] ?? '',
                    'value_type' => $action['value_type'] ?? 'static',
                ];
            })->all();

            $sheet->setCellValue([1, $row], $rule->name);
            $sheet->setCellValue([2, $row], $rule->conditionAttribute?->technical_name ?? '');
            $sheet->setCellValue([3, $row], $rule->conditionAttribute?->name_de ?? '');
            $sheet->setCellValue([4, $row], $rule->condition_operator);
            $sheet->setCellValue([5, $row], is_array($rule->condition_value) ? json_encode($rule->condition_value, JSON_UNESCAPED_UNICODE) : (string) ($rule->condition_value ?? ''));
            $sheet->setCellValue([6, $row], json_encode($actions, JSON_UNESCAPED_UNICODE));
            $sheet->setCellValue([7, $row], $rule->is_active ? 'ja' : 'nein');
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(60);
        $sheet->getColumnDimension('G')->setWidth(8);
    }

    // ─── Import: Sheets ────────────────────────────────────────

    private function importMappings(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $sourceHierarchyId,
        string $targetHierarchyId,
        array $attributeIndex,
        array &$stats
    ): void {
        $rows = $sheet->toArray(null, true, true, true);
        $headerRow = $rows[1] ?? [];
        unset($rows[1]); // Header entfernen

        DB::transaction(function () use ($rows, $sourceHierarchyId, $targetHierarchyId, $attributeIndex, &$stats) {
            foreach ($rows as $rowNum => $row) {
                $sourceKey = trim((string) ($row['A'] ?? ''));
                $targetKey = trim((string) ($row['C'] ?? ''));

                if ($sourceKey === '' || $targetKey === '') {
                    continue;
                }

                $sourceAttrId = $attributeIndex[$sourceKey] ?? null;
                $targetAttrId = $attributeIndex[$targetKey] ?? null;

                if (!$sourceAttrId) {
                    $stats['errors'][] = "Zeile {$rowNum}: Quell-Attribut '{$sourceKey}' nicht gefunden";
                    continue;
                }
                if (!$targetAttrId) {
                    $stats['errors'][] = "Zeile {$rowNum}: Ziel-Attribut '{$targetKey}' nicht gefunden";
                    continue;
                }

                $transformType = trim((string) ($row['E'] ?? 'direct'));
                if (!in_array($transformType, ['direct', 'unit_convert', 'value_map'])) {
                    $transformType = 'direct';
                }

                $transformConfig = null;
                $configStr = trim((string) ($row['F'] ?? ''));
                if ($configStr !== '') {
                    $decoded = json_decode($configStr, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $transformConfig = $decoded;
                    } else {
                        $stats['errors'][] = "Zeile {$rowNum}: Ungültiges JSON in Transform-Config";
                    }
                }

                $mapping = AttributeMapping::updateOrCreate(
                    [
                        'source_hierarchy_id' => $sourceHierarchyId,
                        'source_attribute_id' => $sourceAttrId,
                        'target_hierarchy_id' => $targetHierarchyId,
                        'target_attribute_id' => $targetAttrId,
                    ],
                    [
                        'transform_type' => $transformType,
                        'transform_config' => $transformConfig,
                    ]
                );

                if ($mapping->wasRecentlyCreated) {
                    $stats['mappings_created']++;
                } else {
                    $stats['mappings_updated']++;
                }
            }
        });
    }

    private function importRules(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $sourceHierarchyId,
        string $targetHierarchyId,
        array $attributeIndex,
        array &$stats
    ): void {
        $rows = $sheet->toArray(null, true, true, true);
        unset($rows[1]); // Header entfernen

        DB::transaction(function () use ($rows, $sourceHierarchyId, $targetHierarchyId, $attributeIndex, &$stats) {
            foreach ($rows as $rowNum => $row) {
                $name = trim((string) ($row['A'] ?? ''));
                $conditionKey = trim((string) ($row['B'] ?? ''));

                if ($name === '' || $conditionKey === '') {
                    continue;
                }

                $conditionAttrId = $attributeIndex[$conditionKey] ?? null;
                if (!$conditionAttrId) {
                    $stats['errors'][] = "Regeln Zeile {$rowNum}: Bedingung-Attribut '{$conditionKey}' nicht gefunden";
                    continue;
                }

                $operator = trim((string) ($row['D'] ?? '='));
                $conditionValue = trim((string) ($row['E'] ?? ''));

                // Versuche JSON zu parsen (für 'in' Operator etc.)
                $decoded = json_decode($conditionValue, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $conditionValue = $decoded;
                }

                // Aktionen parsen
                $actionsStr = trim((string) ($row['F'] ?? '[]'));
                $actionsRaw = json_decode($actionsStr, true);
                if (!is_array($actionsRaw)) {
                    $stats['errors'][] = "Regeln Zeile {$rowNum}: Ungültiges JSON in Aktionen";
                    continue;
                }

                // target_key → target_attribute_id auflösen
                $actions = [];
                $hasError = false;
                foreach ($actionsRaw as $action) {
                    $targetKey = $action['target_key'] ?? '';
                    $targetAttrId = $attributeIndex[$targetKey] ?? null;
                    if (!$targetAttrId) {
                        $stats['errors'][] = "Regeln Zeile {$rowNum}: Ziel-Attribut '{$targetKey}' in Aktion nicht gefunden";
                        $hasError = true;
                        break;
                    }
                    $actions[] = [
                        'target_attribute_id' => $targetAttrId,
                        'value' => $action['value'] ?? '',
                        'value_type' => $action['value_type'] ?? 'static',
                    ];
                }
                if ($hasError) {
                    continue;
                }

                $isActive = in_array(strtolower(trim((string) ($row['G'] ?? 'ja'))), ['ja', 'yes', '1', 'true']);

                $rule = AttributeMappingRule::updateOrCreate(
                    [
                        'source_hierarchy_id' => $sourceHierarchyId,
                        'target_hierarchy_id' => $targetHierarchyId,
                        'name' => $name,
                    ],
                    [
                        'condition_attribute_id' => $conditionAttrId,
                        'condition_operator' => $operator,
                        'condition_value' => $conditionValue,
                        'actions' => $actions,
                        'is_active' => $isActive,
                    ]
                );

                if ($rule->wasRecentlyCreated) {
                    $stats['rules_created']++;
                } else {
                    $stats['rules_updated']++;
                }
            }
        });
    }

    // ─── Helpers ───────────────────────────────────────────────

    private function writeHeaders(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $headers): void
    {
        foreach ($headers as $col => $header) {
            $cell = $sheet->getCell([$col + 1, 1]);
            $cell->setValue($header);
        }

        // Header-Styling
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E5E7EB'],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->setAutoFilter($headerRange);
    }

    private function findSheet(Spreadsheet $spreadsheet, string $name): ?\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        // Exakter Name
        $sheet = $spreadsheet->getSheetByName($name);
        if ($sheet) {
            return $sheet;
        }

        // Prefix-Match (z.B. "01_Attribut-Zuordnungen")
        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            if (str_contains(strtolower($sheetName), strtolower($name))) {
                return $spreadsheet->getSheetByName($sheetName);
            }
        }

        return null;
    }
}
