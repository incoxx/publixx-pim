<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Validiert geparste Sheet-Daten:
 * - Pflichtfeld-Prüfung
 * - Datentyp-Prüfung
 * - Enum-Prüfung
 * - Referenz-Auflösung (mit Fuzzy-Suggestion)
 * - Duplikat-Erkennung (Create vs. Update)
 */
class SheetValidator
{
    /** Erlaubte Datentypen für Attribute. */
    private const array VALID_DATA_TYPES = [
        'String', 'Number', 'Float', 'Date', 'Flag', 'Selection', 'Dictionary', 'Collection', 'Composite',
    ];

    /** Erlaubte Produkt-Status. */
    private const array VALID_PRODUCT_STATUS = ['draft', 'active', 'inactive', 'discontinued'];

    /** Erlaubte Hierarchie-Typen. */
    private const array VALID_HIERARCHY_TYPES = ['master', 'output'];

    /** Erlaubte Ja/Nein-Werte. */
    private const array BOOLEAN_TRUE = ['ja', 'yes', '1', 'true', 'wahr', 'x'];
    private const array BOOLEAN_FALSE = ['nein', 'no', '0', 'false', 'falsch', ''];

    /** Spalten-Buchstaben für Fehlerberichte. */
    private const array COLUMN_LETTERS = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
        'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
    ];

    /**
     * Entitäten, die im gleichen Import angelegt werden (Cross-Sheet-Referenzen).
     * @var array<string, array<string>>  type → [lowercase_name, ...]
     */
    private array $pendingEntities = [];

    public function __construct(
        private readonly ReferenceResolver $resolver,
        private readonly FuzzyMatcher $fuzzyMatcher,
    ) {}

    /**
     * Validiert alle Sheets und gibt ein ValidationResult zurück.
     */
    public function validate(ParseResult $parseResult): ValidationResult
    {
        $this->pendingEntities = $this->buildPendingEntities($parseResult);

        $errors = [];
        $summary = [];

        foreach ($parseResult->sheetsFound as $sheetKey) {
            $rows = $parseResult->getSheetData($sheetKey);
            $definition = SheetParser::SHEET_DEFINITIONS[$sheetKey] ?? null;

            if ($definition === null || empty($rows)) {
                continue;
            }

            $sheetErrors = $this->validateSheet($sheetKey, $rows, $definition);
            $errors = array_merge($errors, $sheetErrors);

            $validCount = count($rows) - count(array_filter($sheetErrors, fn($e) => $e['sheet'] === $sheetKey));
            $errorRowNumbers = array_unique(array_column(
                array_filter($sheetErrors, fn($e) => $e['sheet'] === $sheetKey),
                'row'
            ));
            $validCount = count($rows) - count($errorRowNumbers);

            // Create vs Update Zählung
            $counts = $this->countCreateUpdate($sheetKey, $rows, $definition);

            $summary[$sheetKey] = [
                'total' => count($rows),
                'valid' => max(0, $validCount),
                'errors' => count($errorRowNumbers),
                'creates' => $counts['creates'],
                'updates' => $counts['updates'],
            ];
        }

        return new ValidationResult(
            sheetsFound: $parseResult->sheetsFound,
            summary: $summary,
            errors: $errors,
            hasErrors: !empty($errors),
        );
    }

    /**
     * Validiert ein einzelnes Sheet.
     *
     * @return array<array{sheet:string,row:int,column:string,field:string,value:mixed,error:string,suggestion:?string}>
     */
    private function validateSheet(string $sheetKey, array $rows, array $definition): array
    {
        $errors = [];
        $columns = $definition['columns'];
        $required = $definition['required'] ?? [];
        $columnIndex = array_flip(array_values($columns));

        foreach ($rows as $rowNum => $row) {
            // 1. Pflichtfeld-Prüfung
            foreach ($required as $field) {
                if (empty($row[$field]) && $row[$field] !== '0' && $row[$field] !== 0) {
                    $colLetter = $this->fieldToColumn($field, $columns);
                    $errors[] = [
                        'sheet' => $sheetKey,
                        'row' => $rowNum,
                        'column' => $colLetter,
                        'field' => $this->fieldToHeader($field, $sheetKey),
                        'value' => $row[$field] ?? null,
                        'error' => "Pflichtfeld ist leer.",
                        'suggestion' => null,
                    ];
                }
            }

            // 2. Sheet-spezifische Validierung
            $sheetSpecificErrors = match ($sheetKey) {
                '05_Attribute' => $this->validateAttributeRow($row, $rowNum, $columns),
                '08_Produkte' => $this->validateProductRow($row, $rowNum, $columns),
                '09_Produktwerte' => $this->validateProductValueRow($row, $rowNum, $columns),
                '06_Hierarchien' => $this->validateHierarchyRow($row, $rowNum, $columns),
                '13_Preise' => $this->validatePriceRow($row, $rowNum, $columns),
                '10_Varianten' => $this->validateVariantRow($row, $rowNum, $columns),
                '12_Produktbeziehungen' => $this->validateRelationRow($row, $rowNum, $columns),
                default => [],
            };

            foreach ($sheetSpecificErrors as $err) {
                $err['sheet'] = $sheetKey;
                $errors[] = $err;
            }
        }

        return $errors;
    }

    // ──────────────────────────────────────────────
    //  Sheet-spezifische Validierung
    // ──────────────────────────────────────────────

    private function validateAttributeRow(array $row, int $rowNum, array $columns): array
    {
        $errors = [];

        // Datentyp validieren
        if (!empty($row['data_type'])) {
            if (!in_array($row['data_type'], self::VALID_DATA_TYPES, true)) {
                $suggestion = $this->fuzzyMatcher->findMatch($row['data_type'], self::VALID_DATA_TYPES);
                $errors[] = [
                    'row' => $rowNum,
                    'column' => $this->fieldToColumn('data_type', $columns),
                    'field' => 'Datentyp',
                    'value' => $row['data_type'],
                    'error' => 'Ungültiger Datentyp. Erlaubt: ' . implode(', ', self::VALID_DATA_TYPES),
                    'suggestion' => $suggestion?->toSuggestion(),
                ];
            }
        }

        // Attributgruppe referenz-prüfen
        if (!empty($row['attribute_group'])) {
            $result = $this->resolver->resolveAttributeType($row['attribute_group']);
            if (!$result->resolved() && !$this->isPending('attribute_type', $row['attribute_group'])) {
                $errors[] = [
                    'row' => $rowNum,
                    'column' => $this->fieldToColumn('attribute_group', $columns),
                    'field' => 'Attributgruppe',
                    'value' => $row['attribute_group'],
                    'error' => 'Attributgruppe nicht gefunden.',
                    'suggestion' => $result->suggestion,
                ];
            }
        }

        // Werteliste referenz-prüfen
        if (!empty($row['value_list'])) {
            $result = $this->resolver->resolveValueList($row['value_list']);
            if (!$result->resolved() && !$this->isPending('value_list', $row['value_list'])) {
                $errors[] = [
                    'row' => $rowNum,
                    'column' => $this->fieldToColumn('value_list', $columns),
                    'field' => 'Werteliste',
                    'value' => $row['value_list'],
                    'error' => 'Werteliste nicht gefunden.',
                    'suggestion' => $result->suggestion,
                ];
            }
        }

        // Einheitengruppe referenz-prüfen
        if (!empty($row['unit_group'])) {
            $result = $this->resolver->resolveUnitGroup($row['unit_group']);
            if (!$result->resolved() && !$this->isPending('unit_group', $row['unit_group'])) {
                $errors[] = [
                    'row' => $rowNum,
                    'column' => $this->fieldToColumn('unit_group', $columns),
                    'field' => 'Einheitengruppe',
                    'value' => $row['unit_group'],
                    'error' => 'Einheitengruppe nicht gefunden.',
                    'suggestion' => $result->suggestion,
                ];
            }
        }

        // Übergeordnetes Attribut prüfen
        if (!empty($row['parent_attribute'])) {
            $result = $this->resolver->resolveAttribute($row['parent_attribute']);
            if (!$result->resolved() && !$this->isPending('attribute', $row['parent_attribute'])) {
                $errors[] = [
                    'row' => $rowNum,
                    'column' => $this->fieldToColumn('parent_attribute', $columns),
                    'field' => 'Übergeordnetes Attribut',
                    'value' => $row['parent_attribute'],
                    'error' => 'Übergeordnetes Attribut nicht gefunden.',
                    'suggestion' => $result->suggestion,
                ];
            }
        }

        // Boolean-Felder validieren
        foreach (['is_multipliable', 'is_translatable', 'is_unique', 'is_searchable', 'is_inheritable'] as $boolField) {
            if (!empty($row[$boolField]) && !$this->isValidBoolean($row[$boolField])) {
                $errors[] = [
                    'row' => $rowNum,
                    'column' => $this->fieldToColumn($boolField, $columns),
                    'field' => $boolField,
                    'value' => $row[$boolField],
                    'error' => 'Ungültiger Wert. Erlaubt: Ja/Nein.',
                    'suggestion' => null,
                ];
            }
        }

        return $errors;
    }

    private function validateProductRow(array $row, int $rowNum, array $columns): array
    {
        $errors = [];

        // Produkttyp referenz-prüfen
        if (!empty($row['product_type'])) {
            $result = $this->resolver->resolveProductType($row['product_type']);
            if (!$result->resolved() && !$this->isPending('product_type', $row['product_type'])) {
                $errors[] = [
                    'row' => $rowNum,
                    'column' => $this->fieldToColumn('product_type', $columns),
                    'field' => 'Produkttyp',
                    'value' => $row['product_type'],
                    'error' => 'Produkttyp nicht gefunden.',
                    'suggestion' => $result->suggestion,
                ];
            }
        }

        // Status validieren
        if (!empty($row['status']) && !in_array(mb_strtolower($row['status']), self::VALID_PRODUCT_STATUS, true)) {
            $errors[] = [
                'row' => $rowNum,
                'column' => $this->fieldToColumn('status', $columns),
                'field' => 'Status',
                'value' => $row['status'],
                'error' => 'Ungültiger Status. Erlaubt: ' . implode(', ', self::VALID_PRODUCT_STATUS),
                'suggestion' => null,
            ];
        }

        return $errors;
    }

    private function validateProductValueRow(array $row, int $rowNum, array $columns): array
    {
        $errors = [];

        // Attribut referenz-prüfen (technischer Name oder Anzeigename)
        if (!empty($row['attribute'])) {
            $result = $this->resolver->resolveAttribute($row['attribute']);
            if (!$result->resolved() && !$this->isPending('attribute', $row['attribute'])) {
                $errors[] = [
                    'row' => $rowNum,
                    'column' => $this->fieldToColumn('attribute', $columns),
                    'field' => 'Attribut',
                    'value' => $row['attribute'],
                    'error' => 'Attribut nicht gefunden.',
                    'suggestion' => $result->suggestion,
                ];
            }
        }

        // SKU referenz-prüfen (Produkt muss existieren oder im gleichen Import sein)
        // Hinweis: Cross-Sheet-Referenzen werden im ImportService geprüft

        // Einheit prüfen
        if (!empty($row['unit'])) {
            $result = $this->resolver->resolveUnit($row['unit']);
            if (!$result->resolved() && !$this->isPending('unit', $row['unit'])) {
                $errors[] = [
                    'row' => $rowNum,
                    'column' => $this->fieldToColumn('unit', $columns),
                    'field' => 'Einheit',
                    'value' => $row['unit'],
                    'error' => 'Einheit nicht gefunden.',
                    'suggestion' => $result->suggestion,
                ];
            }
        }

        // Sprache validieren (ISO-Code)
        if (!empty($row['language']) && !preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', (string) $row['language'])) {
            $errors[] = [
                'row' => $rowNum,
                'column' => $this->fieldToColumn('language', $columns),
                'field' => 'Sprache',
                'value' => $row['language'],
                'error' => 'Ungültiger Sprachcode. Erwartet: ISO 639-1 (z.B. de, en, fr).',
                'suggestion' => null,
            ];
        }

        // Index muss eine Zahl sein
        if ($row['index'] !== null && $row['index'] !== '' && !is_numeric($row['index'])) {
            $errors[] = [
                'row' => $rowNum,
                'column' => $this->fieldToColumn('index', $columns),
                'field' => 'Index',
                'value' => $row['index'],
                'error' => 'Index muss eine Zahl sein.',
                'suggestion' => null,
            ];
        }

        return $errors;
    }

    private function validateHierarchyRow(array $row, int $rowNum, array $columns): array
    {
        $errors = [];

        if (!empty($row['type']) && !in_array(mb_strtolower((string) $row['type']), self::VALID_HIERARCHY_TYPES, true)) {
            $errors[] = [
                'row' => $rowNum,
                'column' => $this->fieldToColumn('type', $columns),
                'field' => 'Typ',
                'value' => $row['type'],
                'error' => 'Ungültiger Hierarchie-Typ. Erlaubt: master, output.',
                'suggestion' => null,
            ];
        }

        // Mindestens Ebene 1 muss befüllt sein
        if (empty($row['level_1'])) {
            $errors[] = [
                'row' => $rowNum,
                'column' => 'C',
                'field' => 'Ebene 1',
                'value' => null,
                'error' => 'Mindestens Ebene 1 muss befüllt sein.',
                'suggestion' => null,
            ];
        }

        return $errors;
    }

    private function validatePriceRow(array $row, int $rowNum, array $columns): array
    {
        $errors = [];

        // Betrag muss numerisch sein
        if (!empty($row['amount']) && !is_numeric($row['amount'])) {
            $errors[] = [
                'row' => $rowNum,
                'column' => $this->fieldToColumn('amount', $columns),
                'field' => 'Betrag',
                'value' => $row['amount'],
                'error' => 'Betrag muss eine Zahl sein.',
                'suggestion' => null,
            ];
        }

        // Währung: ISO 4217 (3 Buchstaben)
        if (!empty($row['currency']) && !preg_match('/^[A-Z]{3}$/', strtoupper((string) $row['currency']))) {
            $errors[] = [
                'row' => $rowNum,
                'column' => $this->fieldToColumn('currency', $columns),
                'field' => 'Währung',
                'value' => $row['currency'],
                'error' => 'Ungültige Währung. Erwartet: ISO 4217 (z.B. EUR, USD, CHF).',
                'suggestion' => null,
            ];
        }

        // Preisart prüfen (wird ggf. automatisch beim Import angelegt)
        if (!empty($row['price_type'])) {
            $result = $this->resolver->resolvePriceType($row['price_type']);
            if (!$result->resolved() && !$this->isPending('price_type', $row['price_type'])) {
                // Preisarten werden beim Import automatisch angelegt – kein Fehler
            }
        }

        return $errors;
    }

    private function validateVariantRow(array $row, int $rowNum, array $columns): array
    {
        $errors = [];

        if (!empty($row['status']) && !in_array(mb_strtolower((string) $row['status']), self::VALID_PRODUCT_STATUS, true)) {
            $errors[] = [
                'row' => $rowNum,
                'column' => $this->fieldToColumn('status', $columns),
                'field' => 'Status',
                'value' => $row['status'],
                'error' => 'Ungültiger Status. Erlaubt: ' . implode(', ', self::VALID_PRODUCT_STATUS),
                'suggestion' => null,
            ];
        }

        return $errors;
    }

    private function validateRelationRow(array $row, int $rowNum, array $columns): array
    {
        $errors = [];

        // Beziehungstypen werden beim Import automatisch angelegt
        if (!empty($row['relation_type'])) {
            $result = $this->resolver->resolveRelationType($row['relation_type']);
            if (!$result->resolved() && !$this->isPending('relation_type', $row['relation_type'])) {
                // Beziehungstypen werden beim Import automatisch angelegt – kein Fehler
            }
        }

        return $errors;
    }

    // ──────────────────────────────────────────────
    //  Create vs. Update Zählung
    // ──────────────────────────────────────────────

    private function countCreateUpdate(string $sheetKey, array $rows, array $definition): array
    {
        $creates = 0;
        $updates = 0;

        foreach ($rows as $row) {
            $exists = match ($sheetKey) {
                '01_Produkttypen', '02_Attributgruppen', '05_Attribute' =>
                    !empty($row['technical_name']) && $this->resolver->attributeExists($row['technical_name']) !== null,
                '08_Produkte', '10_Varianten' => 
                    !empty($row['sku'] ?? $row['variant_sku'] ?? null)
                    && $this->resolver->productExists($row['sku'] ?? $row['variant_sku'] ?? '') !== null,
                default => false,
            };

            if ($exists) {
                $updates++;
            } else {
                $creates++;
            }
        }

        return ['creates' => $creates, 'updates' => $updates];
    }

    // ──────────────────────────────────────────────
    //  Pending-Registry (Cross-Sheet-Referenzen)
    // ──────────────────────────────────────────────

    /**
     * Baut eine Registry aller Entitäten auf, die im gleichen Import angelegt werden.
     * Damit können Cross-Sheet-Referenzen bei der Validierung akzeptiert werden.
     *
     * @return array<string, array<string>>  type → [lowercase_name, ...]
     */
    private function buildPendingEntities(ParseResult $parseResult): array
    {
        $pending = [];

        $mappings = [
            '01_Produkttypen'   => ['product_type', 'technical_name'],
            '02_Attributgruppen' => ['attribute_type', 'technical_name'],
            '04_Wertelisten'    => ['value_list', 'list_technical_name'],
            '05_Attribute'      => ['attribute', 'technical_name'],
            '08_Produkte'       => ['product', 'sku'],
        ];

        foreach ($mappings as $sheetKey => [$type, $field]) {
            if (!$parseResult->hasSheet($sheetKey)) {
                continue;
            }
            $pending[$type] = [];
            foreach ($parseResult->getSheetData($sheetKey) as $row) {
                if (!empty($row[$field])) {
                    $pending[$type][] = mb_strtolower(trim((string) $row[$field]));
                }
            }
        }

        // Einheiten: Gruppe und Einheit separat sammeln
        if ($parseResult->hasSheet('03_Einheiten')) {
            $pending['unit_group'] = [];
            $pending['unit'] = [];
            foreach ($parseResult->getSheetData('03_Einheiten') as $row) {
                if (!empty($row['group_technical_name'])) {
                    $pending['unit_group'][] = mb_strtolower(trim((string) $row['group_technical_name']));
                }
                if (!empty($row['technical_name'])) {
                    $pending['unit'][] = mb_strtolower(trim((string) $row['technical_name']));
                }
                // Auch Kürzel als Unit-Referenz akzeptieren
                if (!empty($row['abbreviation'])) {
                    $pending['unit'][] = mb_strtolower(trim((string) $row['abbreviation']));
                }
            }
        }

        // PriceTypes und RelationTypes werden automatisch angelegt – immer als pending markieren
        $pending['price_type'] = [];
        $pending['relation_type'] = [];

        return $pending;
    }

    /**
     * Prüft ob eine Entität im gleichen Import angelegt wird (Cross-Sheet-Referenz).
     */
    private function isPending(string $type, string $name): bool
    {
        if (!isset($this->pendingEntities[$type])) {
            // Für price_type und relation_type: immer akzeptieren (werden auto-erstellt)
            return in_array($type, ['price_type', 'relation_type'], true);
        }

        return in_array(mb_strtolower(trim($name)), $this->pendingEntities[$type], true);
    }

    // ──────────────────────────────────────────────
    //  Hilfs-Methoden
    // ──────────────────────────────────────────────

    private function fieldToColumn(string $field, array $columns): string
    {
        $flipped = array_flip($columns);
        return $flipped[$field] ?? '?';
    }

    private function fieldToHeader(string $field, string $sheetKey): string
    {
        $headerMap = [
            'technical_name' => 'Technischer Name',
            'name_de' => 'Name (Deutsch)',
            'name_en' => 'Name (Englisch)',
            'data_type' => 'Datentyp',
            'sku' => 'SKU',
            'name' => 'Produktname',
            'product_type' => 'Produkttyp',
            'attribute' => 'Attribut',
            'value' => 'Wert',
            'hierarchy' => 'Hierarchie',
            'type' => 'Typ',
        ];

        return $headerMap[$field] ?? $field;
    }

    private function isValidBoolean(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $lower = mb_strtolower(trim((string) $value));
        return in_array($lower, array_merge(self::BOOLEAN_TRUE, self::BOOLEAN_FALSE), true);
    }
}
