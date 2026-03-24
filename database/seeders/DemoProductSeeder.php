<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\ComparisonOperator;
use App\Models\HierarchyNode;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\ProductRelation;
use App\Models\ProductRelationType;
use App\Models\ProductSearchIndex;
use App\Models\ProductType;
use App\Models\Unit;
use App\Models\User;
use App\Models\ValueListEntry;
use Illuminate\Database\Seeder;

class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        $physicalType = ProductType::where('technical_name', 'physical_product')->first();
        $admin = User::where('email', 'admin@publixx.com')->first();
        $akkubohrer = HierarchyNode::where('name_de', 'Akkubohrschrauber')->first();
        $schlagbohrer = HierarchyNode::where('name_de', 'Schlagbohrmaschinen')->first();

        $attrs = Attribute::all()->keyBy('technical_name');
        $colorRed = ValueListEntry::where('technical_name', 'red')->first();
        $colorBlue = ValueListEntry::where('technical_name', 'blue')->first();
        $materialMetal = ValueListEntry::where('technical_name', 'metal')->first();
        $materialPlastic = ValueListEntry::where('technical_name', 'plastic')->first();

        // Preistypen
        $listPrice = PriceType::where('technical_name', 'list_price')->first();
        $purchasePrice = PriceType::where('technical_name', 'purchase_price')->first();
        $salePrice = PriceType::where('technical_name', 'sale_price')->first();

        // Vergleichsoperatoren
        $opApprox = ComparisonOperator::where('technical_name', 'approx')->first();
        $opEq = ComparisonOperator::where('technical_name', 'eq')->first();

        // Einheiten für Composites
        $unitMm = Unit::where('technical_name', 'millimeter')->first();

        if (! $physicalType || ! $admin) {
            return;
        }

        $products = [
            [
                'sku' => 'PD-18V-001',
                'ean' => '4012345678901',
                'name' => 'Akkubohrschrauber ProDrill 18V',
                'status' => 'active',
                'node' => $akkubohrer,
                'values' => [
                    ['attr' => 'product-name-str', 'lang' => 'de', 'string' => 'Akkubohrschrauber ProDrill 18V'],
                    ['attr' => 'product-name-str', 'lang' => 'en', 'string' => 'Cordless Drill ProDrill 18V'],
                    ['attr' => 'product-description-str', 'lang' => 'de', 'string' => 'Leistungsstarker 18V Akkubohrschrauber mit bürstenlosem Motor für professionelle Anwendungen. Kompaktes Design, hohe Drehmomentleistung und lange Akkulaufzeit.'],
                    ['attr' => 'product-description-str', 'lang' => 'en', 'string' => 'Powerful 18V cordless drill with brushless motor for professional applications. Compact design, high torque output, and long battery life.'],
                    ['attr' => 'product-weight-num', 'number' => 1.800, 'cop' => 'approx'],
                    ['attr' => 'product-voltage-num', 'number' => 18.0, 'cop' => 'eq'],
                    ['attr' => 'product-color-sel', 'selection' => $colorBlue],
                    ['attr' => 'product-material-sel', 'selection' => $materialPlastic],
                    ['attr' => 'product-norm-str', 'string' => 'DIN EN 60745-1'],
                    ['attr' => 'product-is-new-flag', 'flag' => true],
                ],
                'prices' => ['list' => 189.99, 'purchase' => 95.00, 'sale' => 169.99],
                'dimensions' => [210.0, 75.0, 230.0],
                'lieferumfang' => [
                    ['de' => 'Akkubohrschrauber', 'en' => 'Cordless drill driver', 'qty' => 1],
                    ['de' => 'Akku 18V 4.0Ah', 'en' => 'Battery 18V 4.0Ah', 'qty' => 2],
                    ['de' => 'Ladegerät', 'en' => 'Charger', 'qty' => 1],
                    ['de' => 'Transportkoffer', 'en' => 'Carrying case', 'qty' => 1],
                ],
                'zertifizierungen' => [
                    ['name' => 'DIN EN 60745-1', 'nr' => 'DE-2024-0815', 'von' => '2024-01-15', 'bis' => '2027-01-14'],
                    ['name' => 'CE-Konformität', 'nr' => 'CE-EU-2024-1234', 'von' => '2024-03-01', 'bis' => '2029-02-28'],
                ],
            ],
            [
                'sku' => 'PD-18V-002',
                'ean' => '4012345678902',
                'name' => 'Akkubohrschrauber CompactDrill 12V',
                'status' => 'active',
                'node' => $akkubohrer,
                'values' => [
                    ['attr' => 'product-name-str', 'lang' => 'de', 'string' => 'Akkubohrschrauber CompactDrill 12V'],
                    ['attr' => 'product-name-str', 'lang' => 'en', 'string' => 'Cordless Drill CompactDrill 12V'],
                    ['attr' => 'product-description-str', 'lang' => 'de', 'string' => 'Kompakter 12V Akkubohrschrauber für Heimwerker. Ideal für leichte Bohr- und Schraubarbeiten in Holz und Kunststoff.'],
                    ['attr' => 'product-description-str', 'lang' => 'en', 'string' => 'Compact 12V cordless drill for DIY enthusiasts. Ideal for light drilling and screwing in wood and plastic.'],
                    ['attr' => 'product-weight-num', 'number' => 1.100, 'cop' => 'approx'],
                    ['attr' => 'product-voltage-num', 'number' => 12.0, 'cop' => 'eq'],
                    ['attr' => 'product-color-sel', 'selection' => $colorRed],
                    ['attr' => 'product-is-new-flag', 'flag' => false],
                ],
                'prices' => ['list' => 99.99, 'purchase' => 52.00],
                'dimensions' => [185.0, 65.0, 195.0],
                'lieferumfang' => [
                    ['de' => 'Akkubohrschrauber', 'en' => 'Cordless drill driver', 'qty' => 1],
                    ['de' => 'Akku 12V 2.0Ah', 'en' => 'Battery 12V 2.0Ah', 'qty' => 1],
                    ['de' => 'Ladegerät', 'en' => 'Charger', 'qty' => 1],
                ],
                'zertifizierungen' => [
                    ['name' => 'CE-Konformität', 'nr' => 'CE-EU-2024-2345', 'von' => '2024-05-01', 'bis' => '2029-04-30'],
                ],
            ],
            [
                'sku' => 'HD-18V-001',
                'ean' => '4012345678903',
                'name' => 'Schlagbohrmaschine PowerHammer 18V',
                'status' => 'active',
                'node' => $schlagbohrer,
                'values' => [
                    ['attr' => 'product-name-str', 'lang' => 'de', 'string' => 'Schlagbohrmaschine PowerHammer 18V'],
                    ['attr' => 'product-name-str', 'lang' => 'en', 'string' => 'Hammer Drill PowerHammer 18V'],
                    ['attr' => 'product-description-str', 'lang' => 'de', 'string' => 'Robuste 18V Schlagbohrmaschine mit Zweigang-Getriebe. Für Beton, Mauerwerk und Stahl geeignet.'],
                    ['attr' => 'product-description-str', 'lang' => 'en', 'string' => 'Robust 18V hammer drill with two-speed gearbox. Suitable for concrete, masonry, and steel.'],
                    ['attr' => 'product-weight-num', 'number' => 2.500, 'cop' => 'approx'],
                    ['attr' => 'product-voltage-num', 'number' => 18.0, 'cop' => 'eq'],
                    ['attr' => 'product-color-sel', 'selection' => $colorBlue],
                    ['attr' => 'product-material-sel', 'selection' => $materialMetal],
                    ['attr' => 'product-norm-str', 'string' => 'DIN EN 60745-2-6'],
                ],
                'prices' => ['list' => 279.99, 'purchase' => 140.00, 'sale' => 249.99],
                'dimensions' => [250.0, 85.0, 280.0],
                'lieferumfang' => [
                    ['de' => 'Schlagbohrmaschine', 'en' => 'Hammer drill', 'qty' => 1],
                    ['de' => 'Akku 18V 5.0Ah', 'en' => 'Battery 18V 5.0Ah', 'qty' => 2],
                    ['de' => 'Schnellladegerät', 'en' => 'Fast charger', 'qty' => 1],
                    ['de' => 'Tiefenanschlag', 'en' => 'Depth stop', 'qty' => 1],
                    ['de' => 'Systemkoffer', 'en' => 'System case', 'qty' => 1],
                ],
                'zertifizierungen' => [
                    ['name' => 'DIN EN 60745-2-6', 'nr' => 'DE-2024-0901', 'von' => '2024-06-01', 'bis' => '2027-05-31'],
                ],
            ],
            [
                'sku' => 'PD-18V-003',
                'ean' => '4012345678904',
                'name' => 'Akkuschrauber MiniDrive 3.6V',
                'status' => 'draft',
                'node' => $akkubohrer,
                'values' => [
                    ['attr' => 'product-name-str', 'lang' => 'de', 'string' => 'Akkuschrauber MiniDrive 3.6V'],
                    ['attr' => 'product-name-str', 'lang' => 'en', 'string' => 'Cordless Screwdriver MiniDrive 3.6V'],
                    ['attr' => 'product-description-str', 'lang' => 'de', 'string' => 'Handlicher 3.6V Akkuschrauber mit integriertem Akku und USB-Ladefunktion. Perfekt für Möbelmontage und Kleinreparaturen.'],
                    ['attr' => 'product-description-str', 'lang' => 'en', 'string' => 'Handy 3.6V cordless screwdriver with built-in battery and USB charging. Perfect for furniture assembly and small repairs.'],
                    ['attr' => 'product-weight-num', 'number' => 0.350],
                    ['attr' => 'product-voltage-num', 'number' => 3.6, 'cop' => 'eq'],
                    ['attr' => 'product-color-sel', 'selection' => $colorRed],
                ],
                'prices' => ['list' => 49.99, 'purchase' => 28.00],
                'dimensions' => [160.0, 45.0, 130.0],
                'lieferumfang' => [
                    ['de' => 'Akkuschrauber', 'en' => 'Cordless screwdriver', 'qty' => 1],
                    ['de' => 'USB-Ladekabel', 'en' => 'USB charging cable', 'qty' => 1],
                ],
                'zertifizierungen' => [],
            ],
            [
                'sku' => 'HD-36V-001',
                'ean' => '4012345678905',
                'name' => 'Bohrhammer ProForce 36V SDS+',
                'status' => 'active',
                'node' => $schlagbohrer,
                'values' => [
                    ['attr' => 'product-name-str', 'lang' => 'de', 'string' => 'Bohrhammer ProForce 36V SDS+'],
                    ['attr' => 'product-name-str', 'lang' => 'en', 'string' => 'Rotary Hammer ProForce 36V SDS+'],
                    ['attr' => 'product-description-str', 'lang' => 'de', 'string' => 'Professioneller 36V Bohrhammer mit SDS+ Aufnahme und 5.0 Joule Schlagenergie. Vibrationsarm dank Anti-Vibrations-System.'],
                    ['attr' => 'product-description-str', 'lang' => 'en', 'string' => 'Professional 36V rotary hammer with SDS+ chuck and 5.0 Joule impact energy. Low vibration thanks to anti-vibration system.'],
                    ['attr' => 'product-weight-num', 'number' => 3.800, 'cop' => 'approx'],
                    ['attr' => 'product-voltage-num', 'number' => 36.0, 'cop' => 'eq'],
                    ['attr' => 'product-material-sel', 'selection' => $materialMetal],
                    ['attr' => 'product-norm-str', 'string' => 'DIN EN 62841-2-6'],
                ],
                'prices' => ['list' => 449.99, 'purchase' => 225.00],
                'dimensions' => [340.0, 110.0, 300.0],
                'lieferumfang' => [
                    ['de' => 'Bohrhammer', 'en' => 'Rotary hammer', 'qty' => 1],
                    ['de' => 'Akku 36V 4.0Ah', 'en' => 'Battery 36V 4.0Ah', 'qty' => 2],
                    ['de' => 'Schnellladegerät', 'en' => 'Fast charger', 'qty' => 1],
                    ['de' => 'SDS+ Bohrerset (6/8/10 mm)', 'en' => 'SDS+ drill bit set (6/8/10 mm)', 'qty' => 1],
                    ['de' => 'Systemkoffer L-BOXX', 'en' => 'System case L-BOXX', 'qty' => 1],
                ],
                'zertifizierungen' => [
                    ['name' => 'DIN EN 62841-2-6', 'nr' => 'DE-2025-0102', 'von' => '2025-01-01', 'bis' => '2028-12-31'],
                    ['name' => 'GS-Zeichen', 'nr' => 'GS-2025-5678', 'von' => '2025-02-01', 'bis' => '2030-01-31'],
                ],
            ],
        ];

        $createdProducts = [];

        foreach ($products as $pData) {
            $product = Product::firstOrCreate(
                ['sku' => $pData['sku']],
                [
                    'product_type_id' => $physicalType->id,
                    'ean' => $pData['ean'],
                    'name' => $pData['name'],
                    'status' => $pData['status'],
                    'master_hierarchy_node_id' => $pData['node']?->id,
                    'created_by' => $admin->id,
                ]
            );

            $createdProducts[$pData['sku']] = $product;

            // Attributwerte
            foreach ($pData['values'] as $v) {
                $attr = $attrs[$v['attr']] ?? null;
                if (! $attr) {
                    continue;
                }

                $data = [
                    'product_id' => $product->id,
                    'attribute_id' => $attr->id,
                    'language' => $v['lang'] ?? null,
                    'multiplied_index' => 0,
                ];

                $valueData = [];
                if (isset($v['string'])) {
                    $valueData['value_string'] = $v['string'];
                }
                if (isset($v['number'])) {
                    $valueData['value_number'] = $v['number'];
                    if ($attr->default_unit_id) {
                        $valueData['unit_id'] = $attr->default_unit_id;
                    }
                    // Vergleichsoperator
                    if (isset($v['cop'])) {
                        $cop = $v['cop'] === 'approx' ? $opApprox : ($v['cop'] === 'eq' ? $opEq : null);
                        if ($cop) {
                            $valueData['comparison_operator_id'] = $cop->id;
                        }
                    }
                }
                if (isset($v['flag'])) {
                    $valueData['value_flag'] = $v['flag'];
                }
                if (isset($v['selection']) && $v['selection']) {
                    $valueData['value_selection_id'] = $v['selection']->id;
                    $valueData['value_string'] = $v['selection']->technical_name;
                }

                ProductAttributeValue::firstOrCreate($data, $valueData);
            }

            // ----- Preise -----
            $priceData = $pData['prices'];
            if ($listPrice && isset($priceData['list'])) {
                ProductPrice::firstOrCreate(
                    ['product_id' => $product->id, 'price_type_id' => $listPrice->id, 'currency' => 'EUR'],
                    ['amount' => $priceData['list'], 'valid_from' => now()->toDateString()]
                );
            }
            if ($purchasePrice && isset($priceData['purchase'])) {
                ProductPrice::firstOrCreate(
                    ['product_id' => $product->id, 'price_type_id' => $purchasePrice->id, 'currency' => 'EUR'],
                    ['amount' => $priceData['purchase'], 'valid_from' => now()->toDateString()]
                );
            }
            if ($salePrice && isset($priceData['sale'])) {
                ProductPrice::firstOrCreate(
                    ['product_id' => $product->id, 'price_type_id' => $salePrice->id, 'currency' => 'EUR'],
                    ['amount' => $priceData['sale'], 'valid_from' => now()->toDateString(), 'valid_to' => now()->addMonths(3)->toDateString()]
                );
            }

            // ----- Abmessungen (einfaches Composite) -----
            if (!empty($pData['dimensions']) && $unitMm) {
                $dimAttrs = ['dim-breite', 'dim-hoehe', 'dim-tiefe'];
                foreach ($dimAttrs as $i => $dimName) {
                    $dimAttr = $attrs[$dimName] ?? null;
                    if ($dimAttr && isset($pData['dimensions'][$i])) {
                        ProductAttributeValue::firstOrCreate(
                            ['product_id' => $product->id, 'attribute_id' => $dimAttr->id, 'language' => null, 'multiplied_index' => 0],
                            ['value_number' => $pData['dimensions'][$i], 'unit_id' => $unitMm->id]
                        );
                    }
                }
            }

            // ----- Lieferumfang (vermehrbares Composite) -----
            $lieferBez = $attrs['lieferumfang-bezeichnung'] ?? null;
            $lieferMenge = $attrs['lieferumfang-menge'] ?? null;
            if ($lieferBez && $lieferMenge) {
                foreach ($pData['lieferumfang'] as $idx => $item) {
                    // Bezeichnung DE
                    ProductAttributeValue::firstOrCreate(
                        ['product_id' => $product->id, 'attribute_id' => $lieferBez->id, 'language' => 'de', 'multiplied_index' => $idx],
                        ['value_string' => $item['de']]
                    );
                    // Bezeichnung EN
                    ProductAttributeValue::firstOrCreate(
                        ['product_id' => $product->id, 'attribute_id' => $lieferBez->id, 'language' => 'en', 'multiplied_index' => $idx],
                        ['value_string' => $item['en']]
                    );
                    // Menge
                    ProductAttributeValue::firstOrCreate(
                        ['product_id' => $product->id, 'attribute_id' => $lieferMenge->id, 'language' => null, 'multiplied_index' => $idx],
                        ['value_number' => $item['qty']]
                    );
                }
            }

            // ----- Zertifizierungen (geschachteltes vermehrbares Composite) -----
            $zertName = $attrs['zert-name'] ?? null;
            $zertNr = $attrs['zert-nummer'] ?? null;
            $zertVon = $attrs['zert-gueltig-von'] ?? null;
            $zertBis = $attrs['zert-gueltig-bis'] ?? null;
            if ($zertName && $zertNr && $zertVon && $zertBis) {
                foreach ($pData['zertifizierungen'] as $idx => $cert) {
                    ProductAttributeValue::firstOrCreate(
                        ['product_id' => $product->id, 'attribute_id' => $zertName->id, 'language' => null, 'multiplied_index' => $idx],
                        ['value_string' => $cert['name']]
                    );
                    ProductAttributeValue::firstOrCreate(
                        ['product_id' => $product->id, 'attribute_id' => $zertNr->id, 'language' => null, 'multiplied_index' => $idx],
                        ['value_string' => $cert['nr']]
                    );
                    // Sub-Composite: Gültigkeitsdaten
                    ProductAttributeValue::firstOrCreate(
                        ['product_id' => $product->id, 'attribute_id' => $zertVon->id, 'language' => null, 'multiplied_index' => $idx],
                        ['value_date' => $cert['von']]
                    );
                    ProductAttributeValue::firstOrCreate(
                        ['product_id' => $product->id, 'attribute_id' => $zertBis->id, 'language' => null, 'multiplied_index' => $idx],
                        ['value_date' => $cert['bis']]
                    );
                }
            }

            // Search index
            ProductSearchIndex::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'sku' => $product->sku,
                    'ean' => $product->ean,
                    'product_type' => $physicalType->technical_name,
                    'status' => $product->status,
                    'name_de' => $pData['name'],
                    'list_price' => $priceData['list'] ?? null,
                    'updated_at' => now(),
                ]
            );
        }

        // ----- Produktbeziehungen -----
        $accessory = ProductRelationType::where('technical_name', 'accessory')->first();
        $crossSell = ProductRelationType::where('technical_name', 'cross_sell')->first();
        $successor = ProductRelationType::where('technical_name', 'successor')->first();

        $relations = [
            // ProDrill ↔ CompactDrill: Cross-Selling (beide Akkubohrer)
            ['source' => 'PD-18V-001', 'target' => 'PD-18V-002', 'type' => $crossSell, 'sort' => 10],
            // ProDrill → PowerHammer: Zubehör (Upgrade-Empfehlung)
            ['source' => 'PD-18V-001', 'target' => 'HD-18V-001', 'type' => $accessory, 'sort' => 20],
            // ProForce 36V → PowerHammer 18V: Nachfolger
            ['source' => 'HD-36V-001', 'target' => 'HD-18V-001', 'type' => $successor, 'sort' => 10],
            // MiniDrive → ProDrill: Cross-Selling (Upgrade)
            ['source' => 'PD-18V-003', 'target' => 'PD-18V-001', 'type' => $crossSell, 'sort' => 10],
        ];

        foreach ($relations as $rel) {
            $source = $createdProducts[$rel['source']] ?? null;
            $target = $createdProducts[$rel['target']] ?? null;
            if ($source && $target && $rel['type']) {
                ProductRelation::firstOrCreate(
                    [
                        'source_product_id' => $source->id,
                        'target_product_id' => $target->id,
                        'relation_type_id' => $rel['type']->id,
                    ],
                    ['sort_order' => $rel['sort']]
                );
            }
        }
    }
}
