<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeMapping;
use App\Models\AttributeType;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use Illuminate\Database\Seeder;

/**
 * ETIM-Klassifikation als Demo-Daten.
 *
 * Erstellt eine ETIM Output-Hierarchie mit Klassen (EC-Codes),
 * ETIM-Features (EF-Codes) als Attribute mit Wertlisten (EV-Codes),
 * und Attribut-Mappings von Master-Attributen auf ETIM-Features.
 *
 * Muss NACH DemoAttributeSeeder + DemoHierarchySeeder laufen.
 */
class EtimDemoSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 1. ETIM AttributeType ──────────────────────────────

        $atEtim = AttributeType::firstOrCreate(
            ['technical_name' => 'etim_features'],
            [
                'name_de' => 'ETIM-Merkmale',
                'name_en' => 'ETIM Features',
                'sort_order' => 50,
            ]
        );

        // ─── 2. Einheiten (Wiederverwendung bestehender) ────────

        $ugWeight = UnitGroup::where('technical_name', 'weight')->first();
        $ugLength = UnitGroup::where('technical_name', 'length')->first();
        $ugVoltage = UnitGroup::where('technical_name', 'voltage')->first();

        $unitKg = Unit::where('technical_name', 'kilogram')->first();
        $unitMm = Unit::where('technical_name', 'millimeter')->first();
        $unitV = Unit::where('technical_name', 'volt')->first();

        // ─── 3. ETIM-Wertlisten (EV-Codes) ─────────────────────

        $vlSchutzart = ValueList::firstOrCreate(
            ['technical_name' => 'etim-schutzart'],
            ['name_de' => 'ETIM Schutzart', 'name_en' => 'ETIM Protection Class']
        );

        $schutzartEntries = [
            ['technical_name' => 'EV000073', 'display_value_de' => 'IP20', 'display_value_en' => 'IP20', 'sort_order' => 10],
            ['technical_name' => 'EV000074', 'display_value_de' => 'IP44', 'display_value_en' => 'IP44', 'sort_order' => 20],
            ['technical_name' => 'EV000075', 'display_value_de' => 'IP55', 'display_value_en' => 'IP55', 'sort_order' => 30],
            ['technical_name' => 'EV000076', 'display_value_de' => 'IP65', 'display_value_en' => 'IP65', 'sort_order' => 40],
        ];

        foreach ($schutzartEntries as $entry) {
            ValueListEntry::firstOrCreate(
                ['value_list_id' => $vlSchutzart->id, 'technical_name' => $entry['technical_name']],
                $entry
            );
        }

        $vlAkkutyp = ValueList::firstOrCreate(
            ['technical_name' => 'etim-akkutyp'],
            ['name_de' => 'ETIM Akkutyp', 'name_en' => 'ETIM Battery Type']
        );

        $akkutypEntries = [
            ['technical_name' => 'EV000201', 'display_value_de' => 'Li-Ion', 'display_value_en' => 'Li-Ion', 'sort_order' => 10],
            ['technical_name' => 'EV000202', 'display_value_de' => 'NiCd', 'display_value_en' => 'NiCd', 'sort_order' => 20],
            ['technical_name' => 'EV000203', 'display_value_de' => 'NiMH', 'display_value_en' => 'NiMH', 'sort_order' => 30],
        ];

        foreach ($akkutypEntries as $entry) {
            ValueListEntry::firstOrCreate(
                ['value_list_id' => $vlAkkutyp->id, 'technical_name' => $entry['technical_name']],
                $entry
            );
        }

        // ─── 4. ETIM-Attribute (EF-Codes) ───────────────────────

        $efGewicht = Attribute::firstOrCreate(
            ['technical_name' => 'etim-EF000007'],
            [
                'name_de' => 'Gewicht',
                'name_en' => 'Weight',
                'data_type' => 'Float',
                'attribute_type_id' => $atEtim->id,
                'unit_group_id' => $ugWeight?->id,
                'default_unit_id' => $unitKg?->id,
                'source_system' => 'ETIM',
                'source_attribute_name' => 'Weight',
                'source_attribute_key' => 'EF000007',
                'status' => 'active',
            ]
        );

        $efLaenge = Attribute::firstOrCreate(
            ['technical_name' => 'etim-EF000008'],
            [
                'name_de' => 'Länge',
                'name_en' => 'Length',
                'data_type' => 'Float',
                'attribute_type_id' => $atEtim->id,
                'unit_group_id' => $ugLength?->id,
                'default_unit_id' => $unitMm?->id,
                'source_system' => 'ETIM',
                'source_attribute_name' => 'Length',
                'source_attribute_key' => 'EF000008',
                'status' => 'active',
            ]
        );

        $efSpannung = Attribute::firstOrCreate(
            ['technical_name' => 'etim-EF000011'],
            [
                'name_de' => 'Nennspannung',
                'name_en' => 'Rated Voltage',
                'data_type' => 'Float',
                'attribute_type_id' => $atEtim->id,
                'unit_group_id' => $ugVoltage?->id,
                'default_unit_id' => $unitV?->id,
                'source_system' => 'ETIM',
                'source_attribute_name' => 'Rated voltage',
                'source_attribute_key' => 'EF000011',
                'status' => 'active',
            ]
        );

        $efSchutzart = Attribute::firstOrCreate(
            ['technical_name' => 'etim-EF000042'],
            [
                'name_de' => 'Schutzart (IP)',
                'name_en' => 'Protection class (IP)',
                'data_type' => 'Selection',
                'attribute_type_id' => $atEtim->id,
                'value_list_id' => $vlSchutzart->id,
                'source_system' => 'ETIM',
                'source_attribute_name' => 'Protection class (IP)',
                'source_attribute_key' => 'EF000042',
                'status' => 'active',
            ]
        );

        $efAkkutyp = Attribute::firstOrCreate(
            ['technical_name' => 'etim-EF000150'],
            [
                'name_de' => 'Akkutyp',
                'name_en' => 'Battery type',
                'data_type' => 'Selection',
                'attribute_type_id' => $atEtim->id,
                'value_list_id' => $vlAkkutyp->id,
                'source_system' => 'ETIM',
                'source_attribute_name' => 'Battery type',
                'source_attribute_key' => 'EF000150',
                'status' => 'active',
            ]
        );

        $efAkkubetrieb = Attribute::firstOrCreate(
            ['technical_name' => 'etim-EF000160'],
            [
                'name_de' => 'Geeignet für Akkubetrieb',
                'name_en' => 'Suitable for battery operation',
                'data_type' => 'Flag',
                'attribute_type_id' => $atEtim->id,
                'source_system' => 'ETIM',
                'source_attribute_name' => 'Suitable for battery operation',
                'source_attribute_key' => 'EF000160',
                'status' => 'active',
            ]
        );

        $etimAttrs = [$efGewicht, $efLaenge, $efSpannung, $efSchutzart, $efAkkutyp, $efAkkubetrieb];

        // ─── 5. ETIM Output-Hierarchie ──────────────────────────

        $etim = Hierarchy::firstOrCreate(
            ['technical_name' => 'etim_classification'],
            [
                'name_de' => 'ETIM Klassifikation',
                'name_en' => 'ETIM Classification',
                'hierarchy_type' => 'output',
                'description' => 'ETIM-Klassifikation für elektrotechnische Produkte',
            ]
        );

        // Root: ETIM
        $root = HierarchyNode::firstOrCreate(
            ['hierarchy_id' => $etim->id, 'name_de' => 'ETIM', 'parent_node_id' => null],
            ['name_en' => 'ETIM', 'path' => '/', 'depth' => 0, 'sort_order' => 0]
        );
        $root->update(['path' => "/{$root->id}/"]);

        // Gruppe: EG000020 — Elektrowerkzeuge
        $egWerkzeuge = $this->createNode($etim, $root, 'EG000020 Elektrowerkzeuge', 'EG000020 Power Tools', 10);

        // Klassen unter EG000020
        $ecBohrmaschine = $this->createNode($etim, $egWerkzeuge, 'EC000200 Bohrmaschine/Schrauber', 'EC000200 Drill/Screwdriver', 10);
        $ecSchlagbohr = $this->createNode($etim, $egWerkzeuge, 'EC000201 Schlagbohrmaschine', 'EC000201 Hammer Drill', 20);
        $ecWinkelschl = $this->createNode($etim, $egWerkzeuge, 'EC000210 Winkelschleifer', 'EC000210 Angle Grinder', 30);
        $ecStichsaege = $this->createNode($etim, $egWerkzeuge, 'EC000220 Stichsäge', 'EC000220 Jigsaw', 40);

        // ─── 6. ETIM-Attribute den Klassen zuordnen ─────────────

        // EC000200: Bohrmaschine — alle Features
        $this->assignAttributes($ecBohrmaschine, [
            ['attr' => $efGewicht,     'collection' => 'Technische Daten',  'cSort' => 10, 'aSort' => 10],
            ['attr' => $efLaenge,      'collection' => 'Technische Daten',  'cSort' => 10, 'aSort' => 20],
            ['attr' => $efSpannung,    'collection' => 'Technische Daten',  'cSort' => 10, 'aSort' => 30],
            ['attr' => $efSchutzart,   'collection' => 'Sicherheit',        'cSort' => 20, 'aSort' => 10],
            ['attr' => $efAkkutyp,     'collection' => 'Energieversorgung', 'cSort' => 30, 'aSort' => 10],
            ['attr' => $efAkkubetrieb, 'collection' => 'Energieversorgung', 'cSort' => 30, 'aSort' => 20],
        ]);

        // EC000201: Schlagbohrmaschine
        $this->assignAttributes($ecSchlagbohr, [
            ['attr' => $efGewicht,     'collection' => 'Technische Daten',  'cSort' => 10, 'aSort' => 10],
            ['attr' => $efSpannung,    'collection' => 'Technische Daten',  'cSort' => 10, 'aSort' => 20],
            ['attr' => $efSchutzart,   'collection' => 'Sicherheit',        'cSort' => 20, 'aSort' => 10],
            ['attr' => $efAkkubetrieb, 'collection' => 'Energieversorgung', 'cSort' => 30, 'aSort' => 10],
        ]);

        // EC000210: Winkelschleifer
        $this->assignAttributes($ecWinkelschl, [
            ['attr' => $efGewicht,   'collection' => 'Technische Daten', 'cSort' => 10, 'aSort' => 10],
            ['attr' => $efSpannung,  'collection' => 'Technische Daten', 'cSort' => 10, 'aSort' => 20],
            ['attr' => $efSchutzart, 'collection' => 'Sicherheit',       'cSort' => 20, 'aSort' => 10],
        ]);

        // EC000220: Stichsäge
        $this->assignAttributes($ecStichsaege, [
            ['attr' => $efGewicht,     'collection' => 'Technische Daten',  'cSort' => 10, 'aSort' => 10],
            ['attr' => $efSpannung,    'collection' => 'Technische Daten',  'cSort' => 10, 'aSort' => 20],
            ['attr' => $efAkkubetrieb, 'collection' => 'Energieversorgung', 'cSort' => 30, 'aSort' => 10],
        ]);

        // ─── 7. Attribut-Mappings: Master → ETIM ────────────────

        $master = Hierarchy::where('technical_name', 'master_elektrowerkzeuge')->first();

        if (!$master) {
            $this->command->warn('Master-Hierarchie nicht gefunden — Mappings übersprungen.');
            return;
        }

        $masterAttrs = Attribute::whereIn('technical_name', [
            'product-weight-num', 'product-length-num', 'product-voltage-num',
        ])->get()->keyBy('technical_name');

        $mappings = [
            // Gewicht → ETIM Gewicht (1:1 direkt, gleiche Einheit)
            [
                'source' => $masterAttrs['product-weight-num'] ?? null,
                'target' => $efGewicht,
                'type' => 'direct',
                'config' => null,
                'confidence' => 0.95,
            ],
            // Länge → ETIM Länge (1:1 direkt)
            [
                'source' => $masterAttrs['product-length-num'] ?? null,
                'target' => $efLaenge,
                'type' => 'direct',
                'config' => null,
                'confidence' => 0.90,
            ],
            // Spannung → ETIM Nennspannung (1:1 direkt)
            [
                'source' => $masterAttrs['product-voltage-num'] ?? null,
                'target' => $efSpannung,
                'type' => 'direct',
                'config' => null,
                'confidence' => 0.92,
            ],
        ];

        foreach ($mappings as $m) {
            if ($m['source'] === null) {
                continue;
            }
            AttributeMapping::firstOrCreate(
                [
                    'source_hierarchy_id' => $master->id,
                    'source_attribute_id' => $m['source']->id,
                    'target_hierarchy_id' => $etim->id,
                    'target_attribute_id' => $m['target']->id,
                ],
                [
                    'transform_type' => $m['type'],
                    'transform_config' => $m['config'],
                    'ai_suggested' => true,
                    'ai_confidence' => $m['confidence'],
                ]
            );
        }

        // ─── 8. Produkte ETIM-Klassen zuordnen ────────────────

        $productAssignments = [
            // Akkubohrschrauber → EC000200 Bohrmaschine/Schrauber
            ['sku' => 'PD-18V-001', 'node' => $ecBohrmaschine, 'sort' => 10],
            ['sku' => 'PD-18V-002', 'node' => $ecBohrmaschine, 'sort' => 20],
            // Schlagbohrmaschinen → EC000201
            ['sku' => 'HD-18V-001', 'node' => $ecSchlagbohr, 'sort' => 10],
            ['sku' => 'HD-36V-001', 'node' => $ecSchlagbohr, 'sort' => 20],
        ];

        $assignmentCount = 0;
        foreach ($productAssignments as $pa) {
            $product = Product::where('sku', $pa['sku'])->first();
            if ($product && $pa['node']) {
                OutputHierarchyProductAssignment::firstOrCreate(
                    ['hierarchy_node_id' => $pa['node']->id, 'product_id' => $product->id],
                    ['sort_order' => $pa['sort']]
                );
                $assignmentCount++;
            }
        }

        // ─── 9. ETIM-Attributwerte (output_hierarchy_id-scoped) ───

        $evIP20 = ValueListEntry::where('technical_name', 'EV000073')->first();
        $evIP44 = ValueListEntry::where('technical_name', 'EV000074')->first();
        $evLiIon = ValueListEntry::where('technical_name', 'EV000201')->first();

        $etimValues = [
            // PD-18V-001: Akkubohrschrauber ProDrill 18V
            ['sku' => 'PD-18V-001', 'attr' => $efGewicht,     'number' => 1.800, 'unit' => $unitKg],
            ['sku' => 'PD-18V-001', 'attr' => $efSpannung,    'number' => 18.0,  'unit' => $unitV],
            ['sku' => 'PD-18V-001', 'attr' => $efSchutzart,   'selection' => $evIP20],
            ['sku' => 'PD-18V-001', 'attr' => $efAkkutyp,     'selection' => $evLiIon],
            ['sku' => 'PD-18V-001', 'attr' => $efAkkubetrieb, 'flag' => true],

            // PD-18V-002: CompactDrill 12V
            ['sku' => 'PD-18V-002', 'attr' => $efGewicht,     'number' => 1.100, 'unit' => $unitKg],
            ['sku' => 'PD-18V-002', 'attr' => $efSpannung,    'number' => 12.0,  'unit' => $unitV],
            ['sku' => 'PD-18V-002', 'attr' => $efAkkutyp,     'selection' => $evLiIon],
            ['sku' => 'PD-18V-002', 'attr' => $efAkkubetrieb, 'flag' => true],

            // HD-18V-001: Schlagbohrmaschine PowerHammer 18V
            ['sku' => 'HD-18V-001', 'attr' => $efGewicht,     'number' => 2.500, 'unit' => $unitKg],
            ['sku' => 'HD-18V-001', 'attr' => $efSpannung,    'number' => 18.0,  'unit' => $unitV],
            ['sku' => 'HD-18V-001', 'attr' => $efSchutzart,   'selection' => $evIP44],
            ['sku' => 'HD-18V-001', 'attr' => $efAkkubetrieb, 'flag' => true],

            // HD-36V-001: Bohrhammer ProForce 36V SDS+
            ['sku' => 'HD-36V-001', 'attr' => $efGewicht,     'number' => 3.800, 'unit' => $unitKg],
            ['sku' => 'HD-36V-001', 'attr' => $efSpannung,    'number' => 36.0,  'unit' => $unitV],
            ['sku' => 'HD-36V-001', 'attr' => $efSchutzart,   'selection' => $evIP44],
            ['sku' => 'HD-36V-001', 'attr' => $efAkkutyp,     'selection' => $evLiIon],
            ['sku' => 'HD-36V-001', 'attr' => $efAkkubetrieb, 'flag' => true],
        ];

        $valueCount = 0;
        foreach ($etimValues as $ev) {
            $product = Product::where('sku', $ev['sku'])->first();
            if (!$product || !$ev['attr']) {
                continue;
            }

            $data = [
                'product_id' => $product->id,
                'attribute_id' => $ev['attr']->id,
                'language' => null,
                'multiplied_index' => 0,
                'output_hierarchy_id' => $etim->id,
            ];

            $valueData = [];
            if (isset($ev['number'])) {
                $valueData['value_number'] = $ev['number'];
                if (isset($ev['unit'])) {
                    $valueData['unit_id'] = $ev['unit']->id;
                }
            }
            if (isset($ev['selection']) && $ev['selection']) {
                $valueData['value_selection_id'] = $ev['selection']->id;
                $valueData['value_string'] = $ev['selection']->technical_name;
            }
            if (isset($ev['flag'])) {
                $valueData['value_flag'] = $ev['flag'];
            }

            ProductAttributeValue::firstOrCreate($data, $valueData);
            $valueCount++;
        }

        $this->command->info('ETIM Demo-Daten erstellt:');
        $this->command->info('  - 1 ETIM Output-Hierarchie mit 4 Klassen');
        $this->command->info('  - 6 ETIM-Features (EF-Codes)');
        $this->command->info('  - 2 ETIM-Wertlisten mit ' . (count($schutzartEntries) + count($akkutypEntries)) . ' Einträgen');
        $this->command->info('  - ' . count($mappings) . ' Attribut-Mappings (Master → ETIM)');
        $this->command->info('  - ' . $assignmentCount . ' Produkt-Zuordnungen zu ETIM-Klassen');
        $this->command->info('  - ' . $valueCount . ' ETIM-Attributwerte (output_hierarchy_id-scoped)');
    }

    private function createNode(Hierarchy $hierarchy, HierarchyNode $parent, string $nameDe, string $nameEn, int $sort): HierarchyNode
    {
        $node = HierarchyNode::firstOrCreate(
            ['hierarchy_id' => $hierarchy->id, 'name_de' => $nameDe, 'parent_node_id' => $parent->id],
            [
                'name_en' => $nameEn,
                'path' => '/',
                'depth' => $parent->depth + 1,
                'sort_order' => $sort,
            ]
        );

        $correctPath = "{$parent->path}{$node->id}/";
        if ($node->path !== $correctPath) {
            $node->update(['path' => $correctPath]);
        }

        return $node;
    }

    private function assignAttributes(HierarchyNode $node, array $assignments): void
    {
        foreach ($assignments as $a) {
            if ($a['attr'] === null) {
                continue;
            }
            HierarchyNodeAttributeAssignment::firstOrCreate(
                ['hierarchy_node_id' => $node->id, 'attribute_id' => $a['attr']->id],
                [
                    'collection_name' => $a['collection'],
                    'collection_sort' => $a['cSort'],
                    'attribute_sort' => $a['aSort'],
                ]
            );
        }
    }
}
