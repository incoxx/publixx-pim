<?php

declare(strict_types=1);

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Importiert PIM-Daten aus einem JSON-Format (erzeugt von JsonFormatExporter).
 *
 * Nutzt den bestehenden ImportExecutor für die eigentliche Schreiblogik.
 * Wandelt die JSON-Sektionen in das vom ImportExecutor erwartete Zeilenformat um.
 *
 * Die Import-Reihenfolge folgt den Abhängigkeiten:
 *   1. Einheitengruppen + Einheiten
 *   2. Attributgruppen
 *   3. Wertelisten
 *   4. Attribute
 *   5. Produkttypen
 *   6. Hierarchien
 *   7. Hierarchie-Attribut-Zuordnungen
 *   8. Produkte
 *   9. Produktwerte
 *  10. Varianten
 *  11. Produkt-Hierarchie-Zuordnungen
 *  12. Produktbeziehungen
 *  13. Preise
 *  14. Medien-Zuordnungen
 */
class JsonFormatImporter
{
    /** Import-Modus: 'update' (Upsert) oder 'delete_insert'. */
    private string $mode = 'update';

    /** Statistiken pro Sektion. */
    private array $stats = [];

    /** Fehler während des Imports. */
    private array $errors = [];

    public function __construct(
        private readonly ImportExecutor $executor,
    ) {}

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['update', 'delete_insert']) ? $mode : 'update';
    }

    /**
     * Importiert Daten aus einem JSON-String.
     */
    public function importFromString(string $json): JsonImportResult
    {
        $data = json_decode($json, true);
        if ($data === null) {
            throw new \RuntimeException('Ungültiges JSON: ' . json_last_error_msg());
        }

        return $this->importData($data);
    }

    /**
     * Importiert Daten aus einer JSON-Datei.
     */
    public function importFromFile(string $filePath): JsonImportResult
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Datei nicht gefunden: {$filePath}");
        }

        $json = file_get_contents($filePath);
        return $this->importFromString($json);
    }

    /**
     * Importiert die übergebenen Daten in korrekter Abhängigkeitsreihenfolge.
     */
    public function importData(array $data): JsonImportResult
    {
        $startTime = microtime(true);
        $this->stats = [];
        $this->errors = [];

        $meta = $data['_meta'] ?? [];
        Log::channel('import')->info('JSON-Import gestartet', [
            'format' => $meta['format'] ?? 'unbekannt',
            'version' => $meta['version'] ?? 'unbekannt',
            'exported_at' => $meta['exported_at'] ?? 'unbekannt',
            'sections' => $meta['sections'] ?? array_keys($data),
            'mode' => $this->mode,
        ]);

        $this->executor->setMode($this->mode);

        // ParseResult simulieren für den ImportExecutor
        $parseResult = $this->buildParseResult($data);

        DB::beginTransaction();

        try {
            $result = $this->executor->execute($parseResult);

            DB::commit();

            $duration = round(microtime(true) - $startTime, 2);
            Log::channel('import')->info("JSON-Import abgeschlossen in {$duration}s", [
                'stats' => $result->stats,
                'affected_products' => count($result->affectedProductIds),
            ]);

            return new JsonImportResult(
                stats: $result->stats,
                affectedProductIds: $result->affectedProductIds,
                skippedDetails: $result->skippedDetails,
                durationSeconds: $duration,
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::channel('import')->error("JSON-Import fehlgeschlagen: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Validiert die JSON-Struktur ohne zu importieren.
     *
     * @return array{valid: bool, sections: string[], errors: string[]}
     */
    public function validate(array $data): array
    {
        $errors = [];
        $sections = [];

        if (!isset($data['_meta'])) {
            $errors[] = 'Fehlende _meta-Sektion';
        }

        $knownSections = [
            'unit_groups', 'units', 'attribute_views', 'attribute_groups',
            'value_lists', 'attributes', 'product_types', 'price_types',
            'relation_types', 'hierarchies', 'hierarchy_attribute_assignments',
            'products', 'product_attribute_values', 'variants',
            'product_hierarchy_assignments', 'product_relations',
            'prices', 'media_assignments',
        ];

        foreach ($knownSections as $section) {
            if (isset($data[$section])) {
                $sections[] = $section;
                if (!is_array($data[$section])) {
                    $errors[] = "Sektion '{$section}' muss ein Array sein";
                }
            }
        }

        // Pflichtfelder prüfen
        if (isset($data['products'])) {
            foreach ($data['products'] as $i => $product) {
                if (empty($product['sku'])) {
                    $errors[] = "products[{$i}]: SKU fehlt";
                }
                if (empty($product['name'])) {
                    $errors[] = "products[{$i}]: Name fehlt";
                }
                if (empty($product['product_type'])) {
                    $errors[] = "products[{$i}]: Produkttyp fehlt";
                }
            }
        }

        if (isset($data['attributes'])) {
            foreach ($data['attributes'] as $i => $attr) {
                if (empty($attr['technical_name'])) {
                    $errors[] = "attributes[{$i}]: technical_name fehlt";
                }
                if (empty($attr['data_type'])) {
                    $errors[] = "attributes[{$i}]: data_type fehlt";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'sections' => $sections,
            'errors' => $errors,
        ];
    }

    /**
     * Wandelt die JSON-Daten in ein ParseResult um, das der ImportExecutor verarbeiten kann.
     */
    private function buildParseResult(array $data): ParseResult
    {
        $sheets = [];

        // Einheiten (JSON: unit_groups + units → Sheet: 03_Einheiten)
        if (isset($data['units'])) {
            $sheets['03_Einheiten'] = $this->mapUnitsToRows($data['units'], $data['unit_groups'] ?? []);
        }

        // Attributgruppen (JSON: attribute_groups → Sheet: 02_Attributgruppen)
        if (isset($data['attribute_groups'])) {
            $sheets['02_Attributgruppen'] = array_map(fn ($g) => [
                'technical_name' => $g['technical_name'],
                'name_de' => $g['name_de'],
                'name_en' => $g['name_en'] ?? null,
                'description' => $g['description'] ?? null,
                'sort_order' => $g['sort_order'] ?? 0,
            ], $data['attribute_groups']);
        }

        // Wertelisten (JSON: value_lists mit entries → Sheet: 04_Wertelisten)
        if (isset($data['value_lists'])) {
            $sheets['04_Wertelisten'] = $this->mapValueListsToRows($data['value_lists']);
        }

        // Attribute (JSON: attributes → Sheet: 05_Attribute)
        if (isset($data['attributes'])) {
            $sheets['05_Attribute'] = array_map(fn ($a) => [
                'technical_name' => $a['technical_name'],
                'name_de' => $a['name_de'] ?? $a['technical_name'],
                'name_en' => $a['name_en'] ?? null,
                'description' => $a['description'] ?? null,
                'data_type' => $a['data_type'],
                'attribute_group' => $a['attribute_group'] ?? null,
                'value_list' => $a['value_list'] ?? null,
                'unit_group' => $a['unit_group'] ?? null,
                'default_unit' => $a['default_unit'] ?? null,
                'is_multipliable' => $a['is_multipliable'] ?? false,
                'max_multiplied' => $a['max_multiplied'] ?? null,
                'is_translatable' => $a['is_translatable'] ?? false,
                'is_mandatory' => $a['is_mandatory'] ?? false,
                'is_unique' => $a['is_unique'] ?? false,
                'is_searchable' => $a['is_searchable'] ?? true,
                'is_inheritable' => $a['is_inheritable'] ?? true,
                'parent_attribute' => $a['parent_attribute'] ?? null,
                'source_system' => $a['source_system'] ?? null,
                'views' => isset($a['views']) ? implode(',', $a['views']) : null,
            ], $data['attributes']);
        }

        // Produkttypen (JSON: product_types → Sheet: 01_Produkttypen)
        if (isset($data['product_types'])) {
            $sheets['01_Produkttypen'] = array_map(fn ($t) => [
                'technical_name' => $t['technical_name'],
                'name_de' => $t['name_de'] ?? $t['technical_name'],
                'name_en' => $t['name_en'] ?? null,
                'description' => $t['description'] ?? null,
                'has_variants' => $t['has_variants'] ?? false,
                'has_ean' => $t['has_ean'] ?? false,
                'has_prices' => $t['has_prices'] ?? false,
                'has_media' => $t['has_media'] ?? false,
            ], $data['product_types']);
        }

        // Hierarchien (JSON: hierarchies mit nodes → Sheet: 06_Hierarchien)
        if (isset($data['hierarchies'])) {
            $sheets['06_Hierarchien'] = $this->mapHierarchiesToRows($data['hierarchies']);
        }

        // Hierarchie-Attribut-Zuordnungen (JSON: hierarchy_attribute_assignments → Sheet: 07_Hierarchie_Attribute)
        if (isset($data['hierarchy_attribute_assignments'])) {
            $sheets['07_Hierarchie_Attribute'] = array_map(fn ($a) => [
                'hierarchy' => $a['hierarchy'],
                'node_path' => $a['node_path'],
                'attribute' => $a['attribute'],
                'collection_name' => $a['collection_name'] ?? null,
                'collection_sort' => $a['collection_sort'] ?? 0,
                'attribute_sort' => $a['attribute_sort'] ?? 0,
                'dont_inherit' => $a['dont_inherit'] ?? false,
            ], $data['hierarchy_attribute_assignments']);
        }

        // Produkte (JSON: products → Sheet: 08_Produkte)
        if (isset($data['products'])) {
            $sheets['08_Produkte'] = array_map(fn ($p) => [
                'sku' => $p['sku'],
                'name' => $p['name'],
                'name_en' => $p['name_en'] ?? null,
                'product_type' => $p['product_type'],
                'ean' => $p['ean'] ?? null,
                'status' => $p['status'] ?? 'draft',
            ], $data['products']);
        }

        // Produktwerte (JSON: product_attribute_values → Sheet: 09_Produktwerte)
        if (isset($data['product_attribute_values'])) {
            $sheets['09_Produktwerte'] = array_map(fn ($v) => [
                'sku' => $v['sku'],
                'attribute' => $v['attribute'],
                'value' => $v['value'],
                'unit' => $v['unit'] ?? null,
                'language' => $v['language'] ?? null,
                'index' => $v['index'] ?? 0,
            ], $data['product_attribute_values']);
        }

        // Varianten (JSON: variants → Sheet: 10_Varianten)
        if (isset($data['variants'])) {
            $sheets['10_Varianten'] = array_map(fn ($v) => [
                'parent_sku' => $v['parent_sku'],
                'variant_sku' => $v['sku'],
                'variant_name' => $v['name'],
                'name_en' => $v['name_en'] ?? null,
                'ean' => $v['ean'] ?? null,
                'status' => $v['status'] ?? 'draft',
            ], $data['variants']);
        }

        // Produkt-Hierarchien (JSON: product_hierarchy_assignments → Sheet: 11_Produkt_Hierarchien)
        if (isset($data['product_hierarchy_assignments'])) {
            $sheets['11_Produkt_Hierarchien'] = array_map(fn ($a) => [
                'sku' => $a['sku'],
                'hierarchy' => $a['hierarchy'],
                'node_path' => $a['node_path'],
            ], $data['product_hierarchy_assignments']);
        }

        // Produktbeziehungen (JSON: product_relations → Sheet: 12_Produktbeziehungen)
        if (isset($data['product_relations'])) {
            $sheets['12_Produktbeziehungen'] = array_map(fn ($r) => [
                'source_sku' => $r['source_sku'],
                'target_sku' => $r['target_sku'],
                'relation_type' => $r['relation_type'],
                'sort_order' => $r['sort_order'] ?? 0,
            ], $data['product_relations']);
        }

        // Preise (JSON: prices → Sheet: 13_Preise)
        if (isset($data['prices'])) {
            $sheets['13_Preise'] = array_map(fn ($p) => [
                'sku' => $p['sku'],
                'price_type' => $p['price_type'],
                'amount' => $p['amount'],
                'currency' => $p['currency'] ?? 'EUR',
                'valid_from' => $p['valid_from'] ?? null,
                'valid_to' => $p['valid_to'] ?? null,
                'country' => $p['country'] ?? null,
                'scale_from' => $p['scale_from'] ?? null,
                'scale_to' => $p['scale_to'] ?? null,
            ], $data['prices']);
        }

        // Medien (JSON: media_assignments → Sheet: 14_Medien)
        if (isset($data['media_assignments'])) {
            $sheets['14_Medien'] = array_map(fn ($m) => [
                'sku' => $m['sku'],
                'file_name' => $m['file_name'],
                'media_type' => $m['media_type'] ?? 'image',
                'usage_type' => $m['usage_type'] ?? 'gallery',
                'title_de' => $m['title_de'] ?? null,
                'title_en' => $m['title_en'] ?? null,
                'alt_text_de' => $m['alt_text_de'] ?? null,
                'sort_order' => $m['sort_order'] ?? 0,
                'is_primary' => $m['is_primary'] ?? false,
            ], $data['media_assignments']);
        }

        return new ParseResult(
            sheetsFound: array_keys($sheets),
            data: $sheets,
        );
    }

    /**
     * Wandelt JSON-Einheiten in das Zeilenformat des Import-Sheets um.
     */
    private function mapUnitsToRows(array $units, array $unitGroups): array
    {
        // Index der Gruppennamen aufbauen
        $groupNames = [];
        foreach ($unitGroups as $g) {
            $groupNames[$g['technical_name']] = $g['name_de'] ?? $g['technical_name'];
        }

        return array_map(fn ($u) => [
            'group_technical_name' => $u['unit_group'],
            'group_name_de' => $groupNames[$u['unit_group']] ?? $u['unit_group'],
            'technical_name' => $u['technical_name'],
            'abbreviation' => $u['abbreviation'],
            'conversion_factor' => $u['conversion_factor'] ?? 1,
            'is_base_unit' => $u['is_base_unit'] ?? false,
        ], $units);
    }

    /**
     * Wandelt JSON-Wertelisten (mit verschachtelten entries) in flache Zeilen um.
     */
    private function mapValueListsToRows(array $valueLists): array
    {
        $rows = [];

        foreach ($valueLists as $list) {
            $entries = $list['entries'] ?? [];
            if (empty($entries)) {
                $rows[] = [
                    'list_technical_name' => $list['technical_name'],
                    'list_name_de' => $list['name_de'] ?? $list['technical_name'],
                    'entry_technical_name' => null,
                    'display_value_de' => null,
                    'display_value_en' => null,
                    'sort_order' => 0,
                ];
            } else {
                foreach ($entries as $entry) {
                    $rows[] = [
                        'list_technical_name' => $list['technical_name'],
                        'list_name_de' => $list['name_de'] ?? $list['technical_name'],
                        'entry_technical_name' => $entry['technical_name'],
                        'display_value_de' => $entry['display_value_de'] ?? $entry['technical_name'],
                        'display_value_en' => $entry['display_value_en'] ?? null,
                        'sort_order' => $entry['sort_order'] ?? 0,
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * Wandelt JSON-Hierarchien (mit verschachtelten nodes) in flache Zeilen um.
     */
    private function mapHierarchiesToRows(array $hierarchies): array
    {
        $rows = [];

        foreach ($hierarchies as $hierarchy) {
            $nodes = $hierarchy['nodes'] ?? [];
            foreach ($nodes as $node) {
                // Pfad in Level-Spalten aufteilen
                $path = trim($node['path'] ?? '', '/');
                $levels = $path ? explode('/', $path) : [];

                $row = [
                    'hierarchy' => $hierarchy['technical_name'],
                    'type' => $hierarchy['hierarchy_type'] ?? 'master',
                    'level_1' => $levels[0] ?? null,
                    'level_2' => $levels[1] ?? null,
                    'level_3' => $levels[2] ?? null,
                    'level_4' => $levels[3] ?? null,
                    'level_5' => $levels[4] ?? null,
                    'level_6' => $levels[5] ?? null,
                ];

                $rows[] = $row;
            }
        }

        return $rows;
    }
}
