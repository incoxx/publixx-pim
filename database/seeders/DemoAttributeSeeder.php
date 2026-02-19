<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\AttributeView;
use App\Models\AttributeViewAssignment;
use App\Models\ComparisonOperator;
use App\Models\ComparisonOperatorGroup;
use App\Models\PriceType;
use App\Models\ProductRelationType;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use Illuminate\Database\Seeder;

class DemoAttributeSeeder extends Seeder
{
    public function run(): void
    {
        // ----- Attribute Types (Groups) -----
        $atTechnical = AttributeType::firstOrCreate(
            ['technical_name' => 'technical_attributes'],
            ['name_de' => 'Technische Attribute', 'name_en' => 'Technical Attributes', 'sort_order' => 10]
        );
        $atMarketing = AttributeType::firstOrCreate(
            ['technical_name' => 'marketing_attributes'],
            ['name_de' => 'Marketing-Attribute', 'name_en' => 'Marketing Attributes', 'sort_order' => 20]
        );
        $atLogistics = AttributeType::firstOrCreate(
            ['technical_name' => 'logistics_attributes'],
            ['name_de' => 'Logistik-Attribute', 'name_en' => 'Logistics Attributes', 'sort_order' => 30]
        );

        // ----- Unit Groups & Units -----
        $ugWeight = UnitGroup::firstOrCreate(
            ['technical_name' => 'weight'],
            ['name_de' => 'Gewicht', 'name_en' => 'Weight']
        );
        $unitKg = Unit::firstOrCreate(['unit_group_id' => $ugWeight->id, 'technical_name' => 'kilogram'], [
            'abbreviation' => 'kg', 'conversion_factor' => 1, 'is_base_unit' => true, 'is_translatable' => false,
        ]);
        Unit::firstOrCreate(['unit_group_id' => $ugWeight->id, 'technical_name' => 'gram'], [
            'abbreviation' => 'g', 'conversion_factor' => 0.001, 'is_base_unit' => false, 'is_translatable' => false,
        ]);

        $ugLength = UnitGroup::firstOrCreate(
            ['technical_name' => 'length'],
            ['name_de' => 'Länge', 'name_en' => 'Length']
        );
        $unitMm = Unit::firstOrCreate(['unit_group_id' => $ugLength->id, 'technical_name' => 'millimeter'], [
            'abbreviation' => 'mm', 'conversion_factor' => 1, 'is_base_unit' => true, 'is_translatable' => false,
        ]);
        Unit::firstOrCreate(['unit_group_id' => $ugLength->id, 'technical_name' => 'centimeter'], [
            'abbreviation' => 'cm', 'conversion_factor' => 10, 'is_base_unit' => false, 'is_translatable' => false,
        ]);
        Unit::firstOrCreate(['unit_group_id' => $ugLength->id, 'technical_name' => 'meter'], [
            'abbreviation' => 'm', 'conversion_factor' => 1000, 'is_base_unit' => false, 'is_translatable' => false,
        ]);

        $ugVoltage = UnitGroup::firstOrCreate(
            ['technical_name' => 'voltage'],
            ['name_de' => 'Spannung', 'name_en' => 'Voltage']
        );
        $unitV = Unit::firstOrCreate(['unit_group_id' => $ugVoltage->id, 'technical_name' => 'volt'], [
            'abbreviation' => 'V', 'conversion_factor' => 1, 'is_base_unit' => true, 'is_translatable' => false,
        ]);

        $ugPiece = UnitGroup::firstOrCreate(
            ['technical_name' => 'piece'],
            ['name_de' => 'Stück', 'name_en' => 'Piece']
        );
        Unit::firstOrCreate(['unit_group_id' => $ugPiece->id, 'technical_name' => 'piece'], [
            'abbreviation' => 'Stk.',
            'abbreviation_json' => ['en' => 'pcs.', 'fr' => 'pce.'],
            'conversion_factor' => 1,
            'is_base_unit' => true,
            'is_translatable' => true,
        ]);

        // ----- Comparison Operator Groups -----
        $copNumeric = ComparisonOperatorGroup::firstOrCreate(
            ['technical_name' => 'numeric_comparison'],
            ['name_de' => 'Numerischer Vergleich', 'name_en' => 'Numeric Comparison']
        );
        foreach ([
            ['technical_name' => 'eq', 'symbol' => '=', 'description_de' => 'gleich'],
            ['technical_name' => 'lt', 'symbol' => '<', 'description_de' => 'kleiner als'],
            ['technical_name' => 'gt', 'symbol' => '>', 'description_de' => 'größer als'],
            ['technical_name' => 'lte', 'symbol' => '≤', 'description_de' => 'kleiner gleich'],
            ['technical_name' => 'gte', 'symbol' => '≥', 'description_de' => 'größer gleich'],
            ['technical_name' => 'approx', 'symbol' => '≈', 'description_de' => 'ungefähr'],
        ] as $op) {
            ComparisonOperator::firstOrCreate(
                ['group_id' => $copNumeric->id, 'technical_name' => $op['technical_name']],
                $op
            );
        }

        // ----- Value Lists -----
        $vlColors = ValueList::firstOrCreate(
            ['technical_name' => 'colors'],
            ['name_de' => 'Farben', 'name_en' => 'Colors', 'value_data_type' => 'String', 'max_depth' => 1]
        );
        foreach ([
            ['technical_name' => 'red', 'display_value_de' => 'Rot', 'display_value_en' => 'Red', 'sort_order' => 10],
            ['technical_name' => 'blue', 'display_value_de' => 'Blau', 'display_value_en' => 'Blue', 'sort_order' => 20],
            ['technical_name' => 'green', 'display_value_de' => 'Grün', 'display_value_en' => 'Green', 'sort_order' => 30],
            ['technical_name' => 'black', 'display_value_de' => 'Schwarz', 'display_value_en' => 'Black', 'sort_order' => 40],
            ['technical_name' => 'yellow', 'display_value_de' => 'Gelb', 'display_value_en' => 'Yellow', 'sort_order' => 50],
        ] as $entry) {
            ValueListEntry::firstOrCreate(
                ['value_list_id' => $vlColors->id, 'technical_name' => $entry['technical_name']],
                $entry
            );
        }

        $vlMaterials = ValueList::firstOrCreate(
            ['technical_name' => 'materials'],
            ['name_de' => 'Materialien', 'name_en' => 'Materials', 'value_data_type' => 'String', 'max_depth' => 1]
        );
        foreach ([
            ['technical_name' => 'metal', 'display_value_de' => 'Metall', 'display_value_en' => 'Metal', 'sort_order' => 10],
            ['technical_name' => 'plastic', 'display_value_de' => 'Kunststoff', 'display_value_en' => 'Plastic', 'sort_order' => 20],
            ['technical_name' => 'rubber', 'display_value_de' => 'Gummi', 'display_value_en' => 'Rubber', 'sort_order' => 30],
        ] as $entry) {
            ValueListEntry::firstOrCreate(
                ['value_list_id' => $vlMaterials->id, 'technical_name' => $entry['technical_name']],
                $entry
            );
        }

        // ----- Attribute Views -----
        $viewAll = AttributeView::firstOrCreate(
            ['technical_name' => 'all_attributes'],
            ['name_de' => 'Alle Attribute', 'name_en' => 'All Attributes', 'sort_order' => 0]
        );
        $viewEshop = AttributeView::firstOrCreate(
            ['technical_name' => 'eshop_view'],
            ['name_de' => 'E-Shop Ansicht', 'name_en' => 'E-Shop View', 'sort_order' => 10]
        );
        $viewPrint = AttributeView::firstOrCreate(
            ['technical_name' => 'print_view'],
            ['name_de' => 'Print / Katalog', 'name_en' => 'Print / Catalog', 'sort_order' => 20]
        );

        // ----- Attributes -----
        $attrs = [
            [
                'technical_name' => 'product-name-str',
                'name_de' => 'Produktname',
                'name_en' => 'Product Name',
                'data_type' => 'String',
                'attribute_type_id' => $atMarketing->id,
                'is_translatable' => true,
                'is_searchable' => true,
                'is_mandatory' => true,
                'max_characters' => 500,
                'position' => 10,
            ],
            [
                'technical_name' => 'product-description-str',
                'name_de' => 'Produktbeschreibung',
                'name_en' => 'Product Description',
                'data_type' => 'String',
                'attribute_type_id' => $atMarketing->id,
                'is_translatable' => true,
                'is_searchable' => true,
                'max_characters' => 5000,
                'position' => 20,
            ],
            [
                'technical_name' => 'product-weight-num',
                'name_de' => 'Gewicht',
                'name_en' => 'Weight',
                'data_type' => 'Float',
                'attribute_type_id' => $atTechnical->id,
                'unit_group_id' => $ugWeight->id,
                'default_unit_id' => $unitKg->id,
                'comparison_operator_group_id' => $copNumeric->id,
                'max_pre_decimal' => 6,
                'max_post_decimal' => 3,
                'position' => 30,
            ],
            [
                'technical_name' => 'product-length-num',
                'name_de' => 'Länge',
                'name_en' => 'Length',
                'data_type' => 'Float',
                'attribute_type_id' => $atTechnical->id,
                'unit_group_id' => $ugLength->id,
                'default_unit_id' => $unitMm->id,
                'max_pre_decimal' => 6,
                'max_post_decimal' => 2,
                'position' => 40,
            ],
            [
                'technical_name' => 'product-voltage-num',
                'name_de' => 'Spannung',
                'name_en' => 'Voltage',
                'data_type' => 'Float',
                'attribute_type_id' => $atTechnical->id,
                'unit_group_id' => $ugVoltage->id,
                'default_unit_id' => $unitV->id,
                'max_pre_decimal' => 4,
                'max_post_decimal' => 1,
                'position' => 50,
            ],
            [
                'technical_name' => 'product-color-sel',
                'name_de' => 'Farbe',
                'name_en' => 'Color',
                'data_type' => 'Selection',
                'attribute_type_id' => $atMarketing->id,
                'value_list_id' => $vlColors->id,
                'position' => 60,
            ],
            [
                'technical_name' => 'product-material-sel',
                'name_de' => 'Material',
                'name_en' => 'Material',
                'data_type' => 'Selection',
                'attribute_type_id' => $atTechnical->id,
                'value_list_id' => $vlMaterials->id,
                'position' => 70,
            ],
            [
                'technical_name' => 'product-norm-str',
                'name_de' => 'Norm / Standard',
                'name_en' => 'Norm / Standard',
                'data_type' => 'String',
                'attribute_type_id' => $atTechnical->id,
                'is_translatable' => false,
                'max_characters' => 100,
                'position' => 80,
            ],
            [
                'technical_name' => 'product-release-date',
                'name_de' => 'Erscheinungsdatum',
                'name_en' => 'Release Date',
                'data_type' => 'Date',
                'attribute_type_id' => $atMarketing->id,
                'position' => 90,
            ],
            [
                'technical_name' => 'product-is-new-flag',
                'name_de' => 'Neuheit',
                'name_en' => 'Is New',
                'data_type' => 'Flag',
                'attribute_type_id' => $atMarketing->id,
                'position' => 100,
            ],
            [
                'technical_name' => 'product-sku-source-str',
                'name_de' => 'SKU Quellsystem',
                'name_en' => 'SKU Source System',
                'data_type' => 'String',
                'attribute_type_id' => $atLogistics->id,
                'source_system' => 'SAP ERP',
                'source_attribute_key' => 'MATNR',
                'position' => 110,
            ],
            [
                'technical_name' => 'product-customs-tariff-str',
                'name_de' => 'Zolltarifnummer',
                'name_en' => 'Customs Tariff Number',
                'data_type' => 'String',
                'attribute_type_id' => $atLogistics->id,
                'max_characters' => 20,
                'position' => 120,
            ],
        ];

        $createdAttrs = [];
        foreach ($attrs as $attrData) {
            $createdAttrs[] = Attribute::firstOrCreate(
                ['technical_name' => $attrData['technical_name']],
                $attrData
            );
        }

        // Assign all attributes to "All Attributes" view
        foreach ($createdAttrs as $attr) {
            AttributeViewAssignment::firstOrCreate([
                'attribute_id' => $attr->id,
                'attribute_view_id' => $viewAll->id,
            ]);
        }

        // Assign marketing + selection attrs to E-Shop view
        $eshopAttrNames = [
            'product-name-str', 'product-description-str',
            'product-color-sel', 'product-is-new-flag', 'product-release-date',
        ];
        foreach ($createdAttrs as $attr) {
            if (in_array($attr->technical_name, $eshopAttrNames)) {
                AttributeViewAssignment::firstOrCreate([
                    'attribute_id' => $attr->id,
                    'attribute_view_id' => $viewEshop->id,
                ]);
            }
        }

        // ----- Price Types -----
        foreach ([
            ['technical_name' => 'list_price', 'name_de' => 'Listenpreis', 'name_en' => 'List Price'],
            ['technical_name' => 'purchase_price', 'name_de' => 'Einkaufspreis', 'name_en' => 'Purchase Price'],
            ['technical_name' => 'sale_price', 'name_de' => 'Aktionspreis', 'name_en' => 'Sale Price'],
        ] as $pt) {
            PriceType::firstOrCreate(['technical_name' => $pt['technical_name']], $pt);
        }

        // ----- Product Relation Types -----
        foreach ([
            ['technical_name' => 'accessory', 'name_de' => 'Zubehör', 'name_en' => 'Accessory', 'is_bidirectional' => false],
            ['technical_name' => 'cross_sell', 'name_de' => 'Cross-Selling', 'name_en' => 'Cross-Sell', 'is_bidirectional' => true],
            ['technical_name' => 'successor', 'name_de' => 'Nachfolger', 'name_en' => 'Successor', 'is_bidirectional' => false],
        ] as $rt) {
            ProductRelationType::firstOrCreate(['technical_name' => $rt['technical_name']], $rt);
        }
    }
}
