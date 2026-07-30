<?php

declare(strict_types=1);

namespace App\Services\Import;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    /** Import-ID für Progress/Cancel-Tracking. */
    private ?string $importId = null;

    public function __construct(
        private readonly ImportExecutor $executor,
    ) {}

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['update', 'delete_insert']) ? $mode : 'update';
    }

    /**
     * Setzt die Import-ID für Cancel-Tracking.
     */
    public function setImportId(string $importId): void
    {
        $this->importId = $importId;
        $this->executor->setImportId($importId);
    }

    /**
     * Gibt die Import-ID zurück (erzeugt eine neue falls nicht gesetzt).
     */
    public function getImportId(): string
    {
        if ($this->importId === null) {
            $this->importId = Str::uuid()->toString();
            $this->executor->setImportId($this->importId);
        }

        return $this->importId;
    }

    /**
     * Setzt eine Callback-Funktion für Fortschrittsmeldungen (pro Sektion/Sheet).
     */
    public function setProgressCallback(callable $callback): void
    {
        $this->executor->setProgressCallback($callback);
    }

    /**
     * Setzt eine Callback-Funktion, die periodisch waehrend langer Sheets
     * aufgerufen wird (haelt die SSE-Verbindung zwischen Progress-Events am Leben).
     */
    public function setHeartbeatCallback(callable $callback): void
    {
        $this->executor->setHeartbeatCallback($callback);
    }

    /**
     * Bricht einen laufenden Import ab (per Import-ID, gleicher Cancel-Kanal wie
     * der BMEcat-Import — ImportExecutor::checkCancelled() prueft denselben Key).
     */
    public static function cancelImport(string $importId): void
    {
        Cache::put("bmecat_import_cancel_{$importId}", true, 300);
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

        // ImportExecutor verwaltet seine eigene Transaktion – kein zusätzliches Wrapping nötig.
        $result = $this->executor->execute($parseResult);

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
            'attribute_formatting_rules', 'comparison_operator_groups', 'comparison_operators',
            'value_lists', 'attributes', 'product_types', 'price_types', 'price_regions',
            'relation_types', 'manufacturers', 'media_languages', 'media_countries',
            'media_usage_types', 'dictionary_entries', 'collection_types',
            'hierarchies', 'hierarchy_attribute_assignments',
            'products', 'product_attribute_values', 'variants',
            'product_hierarchy_assignments', 'product_relations',
            'prices', 'media_assignments', 'product_reference_profiles',
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

        // Attribut-Sichten (JSON: attribute_views → Sheet: 15_Attribut_Sichten)
        if (isset($data['attribute_views'])) {
            $sheets['15_Attribut_Sichten'] = array_map(fn ($v) => [
                'technical_name' => $v['technical_name'],
                'name_de' => $v['name_de'],
                'name_en' => $v['name_en'] ?? null,
                'description' => $v['description'] ?? null,
                'sort_order' => $v['sort_order'] ?? 0,
                'is_write_protected' => $v['is_write_protected'] ?? false,
            ], $data['attribute_views']);
        }

        // Preistypen (JSON: price_types → Sheet: 16_Preistypen)
        if (isset($data['price_types'])) {
            $sheets['16_Preistypen'] = array_map(fn ($p) => [
                'technical_name' => $p['technical_name'],
                'name_de' => $p['name_de'],
                'name_en' => $p['name_en'] ?? null,
            ], $data['price_types']);
        }

        // Beziehungstypen (JSON: relation_types → Sheet: 17_Beziehungstypen)
        if (isset($data['relation_types'])) {
            $sheets['17_Beziehungstypen'] = array_map(fn ($r) => [
                'technical_name' => $r['technical_name'],
                'name_de' => $r['name_de'],
                'name_en' => $r['name_en'] ?? null,
                'is_bidirectional' => $r['is_bidirectional'] ?? false,
            ], $data['relation_types']);
        }

        // Formatierungsregeln (JSON: attribute_formatting_rules → Sheet: 18_Formatierungsregeln)
        if (isset($data['attribute_formatting_rules'])) {
            $sheets['18_Formatierungsregeln'] = array_map(fn ($r) => [
                'name' => $r['name'],
                'rule_type' => $r['rule_type'],
                'config' => $r['config'] ?? null,
                'description' => $r['description'] ?? null,
            ], $data['attribute_formatting_rules']);
        }

        // Vergleichsoperator-Gruppen (JSON: comparison_operator_groups → Sheet: 19_Vergleichsoperator_Gruppen)
        if (isset($data['comparison_operator_groups'])) {
            $sheets['19_Vergleichsoperator_Gruppen'] = array_map(fn ($g) => [
                'technical_name' => $g['technical_name'],
                'name_de' => $g['name_de'],
                'name_en' => $g['name_en'] ?? null,
            ], $data['comparison_operator_groups']);
        }

        // Vergleichsoperatoren (JSON: comparison_operators → Sheet: 20_Vergleichsoperatoren)
        if (isset($data['comparison_operators'])) {
            $sheets['20_Vergleichsoperatoren'] = array_map(fn ($o) => [
                'group' => $o['group'],
                'technical_name' => $o['technical_name'],
                'symbol' => $o['symbol'],
                'description_de' => $o['description_de'] ?? null,
            ], $data['comparison_operators']);
        }

        // Preisregionen (JSON: price_regions → Sheet: 21_Preisregionen)
        if (isset($data['price_regions'])) {
            $sheets['21_Preisregionen'] = array_map(fn ($r) => [
                'code' => $r['code'],
                'name' => $r['name'] ?? $r['code'],
                'type' => $r['type'] ?? 'country',
                'metadata' => $r['metadata'] ?? null,
            ], $data['price_regions']);
        }

        // Hersteller (JSON: manufacturers → Sheet: 22_Hersteller)
        if (isset($data['manufacturers'])) {
            $sheets['22_Hersteller'] = array_map(fn ($m) => [
                'name' => $m['name'],
                'street' => $m['street'] ?? null,
                'zip' => $m['zip'] ?? null,
                'city' => $m['city'] ?? null,
                'country' => $m['country'] ?? null,
                'email' => $m['email'] ?? null,
                'website' => $m['website'] ?? null,
            ], $data['manufacturers']);
        }

        // Medien-Sprachen (JSON: media_languages → Sheet: 23_Medien_Sprachen)
        if (isset($data['media_languages'])) {
            $sheets['23_Medien_Sprachen'] = array_map(fn ($l) => [
                'technical_name' => $l['technical_name'],
                'name_de' => $l['name_de'],
                'name_en' => $l['name_en'] ?? null,
                'sort_order' => $l['sort_order'] ?? 0,
            ], $data['media_languages']);
        }

        // Medien-Länder (JSON: media_countries → Sheet: 24_Medien_Laender)
        if (isset($data['media_countries'])) {
            $sheets['24_Medien_Laender'] = array_map(fn ($c) => [
                'technical_name' => $c['technical_name'],
                'name_de' => $c['name_de'],
                'name_en' => $c['name_en'] ?? null,
                'sort_order' => $c['sort_order'] ?? 0,
            ], $data['media_countries']);
        }

        // Medien-Verwendungstypen (JSON: media_usage_types → Sheet: 25_Medien_Verwendungstypen)
        if (isset($data['media_usage_types'])) {
            $sheets['25_Medien_Verwendungstypen'] = array_map(fn ($t) => [
                'technical_name' => $t['technical_name'],
                'name_de' => $t['name_de'],
                'name_en' => $t['name_en'] ?? null,
                'sort_order' => $t['sort_order'] ?? 0,
                'allowed_extensions' => $t['allowed_extensions'] ?? null,
                'restricted_display_mode' => $t['restricted_display_mode'] ?? null,
                'allowed_product_types' => $t['allowed_product_types'] ?? null,
            ], $data['media_usage_types']);
        }

        // Wörterbuch (JSON: dictionary_entries → Sheet: 26_Woerterbuch)
        if (isset($data['dictionary_entries'])) {
            $sheets['26_Woerterbuch'] = array_map(fn ($e) => [
                'category' => $e['category'] ?? null,
                'short_text_de' => $e['short_text_de'],
                'short_text_en' => $e['short_text_en'] ?? null,
                'long_text_de' => $e['long_text_de'] ?? $e['short_text_de'],
                'long_text_en' => $e['long_text_en'] ?? null,
                'status' => $e['status'] ?? 'active',
            ], $data['dictionary_entries']);
        }

        // Collection-Typen (JSON: collection_types → Sheet: 27_Collection_Typen)
        if (isset($data['collection_types'])) {
            $sheets['27_Collection_Typen'] = array_map(fn ($t) => [
                'technical_name' => $t['technical_name'],
                'name_de' => $t['name_de'],
                'name_en' => $t['name_en'] ?? null,
                'description' => $t['description'] ?? null,
                'icon' => $t['icon'] ?? null,
                'color' => $t['color'] ?? null,
                'default_attribute_groups' => $t['default_attribute_groups'] ?? null,
                'default_item_attribute_groups' => $t['default_item_attribute_groups'] ?? null,
                'default_price_type' => $t['default_price_type'] ?? null,
                'default_discount_attribute' => $t['default_discount_attribute'] ?? null,
                'requires_organization' => $t['requires_organization'] ?? false,
                'requires_snapshot' => $t['requires_snapshot'] ?? false,
                'allowed_export_formats' => $t['allowed_export_formats'] ?? null,
                'sort_order' => $t['sort_order'] ?? 0,
                'is_active' => $t['is_active'] ?? true,
            ], $data['collection_types']);
        }

        // Referenz-Profile (JSON: product_reference_profiles → Sheet: 28_Referenz_Profile)
        if (isset($data['product_reference_profiles'])) {
            $sheets['28_Referenz_Profile'] = array_map(fn ($p) => [
                'technical_name' => $p['technical_name'],
                'name' => $p['name'] ?? $p['technical_name'],
                'description' => $p['description'] ?? null,
                'parent_profile' => $p['parent_profile'] ?? null,
                'is_abstract' => $p['is_abstract'] ?? false,
                'is_active' => $p['is_active'] ?? true,
                'version' => $p['version'] ?? 1,
                'rules' => $p['rules'] ?? null,
                'golden_skus' => $p['golden_skus'] ?? null,
            ], $data['product_reference_profiles']);
        }

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
                'formatting_rule' => $a['formatting_rule'] ?? null,
                'comparison_operator_group' => $a['comparison_operator_group'] ?? null,
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
                'composite_expression' => $a['composite_expression'] ?? null,
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

        // Hierarchie-Ebene-Attribut-Zuordnungen (JSON: hierarchy_level_attribute_assignments → Sheet: 07b_Hierarchie_Ebene_Attribute)
        if (isset($data['hierarchy_level_attribute_assignments'])) {
            $sheets['07b_Hierarchie_Ebene_Attribute'] = array_map(fn ($a) => [
                'hierarchy' => $a['hierarchy'],
                'attribute' => $a['attribute'],
                'sort_order' => $a['sort_order'] ?? 0,
            ], $data['hierarchy_level_attribute_assignments']);
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
            $sheets['12_Produktbeziehungen'] = array_map(fn ($r) => array_filter([
                'source_sku' => $r['source_sku'],
                'target_sku' => $r['target_sku'],
                'relation_type' => $r['relation_type'],
                'sort_order' => $r['sort_order'] ?? 0,
                'attribute_values' => $r['attribute_values'] ?? null,
            ], fn ($v) => $v !== null), $data['product_relations']);
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
                'country' => $p['price_region'] ?? $p['country'] ?? null,
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
