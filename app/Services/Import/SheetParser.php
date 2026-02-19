<?php

declare(strict_types=1);

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Parst eine Excel-Datei mit bis zu 14 Reitern in strukturierte Daten.
 * Jeder Reiter wird anhand seines Namens erkannt (Prefix-Match auf Nr).
 */
class SheetParser
{
    /**
     * Mapping: Sheet-Nummer → erwarteter Name-Prefix + Spalten-Definition.
     * Die Spalten-Keys sind die internen Feldnamen.
     */
    public const array SHEET_DEFINITIONS = [
        '01_Produkttypen' => [
            'columns' => [
                'A' => 'technical_name',
                'B' => 'name_de',
                'C' => 'name_en',
                'D' => 'description',
                'E' => 'has_variants',
                'F' => 'has_ean',
                'G' => 'has_prices',
                'H' => 'has_media',
            ],
            'required' => ['technical_name', 'name_de'],
            'identifier' => ['technical_name'],
        ],
        '02_Attributgruppen' => [
            'columns' => [
                'A' => 'technical_name',
                'B' => 'name_de',
                'C' => 'name_en',
                'D' => 'description',
                'E' => 'sort_order',
            ],
            'required' => ['technical_name', 'name_de'],
            'identifier' => ['technical_name'],
        ],
        '03_Einheiten' => [
            'columns' => [
                'A' => 'group_technical_name',
                'B' => 'group_name_de',
                'C' => 'technical_name',
                'D' => 'abbreviation',
                'E' => 'conversion_factor',
                'F' => 'is_base_unit',
            ],
            'required' => ['group_technical_name', 'group_name_de', 'technical_name', 'abbreviation'],
            'identifier' => ['technical_name'],
        ],
        '04_Wertelisten' => [
            'columns' => [
                'A' => 'list_technical_name',
                'B' => 'list_name_de',
                'C' => 'entry_technical_name',
                'D' => 'display_value_de',
                'E' => 'display_value_en',
                'F' => 'sort_order',
            ],
            'required' => ['list_technical_name', 'list_name_de'],
            'identifier' => ['list_technical_name'],
        ],
        '05_Attribute' => [
            'columns' => [
                'A' => 'technical_name',
                'B' => 'name_de',
                'C' => 'name_en',
                'D' => 'description',
                'E' => 'data_type',
                'F' => 'attribute_group',
                'G' => 'value_list',
                'H' => 'unit_group',
                'I' => 'default_unit',
                'J' => 'is_multipliable',
                'K' => 'max_multiplied',
                'L' => 'is_translatable',
                'M' => 'is_mandatory',
                'N' => 'is_unique',
                'O' => 'is_searchable',
                'P' => 'is_inheritable',
                'Q' => 'parent_attribute',
                'R' => 'source_system',
                'S' => 'views',
            ],
            'required' => ['technical_name', 'name_de', 'data_type'],
            'identifier' => ['technical_name'],
        ],
        '06_Hierarchien' => [
            'columns' => [
                'A' => 'hierarchy',
                'B' => 'type',
                'C' => 'level_1',
                'D' => 'level_2',
                'E' => 'level_3',
                'F' => 'level_4',
                'G' => 'level_5',
                'H' => 'level_6',
            ],
            'required' => ['hierarchy', 'type'],
            'identifier' => ['hierarchy', 'level_1', 'level_2', 'level_3', 'level_4', 'level_5', 'level_6'],
        ],
        '07_Hierarchie_Attribute' => [
            'columns' => [
                'A' => 'hierarchy',
                'B' => 'node_path',
                'C' => 'attribute',
                'D' => 'collection_name',
                'E' => 'collection_sort',
                'F' => 'attribute_sort',
                'G' => 'dont_inherit',
            ],
            'required' => ['hierarchy', 'node_path', 'attribute'],
            'identifier' => ['node_path', 'attribute'],
        ],
        '08_Produkte' => [
            'columns' => [
                'A' => 'sku',
                'B' => 'name',
                'C' => 'name_en',
                'D' => 'product_type',
                'E' => 'ean',
                'F' => 'status',
            ],
            'required' => ['sku', 'name', 'product_type'],
            'identifier' => ['sku'],
        ],
        '09_Produktwerte' => [
            'columns' => [
                'A' => 'sku',
                'B' => 'attribute',
                'C' => 'value',
                'D' => 'unit',
                'E' => 'language',
                'F' => 'index',
            ],
            'required' => ['sku', 'attribute', 'value'],
            'identifier' => ['sku', 'attribute', 'language', 'index'],
        ],
        '10_Varianten' => [
            'columns' => [
                'A' => 'parent_sku',
                'B' => 'variant_sku',
                'C' => 'variant_name',
                'D' => 'variant_name_en',
                'E' => 'ean',
                'F' => 'status',
            ],
            'required' => ['parent_sku', 'variant_sku', 'variant_name'],
            'identifier' => ['variant_sku'],
        ],
        '11_Produkt_Hierarchien' => [
            'columns' => [
                'A' => 'sku',
                'B' => 'hierarchy',
                'C' => 'node_path',
            ],
            'required' => ['sku', 'hierarchy', 'node_path'],
            'identifier' => ['sku', 'node_path'],
        ],
        '12_Produktbeziehungen' => [
            'columns' => [
                'A' => 'source_sku',
                'B' => 'target_sku',
                'C' => 'relation_type',
                'D' => 'sort_order',
            ],
            'required' => ['source_sku', 'target_sku', 'relation_type'],
            'identifier' => ['source_sku', 'target_sku', 'relation_type'],
        ],
        '13_Preise' => [
            'columns' => [
                'A' => 'sku',
                'B' => 'price_type',
                'C' => 'amount',
                'D' => 'currency',
                'E' => 'valid_from',
                'F' => 'valid_to',
                'G' => 'country',
                'H' => 'scale_from',
                'I' => 'scale_to',
            ],
            'required' => ['sku', 'price_type', 'amount', 'currency'],
            'identifier' => ['sku', 'price_type', 'currency', 'valid_from'],
        ],
        '14_Medien' => [
            'columns' => [
                'A' => 'sku',
                'B' => 'file_name',
                'C' => 'media_type',
                'D' => 'usage_type',
                'E' => 'title_de',
                'F' => 'title_en',
                'G' => 'alt_text_de',
                'H' => 'sort_order',
                'I' => 'is_primary',
            ],
            'required' => ['sku', 'file_name'],
            'identifier' => ['sku', 'file_name'],
        ],
    ];

    /**
     * Parst eine Excel-Datei und gibt strukturierte Daten pro Sheet zurück.
     *
     * @param string $filePath Pfad zur .xlsx-Datei
     * @return ParseResult
     */
    public function parse(string $filePath): ParseResult
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheetsFound = [];
        $data = [];

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $definition = $this->matchSheetDefinition($sheetName);
            if ($definition === null) {
                continue; // Unbekannter Reiter → ignorieren
            }

            $worksheet = $spreadsheet->getSheetByName($sheetName);
            if ($worksheet === null) {
                continue;
            }

            $sheetKey = $definition['key'];
            $sheetsFound[] = $sheetKey;
            $data[$sheetKey] = $this->parseSheet($worksheet, $definition['definition']);
        }

        return new ParseResult($sheetsFound, $data);
    }

    /**
     * Gibt die Sheet-Definitionen zurück (für TemplateGenerator etc.).
     *
     * @return array<string, array>
     */
    public static function getSheetDefinitions(): array
    {
        return self::SHEET_DEFINITIONS;
    }

    /**
     * Matcht einen Worksheet-Namen auf eine Sheet-Definition.
     * Unterstützt Prefix-Match: "05_Attribute", "05 Attribute", "Attribute" etc.
     */
    private function matchSheetDefinition(string $sheetName): ?array
    {
        $normalized = trim($sheetName);

        // Exakter Match
        if (isset(self::SHEET_DEFINITIONS[$normalized])) {
            return ['key' => $normalized, 'definition' => self::SHEET_DEFINITIONS[$normalized]];
        }

        // Prefix-Match: "05" am Anfang → 05_Attribute
        foreach (self::SHEET_DEFINITIONS as $key => $definition) {
            $prefix = substr($key, 0, 2); // z.B. "05"
            $suffix = substr($key, 3);     // z.B. "Attribute"

            if (
                str_starts_with($normalized, $prefix)
                || mb_strtolower($normalized) === mb_strtolower($suffix)
                || mb_strtolower($normalized) === mb_strtolower($key)
            ) {
                return ['key' => $key, 'definition' => $definition];
            }
        }

        return null;
    }

    /**
     * Parst ein einzelnes Worksheet in ein Array von Zeilen.
     * Zeile 1 = Header (wird validiert), ab Zeile 2 = Daten.
     *
     * @return array<int, array<string, mixed>> Key = Original-Zeilennummer (2-basiert)
     */
    private function parseSheet(Worksheet $worksheet, array $definition): array
    {
        $columns = $definition['columns'];
        $rows = [];

        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();

        // Ab Zeile 2 (Zeile 1 = Header)
        for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
            $rowData = [];
            $isEmpty = true;

            foreach ($columns as $col => $fieldName) {
                $cellValue = $worksheet->getCell($col . $rowNum)->getValue();

                // Bereinigung
                if (is_string($cellValue)) {
                    $cellValue = trim($cellValue);
                    if ($cellValue === '') {
                        $cellValue = null;
                    }
                }

                $rowData[$fieldName] = $cellValue;

                if ($cellValue !== null && $cellValue !== '') {
                    $isEmpty = false;
                }
            }

            // Komplett leere Zeilen überspringen
            if ($isEmpty) {
                continue;
            }

            // Original-Zeilennummer beibehalten (für Fehlerberichte)
            $rowData['_row'] = $rowNum;
            $rows[$rowNum] = $rowData;
        }

        return $rows;
    }
}
