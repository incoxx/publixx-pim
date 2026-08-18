<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\Hierarchy;
use App\Models\PriceType;
use App\Models\ProductRelationType;
use App\Models\ProductType;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Legt die Kohlhammer/COVER-Konfiguration in einer leeren anyPIM-Instanz an.
 *
 * Quelle ist das Manifest in database/seeders/data/kohlhammer/, das aus den
 * PIMCORE-Importskripten abgeleitet wurde. Der Seeder legt keine Tabellen an —
 * das anyPIM-Schema bleibt unverändert. Er befüllt ausschließlich die
 * Konfigurationstabellen: Attributgruppen, Einheiten, Wertelisten, Attribute,
 * Produkttypen, Beziehungstypen, Preisarten und Hierarchiewurzeln.
 *
 * Idempotent: mehrfaches Ausführen ändert nichts, was schon existiert, und
 * ergänzt nur Fehlendes. Damit kann das Manifest nach dem Profiling-Lauf
 * (Schritt 2 der Migration) erweitert und der Seeder erneut gefahren werden.
 *
 *   php artisan db:seed --class=KohlhammerSchemaSeeder
 */
class KohlhammerSchemaSeeder extends Seeder
{
    private const SOURCE_SYSTEM = 'COVER';

    /** @var array<string, string> technical_name => id */
    private array $attributeTypes = [];

    /** @var array<string, string> technical_name => id */
    private array $valueLists = [];

    /** @var array<string, string> technical_name => id */
    private array $unitGroups = [];

    /** @var array<string, string> technical_name => id */
    private array $units = [];

    /** @var array<string, string> technical_name => id */
    private array $productTypes = [];

    public function run(): void
    {
        $structure = require database_path('seeders/data/kohlhammer/structure.php');
        $attributes = require database_path('seeders/data/kohlhammer/attributes.php');

        $this->seedAttributeTypes($structure['attribute_types']);
        $this->seedUnits();
        $this->seedValueLists($structure['value_lists']);
        $this->seedAttributes($attributes);
        $this->seedProductTypes($structure['product_types']);
        $this->seedRelationTypes($structure['relation_types']);
        $this->seedPriceTypes($structure['price_types']);
        $this->seedPriceMetadata($structure['price_metadata']);
        $this->seedHierarchies($structure['hierarchies']);

        $this->command?->info('Kohlhammer-Schema angelegt.');
        $this->command?->line('  Wertelisten ohne Einträge werden vom Profiling-Lauf befüllt (Schritt 2).');
    }

    // ─── Attributgruppen ────────────────────────────────────────────────

    private function seedAttributeTypes(array $rows): void
    {
        foreach ($rows as $row) {
            $type = AttributeType::firstOrCreate(
                ['technical_name' => $row['technical_name']],
                ['name_de' => $row['name_de'], 'sort_order' => $row['sort_order']],
            );
            $this->attributeTypes[$row['technical_name']] = $type->id;
        }

        $this->command?->info(count($rows) . ' Attributgruppen.');
    }

    // ─── Einheiten ──────────────────────────────────────────────────────

    /**
     * Nur die vom Manifest referenzierten Gruppen. Existieren sie bereits
     * (z. B. aus dem Demo-Seeder), werden sie wiederverwendet.
     */
    private function seedUnits(): void
    {
        $definitions = [
            'length' => [
                'name_de' => 'Länge',
                'units'   => [
                    ['technical_name' => 'millimeter', 'abbreviation' => 'mm', 'conversion_factor' => '1',    'is_base_unit' => true],
                    ['technical_name' => 'centimeter', 'abbreviation' => 'cm', 'conversion_factor' => '10',   'is_base_unit' => false],
                ],
            ],
            'weight' => [
                'name_de' => 'Gewicht',
                'units'   => [
                    ['technical_name' => 'gram',     'abbreviation' => 'g',  'conversion_factor' => '1',    'is_base_unit' => true],
                    ['technical_name' => 'kilogram', 'abbreviation' => 'kg', 'conversion_factor' => '1000', 'is_base_unit' => false],
                ],
            ],
        ];

        foreach ($definitions as $technicalName => $definition) {
            $group = UnitGroup::firstOrCreate(
                ['technical_name' => $technicalName],
                ['name_de' => $definition['name_de']],
            );
            $this->unitGroups[$technicalName] = $group->id;

            foreach ($definition['units'] as $unit) {
                $model = Unit::firstOrCreate(
                    ['unit_group_id' => $group->id, 'technical_name' => $unit['technical_name']],
                    [
                        'abbreviation'      => $unit['abbreviation'],
                        'conversion_factor' => $unit['conversion_factor'],
                        'is_base_unit'      => $unit['is_base_unit'],
                    ],
                );
                $this->units[$unit['technical_name']] = $model->id;
            }
        }
    }

    // ─── Wertelisten ────────────────────────────────────────────────────

    private function seedValueLists(array $rows): void
    {
        $entryCount = 0;

        foreach ($rows as $row) {
            $list = ValueList::firstOrCreate(
                ['technical_name' => $row['technical_name']],
                ['name_de' => $row['name_de'], 'max_depth' => $row['max_depth'] ?? 1],
            );
            $this->valueLists[$row['technical_name']] = $list->id;

            foreach ($row['entries'] as $entry) {
                ValueListEntry::firstOrCreate(
                    ['value_list_id' => $list->id, 'technical_name' => $entry['technical_name']],
                    [
                        'display_value_de' => $entry['display_value_de'],
                        'sort_order'       => $entry['sort_order'],
                    ],
                );
                $entryCount++;
            }
        }

        $this->command?->info(count($rows) . " Wertelisten, {$entryCount} Einträge.");
    }

    // ─── Attribute ──────────────────────────────────────────────────────

    private function seedAttributes(array $manifest): void
    {
        $position = 0;
        $created = 0;

        foreach ($manifest as $blockName => $rows) {
            foreach ($rows as $row) {
                $position += 10;

                $payload = [
                    'name_de'               => $row['name_de'],
                    'data_type'             => $row['data_type'],
                    'attribute_type_id'     => $this->attributeTypes[$row['attribute_type']] ?? null,
                    'value_list_id'         => isset($row['value_list']) ? ($this->valueLists[$row['value_list']] ?? null) : null,
                    'unit_group_id'         => isset($row['unit_group']) ? ($this->unitGroups[$row['unit_group']] ?? null) : null,
                    'default_unit_id'       => isset($row['default_unit']) ? ($this->units[$row['default_unit']] ?? null) : null,
                    'is_translatable'       => $row['is_translatable'] ?? false,
                    'is_multipliable'       => $row['is_multipliable'] ?? false,
                    'max_multiplied'        => $row['max_multiplied'] ?? null,
                    'is_unique'             => $row['is_unique'] ?? false,
                    'is_quick_search'       => $row['is_quick_search'] ?? false,
                    'is_primary'            => $row['is_primary'] ?? false,
                    'is_readonly'           => $row['is_readonly'] ?? false,
                    'position'              => $position,
                    'source_system'         => self::SOURCE_SYSTEM,
                    'source_attribute_name' => $row['source'],
                    'source_attribute_key'  => $blockName,
                    'status'                => 'active',
                ];

                $attribute = Attribute::firstOrCreate(
                    ['technical_name' => $row['technical_name']],
                    $payload,
                );

                if ($attribute->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        $total = array_sum(array_map('count', $manifest));
        $this->command?->info("{$total} Attribute im Manifest, {$created} neu angelegt.");
    }

    // ─── Produkttypen ───────────────────────────────────────────────────

    private function seedProductTypes(array $rows): void
    {
        // is_master_data ist eine geplante, noch nicht migrierte Spalte
        // (siehe kohlhammer-cover-migration.md, 7.4). Solange sie fehlt,
        // wird der Wert weggelassen statt den Seeder scheitern zu lassen.
        $hasMasterDataFlag = Schema::hasColumn('product_types', 'is_master_data');

        foreach ($rows as $row) {
            $payload = [
                'name_de'                 => $row['name_de'],
                'has_variants'            => false,
                'has_ean'                 => $row['has_ean'] ?? false,
                'has_prices'              => $row['has_prices'] ?? false,
                'has_media'               => $row['has_media'] ?? false,
                'has_stock'               => false,
                'has_physical_dimensions' => $row['has_physical_dimensions'] ?? false,
                'default_attribute_groups' => $row['default_attribute_groups'],
                'sort_order'              => $row['sort_order'],
                'is_active'               => true,
            ];

            if ($hasMasterDataFlag) {
                $payload['is_master_data'] = $row['is_master_data'];
            }

            $type = ProductType::firstOrCreate(['technical_name' => $row['technical_name']], $payload);
            $this->productTypes[$row['technical_name']] = $type->id;
        }

        if (! $hasMasterDataFlag) {
            $this->command?->warn(
                '  product_types.is_master_data fehlt — Stammdatentypen (contributor, adresse) '
                . 'erscheinen bis zur Migration im normalen Produktkatalog.'
            );
        }

        $this->command?->info(count($rows) . ' Produkttypen.');
    }

    // ─── Beziehungstypen ────────────────────────────────────────────────

    private function seedRelationTypes(array $rows): void
    {
        foreach ($rows as $row) {
            ProductRelationType::firstOrCreate(
                ['technical_name' => $row['technical_name']],
                [
                    'name_de'                         => $row['name_de'],
                    'is_bidirectional'                => $row['is_bidirectional'],
                    'allows_free_attributes'          => false,
                    'allowed_source_product_type_ids' => $this->resolveProductTypeIds($row['source_types']),
                    'allowed_target_product_type_ids' => $this->resolveProductTypeIds($row['target_types']),
                ],
            );
        }

        $this->command?->info(count($rows) . ' Beziehungstypen.');
    }

    /**
     * @param  string[]  $technicalNames
     * @return string[]
     */
    private function resolveProductTypeIds(array $technicalNames): array
    {
        return array_values(array_filter(
            array_map(fn (string $name): ?string => $this->productTypes[$name] ?? null, $technicalNames),
        ));
    }

    // ─── Preisarten ─────────────────────────────────────────────────────

    private function seedPriceTypes(array $rows): void
    {
        foreach ($rows as $row) {
            PriceType::firstOrCreate(
                ['technical_name' => $row['technical_name']],
                ['name_de' => $row['name_de']],
            );
        }

        $this->command?->info(count($rows) . ' Preisarten.');
    }

    // ─── Preis-Metadaten ────────────────────────────────────────────────

    /**
     * Setzt das Feature aus plan-preis-metadaten.md voraus. Fehlt die
     * Tabelle, wird der Block übersprungen — der Rest des Seeders läuft
     * durch, damit eine leere Instanz auch ohne das Feature aufsetzbar ist.
     */
    private function seedPriceMetadata(array $rows): void
    {
        if (! Schema::hasTable('price_metadata_definitions')) {
            $this->command?->warn(
                '  price_metadata_definitions fehlt — ' . count($rows) . ' Preis-Metadaten übersprungen '
                . '(Feature aus plan-preis-metadaten.md noch nicht umgesetzt).'
            );

            return;
        }

        $definition = \App\Models\PriceMetadataDefinition::class;

        foreach ($rows as $row) {
            $definition::firstOrCreate(
                ['technical_name' => $row['technical_name']],
                [
                    'name_de'    => $row['name_de'],
                    'value_type' => $row['value_type'],
                    'sort_order' => $row['sort_order'],
                ],
            );
        }

        $this->command?->info(count($rows) . ' Preis-Metadaten.');
    }

    // ─── Hierarchien ────────────────────────────────────────────────────

    private function seedHierarchies(array $rows): void
    {
        foreach ($rows as $row) {
            Hierarchy::firstOrCreate(
                ['technical_name' => $row['technical_name']],
                [
                    'name_de'        => $row['name_de'],
                    'hierarchy_type' => $row['hierarchy_type'],
                    'description'    => 'Quelle: ' . $row['source'],
                ],
            );
        }

        $this->command?->info(count($rows) . ' Hierarchien (nur Wurzeln — Knoten kommen aus dem Import).');
    }
}
