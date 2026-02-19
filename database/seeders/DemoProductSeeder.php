<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\ProductSearchIndex;
use App\Models\ProductType;
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
        $listPrice = PriceType::where('technical_name', 'list_price')->first();

        $attrs = Attribute::all()->keyBy('technical_name');
        $colorRed = ValueListEntry::where('technical_name', 'red')->first();
        $colorBlue = ValueListEntry::where('technical_name', 'blue')->first();
        $materialMetal = ValueListEntry::where('technical_name', 'metal')->first();
        $materialPlastic = ValueListEntry::where('technical_name', 'plastic')->first();

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
                    ['attr' => 'product-description-str', 'lang' => 'de', 'string' => 'Leistungsstarker 18V Akkubohrschrauber mit bürstenlosem Motor für professionelle Anwendungen.'],
                    ['attr' => 'product-description-str', 'lang' => 'en', 'string' => 'Powerful 18V cordless drill with brushless motor for professional applications.'],
                    ['attr' => 'product-weight-num', 'number' => 1.800],
                    ['attr' => 'product-voltage-num', 'number' => 18.0],
                    ['attr' => 'product-color-sel', 'selection' => $colorBlue],
                    ['attr' => 'product-material-sel', 'selection' => $materialPlastic],
                    ['attr' => 'product-norm-str', 'string' => 'DIN EN 60745-1'],
                    ['attr' => 'product-is-new-flag', 'flag' => true],
                ],
                'price' => 189.99,
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
                    ['attr' => 'product-weight-num', 'number' => 1.100],
                    ['attr' => 'product-voltage-num', 'number' => 12.0],
                    ['attr' => 'product-color-sel', 'selection' => $colorRed],
                    ['attr' => 'product-is-new-flag', 'flag' => false],
                ],
                'price' => 99.99,
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
                    ['attr' => 'product-weight-num', 'number' => 2.500],
                    ['attr' => 'product-voltage-num', 'number' => 18.0],
                    ['attr' => 'product-color-sel', 'selection' => $colorBlue],
                    ['attr' => 'product-material-sel', 'selection' => $materialMetal],
                    ['attr' => 'product-norm-str', 'string' => 'DIN EN 60745-2-6'],
                ],
                'price' => 279.99,
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
                    ['attr' => 'product-weight-num', 'number' => 0.350],
                    ['attr' => 'product-voltage-num', 'number' => 3.6],
                    ['attr' => 'product-color-sel', 'selection' => $colorRed],
                ],
                'price' => 49.99,
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
                    ['attr' => 'product-weight-num', 'number' => 3.800],
                    ['attr' => 'product-voltage-num', 'number' => 36.0],
                    ['attr' => 'product-material-sel', 'selection' => $materialMetal],
                    ['attr' => 'product-norm-str', 'string' => 'DIN EN 60745-2-6'],
                ],
                'price' => 449.99,
            ],
        ];

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

            // Attribute values
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

            // Price
            if ($listPrice && isset($pData['price'])) {
                ProductPrice::firstOrCreate(
                    ['product_id' => $product->id, 'price_type_id' => $listPrice->id, 'currency' => 'EUR'],
                    ['amount' => $pData['price'], 'valid_from' => now()->toDateString()]
                );
            }

            // Search index entry
            ProductSearchIndex::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'sku' => $product->sku,
                    'ean' => $product->ean,
                    'product_type' => $physicalType->technical_name,
                    'status' => $product->status,
                    'name_de' => $pData['name'],
                    'list_price' => $pData['price'] ?? null,
                    'updated_at' => now(),
                ]
            );
        }
    }
}
