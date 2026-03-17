<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\ProductType;
use App\Models\ValueListEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TestDataGeneratorService
{
    public const SKU_PREFIX = 'TEST-';
    public const HIERARCHY_TECHNICAL_NAME = '__testdata__';

    /**
     * Generate test products with attribute values, prices, and hierarchy assignments.
     *
     * @param int         $count             Number of products to generate
     * @param bool        $withPrices        Generate prices for products
     * @param int         $categoryCount     Number of categories in the test hierarchy
     * @param int         $categoryDepth     Depth of category tree
     * @param string|null $userId            User ID for created_by
     * @return array{products_created: int, hierarchy_id: string|null, categories_created: int, attribute_values_created: int, prices_created: int, duration_seconds: float}
     */
    public function generate(
        int $count = 1000,
        bool $withPrices = true,
        int $categoryCount = 20,
        int $categoryDepth = 3,
        ?string $userId = null,
    ): array {
        $startTime = microtime(true);

        // Gather existing data model
        $productTypes = ProductType::where('is_active', true)->get();
        if ($productTypes->isEmpty()) {
            $productTypes = ProductType::all();
        }
        if ($productTypes->isEmpty()) {
            throw new \RuntimeException('Keine Produkttypen vorhanden. Bitte zuerst mindestens einen Produkttyp anlegen.');
        }

        $attributes = Attribute::whereNull('parent_attribute_id')
            ->where('status', '!=', 'inactive')
            ->with(['valueList.entries'])
            ->get();

        $priceTypes = $withPrices ? PriceType::all() : collect();

        // Build hierarchy
        $hierarchy = $this->createTestHierarchy();
        $nodes = $this->createCategoryTree($hierarchy, $categoryCount, $categoryDepth);

        // Generate products in chunks for performance
        $chunkSize = 500;
        $productsCreated = 0;
        $attributeValuesCreated = 0;
        $pricesCreated = 0;

        for ($offset = 0; $offset < $count; $offset += $chunkSize) {
            $batchSize = min($chunkSize, $count - $offset);

            $result = $this->generateProductBatch(
                $batchSize,
                $offset,
                $productTypes,
                $attributes,
                $priceTypes,
                $nodes,
                $userId,
            );

            $productsCreated += $result['products'];
            $attributeValuesCreated += $result['attribute_values'];
            $pricesCreated += $result['prices'];
        }

        $duration = round(microtime(true) - $startTime, 2);

        Log::info('Test data generated', [
            'products' => $productsCreated,
            'attribute_values' => $attributeValuesCreated,
            'prices' => $pricesCreated,
            'categories' => count($nodes),
            'duration' => $duration,
        ]);

        return [
            'products_created' => $productsCreated,
            'hierarchy_id' => $hierarchy->id,
            'categories_created' => count($nodes),
            'attribute_values_created' => $attributeValuesCreated,
            'prices_created' => $pricesCreated,
            'duration_seconds' => $duration,
        ];
    }

    /**
     * Delete all test data (products with TEST- prefix and the test hierarchy).
     *
     * @return array{products_deleted: int, hierarchy_deleted: bool, duration_seconds: float}
     */
    public function cleanup(): array
    {
        $startTime = microtime(true);

        $productsDeleted = 0;
        $hierarchyDeleted = false;

        DB::transaction(function () use (&$productsDeleted, &$hierarchyDeleted) {
            // Find all test products
            $testProductIds = Product::where('sku', 'like', self::SKU_PREFIX . '%')
                ->pluck('id');

            if ($testProductIds->isNotEmpty()) {
                // Delete related data first
                ProductAttributeValue::whereIn('product_id', $testProductIds)->delete();
                ProductPrice::whereIn('product_id', $testProductIds)->delete();

                DB::table('product_relations')
                    ->where(function ($q) use ($testProductIds) {
                        $q->whereIn('source_product_id', $testProductIds)
                            ->orWhereIn('target_product_id', $testProductIds);
                    })
                    ->delete();

                DB::table('product_media_assignments')
                    ->whereIn('product_id', $testProductIds)
                    ->delete();

                DB::table('output_hierarchy_product_assignments')
                    ->whereIn('product_id', $testProductIds)
                    ->delete();

                DB::table('products_search_index')
                    ->whereIn('product_id', $testProductIds)
                    ->delete();

                DB::table('watchlist_items')
                    ->whereIn('product_id', $testProductIds)
                    ->delete();

                DB::table('product_versions')
                    ->whereIn('product_id', $testProductIds)
                    ->delete();

                DB::table('variant_inheritance_rules')
                    ->whereIn('product_id', $testProductIds)
                    ->delete();

                $productsDeleted = Product::whereIn('id', $testProductIds)->delete();
            }

            // Delete test hierarchy
            $hierarchy = Hierarchy::where('technical_name', self::HIERARCHY_TECHNICAL_NAME)->first();
            if ($hierarchy) {
                // Delete node attribute values and assignments
                $nodeIds = HierarchyNode::where('hierarchy_id', $hierarchy->id)->pluck('id');
                if ($nodeIds->isNotEmpty()) {
                    DB::table('hierarchy_node_attribute_values')
                        ->whereIn('hierarchy_node_id', $nodeIds)
                        ->delete();
                    DB::table('hierarchy_node_attribute_assignments')
                        ->whereIn('hierarchy_node_id', $nodeIds)
                        ->delete();
                    DB::table('output_hierarchy_product_assignments')
                        ->whereIn('hierarchy_node_id', $nodeIds)
                        ->delete();
                }

                HierarchyNode::where('hierarchy_id', $hierarchy->id)->delete();
                DB::table('hierarchy_attribute_assignments')
                    ->where('hierarchy_id', $hierarchy->id)
                    ->delete();
                $hierarchy->delete();
                $hierarchyDeleted = true;
            }
        });

        $duration = round(microtime(true) - $startTime, 2);

        Log::info('Test data cleaned up', [
            'products_deleted' => $productsDeleted,
            'hierarchy_deleted' => $hierarchyDeleted,
            'duration' => $duration,
        ]);

        return [
            'products_deleted' => $productsDeleted,
            'hierarchy_deleted' => $hierarchyDeleted,
            'duration_seconds' => $duration,
        ];
    }

    /**
     * Get statistics about existing test data.
     *
     * @return array{test_products: int, test_hierarchy_exists: bool}
     */
    public function stats(): array
    {
        return [
            'test_products' => Product::where('sku', 'like', self::SKU_PREFIX . '%')->count(),
            'test_hierarchy_exists' => Hierarchy::where('technical_name', self::HIERARCHY_TECHNICAL_NAME)->exists(),
        ];
    }

    private function createTestHierarchy(): Hierarchy
    {
        // Delete existing test hierarchy first
        $existing = Hierarchy::where('technical_name', self::HIERARCHY_TECHNICAL_NAME)->first();
        if ($existing) {
            HierarchyNode::where('hierarchy_id', $existing->id)->delete();
            $existing->delete();
        }

        return Hierarchy::create([
            'technical_name' => self::HIERARCHY_TECHNICAL_NAME,
            'name_de' => 'Testdaten-Hierarchie',
            'name_en' => 'Test Data Hierarchy',
            'name_json' => ['de' => 'Testdaten-Hierarchie', 'en' => 'Test Data Hierarchy'],
            'hierarchy_type' => 'master',
            'description' => 'Automatisch generierte Hierarchie für Testdaten. Kann über Admin > Testdaten löschen entfernt werden.',
        ]);
    }

    /**
     * @return HierarchyNode[]
     */
    private function createCategoryTree(Hierarchy $hierarchy, int $categoryCount, int $maxDepth): array
    {
        $nodes = [];
        $categories = $this->getCategoryNames();

        // Create root categories first
        $rootCount = min((int) ceil($categoryCount / $maxDepth), $categoryCount);

        for ($i = 0; $i < $rootCount && count($nodes) < $categoryCount; $i++) {
            $catName = $categories[$i % count($categories)];
            $suffix = $i >= count($categories) ? ' ' . ($i + 1) : '';

            $rootNode = HierarchyNode::create([
                'hierarchy_id' => $hierarchy->id,
                'parent_node_id' => null,
                'name_de' => $catName . $suffix,
                'name_en' => $catName . $suffix,
                'name_json' => ['de' => $catName . $suffix, 'en' => $catName . $suffix],
                'path' => '',
                'depth' => 0,
                'sort_order' => $i,
                'is_active' => true,
            ]);
            $rootNode->update(['path' => $rootNode->id . '/']);
            $nodes[] = $rootNode;

            // Create children up to max depth
            $this->createChildNodes($hierarchy, $rootNode, 1, $maxDepth, $nodes, $categoryCount);
        }

        return $nodes;
    }

    private function createChildNodes(
        Hierarchy $hierarchy,
        HierarchyNode $parent,
        int $currentDepth,
        int $maxDepth,
        array &$nodes,
        int $maxTotal,
    ): void {
        if ($currentDepth >= $maxDepth || count($nodes) >= $maxTotal) {
            return;
        }

        $subcategories = $this->getSubcategoryNames();
        $childCount = min(rand(2, 4), $maxTotal - count($nodes));

        for ($i = 0; $i < $childCount && count($nodes) < $maxTotal; $i++) {
            $name = $subcategories[($i + count($nodes)) % count($subcategories)];

            $childNode = HierarchyNode::create([
                'hierarchy_id' => $hierarchy->id,
                'parent_node_id' => $parent->id,
                'name_de' => $name,
                'name_en' => $name,
                'name_json' => ['de' => $name, 'en' => $name],
                'path' => $parent->path . '/',
                'depth' => $currentDepth,
                'sort_order' => $i,
                'is_active' => true,
            ]);
            $childNode->update(['path' => $parent->path . $childNode->id . '/']);
            $nodes[] = $childNode;

            $this->createChildNodes($hierarchy, $childNode, $currentDepth + 1, $maxDepth, $nodes, $maxTotal);
        }
    }

    /**
     * @return array{products: int, attribute_values: int, prices: int}
     */
    private function generateProductBatch(
        int $batchSize,
        int $offset,
        $productTypes,
        $attributes,
        $priceTypes,
        array $nodes,
        ?string $userId,
    ): array {
        $productRows = [];
        $attributeValueRows = [];
        $priceRows = [];
        $now = now();
        $statuses = ['draft', 'active', 'active', 'active']; // 75% active

        for ($i = 0; $i < $batchSize; $i++) {
            $index = $offset + $i;
            $productId = (string) Str::uuid();
            $productType = $productTypes->random();
            $node = !empty($nodes) ? $nodes[array_rand($nodes)] : null;
            $status = $statuses[array_rand($statuses)];

            $productRows[] = [
                'id' => $productId,
                'sku' => self::SKU_PREFIX . str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                'name' => $this->generateProductName($index),
                'ean' => $this->generateEan($index),
                'status' => $status,
                'product_type_id' => $productType->id,
                'product_type_ref' => 'product',
                'master_hierarchy_node_id' => $node?->id,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Generate attribute values for this product
            foreach ($attributes as $attribute) {
                $value = $this->generateAttributeValue($attribute, $index);
                if ($value !== null) {
                    $value['id'] = (string) Str::uuid();
                    $value['product_id'] = $productId;
                    $value['attribute_id'] = $attribute->id;
                    $value['created_at'] = $now;
                    $value['updated_at'] = $now;
                    $attributeValueRows[] = $value;
                }
            }

            // Generate prices
            foreach ($priceTypes as $priceType) {
                $priceRows[] = [
                    'id' => (string) Str::uuid(),
                    'product_id' => $productId,
                    'price_type_id' => $priceType->id,
                    'amount' => round(mt_rand(100, 999900) / 100, 2),
                    'currency' => 'EUR',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Bulk insert in transaction
        DB::transaction(function () use ($productRows, $attributeValueRows, $priceRows) {
            // Insert products
            foreach (array_chunk($productRows, 500) as $chunk) {
                DB::table('products')->insert($chunk);
            }

            // Insert attribute values
            foreach (array_chunk($attributeValueRows, 1000) as $chunk) {
                DB::table('product_attribute_values')->insert($chunk);
            }

            // Insert prices
            if (!empty($priceRows)) {
                foreach (array_chunk($priceRows, 1000) as $chunk) {
                    DB::table('product_prices')->insert($chunk);
                }
            }
        });

        return [
            'products' => count($productRows),
            'attribute_values' => count($attributeValueRows),
            'prices' => count($priceRows),
        ];
    }

    private function generateAttributeValue(Attribute $attribute, int $index): ?array
    {
        $base = [
            'value_string' => null,
            'value_number' => null,
            'value_date' => null,
            'value_flag' => null,
            'value_selection_id' => null,
            'unit_id' => $attribute->default_unit_id,
            'language' => $attribute->is_translatable ? 'de' : null,
            'multiplied_index' => null,
            'is_inherited' => false,
        ];

        return match (strtolower($attribute->data_type ?? '')) {
            'text', 'string' => array_merge($base, [
                'value_string' => $this->generateTextValue($attribute, $index),
            ]),
            'textarea' => array_merge($base, [
                'value_string' => $this->generateTextareaValue($attribute, $index),
            ]),
            'html', 'richtext' => array_merge($base, [
                'value_string' => $this->generateHtmlValue($attribute, $index),
            ]),
            'number', 'integer', 'float', 'decimal' => array_merge($base, [
                'value_number' => $this->generateNumberValue($attribute),
            ]),
            'boolean' => array_merge($base, [
                'value_flag' => (bool) mt_rand(0, 1),
            ]),
            'date' => array_merge($base, [
                'value_date' => now()->subDays(mt_rand(0, 365))->toDateString(),
            ]),
            'select', 'dropdown' => $this->generateSelectValue($attribute, $base),
            'multiselect' => $this->generateSelectValue($attribute, $base),
            'composite' => null, // Skip composite attributes
            default => array_merge($base, [
                'value_string' => 'Test-' . $attribute->technical_name . '-' . ($index + 1),
            ]),
        };
    }

    private function generateTextValue(Attribute $attribute, int $index): string
    {
        $prefixes = [
            'Premium', 'Standard', 'Professional', 'Basic', 'Advanced',
            'Ultra', 'Classic', 'Modern', 'Eco', 'Industrial',
        ];
        $prefix = $prefixes[($index) % count($prefixes)];

        return $prefix . ' ' . ($attribute->name_de ?? $attribute->technical_name) . ' ' . ($index + 1);
    }

    private function generateTextareaValue(Attribute $attribute, int $index): string
    {
        $descriptions = [
            'Hochwertiges Produkt mit erstklassiger Verarbeitung und langer Lebensdauer.',
            'Vielseitig einsetzbar für verschiedene Anwendungsbereiche.',
            'Entwickelt für professionelle Anforderungen im industriellen Umfeld.',
            'Kompaktes Design mit maximaler Leistungsfähigkeit.',
            'Umweltfreundliche Herstellung nach höchsten Qualitätsstandards.',
            'Robuste Konstruktion für den täglichen Einsatz.',
            'Innovatives Produkt mit modernster Technologie.',
            'Ergonomisch gestaltet für maximalen Komfort.',
        ];

        return $descriptions[$index % count($descriptions)];
    }

    private function generateHtmlValue(Attribute $attribute, int $index): string
    {
        return '<p><strong>' . ($attribute->name_de ?? 'Beschreibung') . '</strong></p>'
            . '<p>Testprodukt Nr. ' . ($index + 1) . ' mit umfangreichen Eigenschaften.</p>'
            . '<ul><li>Eigenschaft A</li><li>Eigenschaft B</li><li>Eigenschaft C</li></ul>';
    }

    private function generateNumberValue(Attribute $attribute): float
    {
        $maxPre = $attribute->max_pre_decimal ?? 6;
        $maxPost = $attribute->max_post_decimal ?? 2;

        $max = (int) pow(10, min($maxPre, 4)) - 1;
        $value = mt_rand(1, max(1, $max));

        if ($maxPost > 0) {
            $decimal = mt_rand(0, (int) pow(10, min($maxPost, 2)) - 1);
            $value = $value + ($decimal / pow(10, min($maxPost, 2)));
        }

        return round($value, $maxPost);
    }

    private function generateSelectValue(Attribute $attribute, array $base): ?array
    {
        if (!$attribute->valueList || $attribute->valueList->entries->isEmpty()) {
            return null;
        }

        $entries = $attribute->valueList->entries->where('is_active', true);
        if ($entries->isEmpty()) {
            $entries = $attribute->valueList->entries;
        }

        $entry = $entries->random();

        return array_merge($base, [
            'value_selection_id' => $entry->id,
        ]);
    }

    private function generateProductName(int $index): string
    {
        $adjectives = [
            'Robuster', 'Kompakter', 'Leistungsstarker', 'Vielseitiger', 'Innovativer',
            'Hochwertiger', 'Professioneller', 'Ergonomischer', 'Modularer', 'Präziser',
            'Effizienter', 'Nachhaltiger', 'Flexibler', 'Intelligenter', 'Zuverlässiger',
        ];

        $nouns = [
            'Werkzeugsatz', 'Adapter', 'Sensor', 'Regler', 'Verbinder',
            'Antrieb', 'Wandler', 'Filter', 'Halter', 'Schalter',
            'Detektor', 'Verstärker', 'Transformator', 'Generator', 'Kompressor',
            'Ventil', 'Motor', 'Pumpe', 'Zylinder', 'Getriebe',
        ];

        $series = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Sigma', 'Omega', 'Pro', 'Max', 'Plus', 'Eco'];

        $adj = $adjectives[$index % count($adjectives)];
        $noun = $nouns[($index / count($adjectives)) % count($nouns)];
        $ser = $series[($index / (count($adjectives) * count($nouns))) % count($series)];

        return $adj . ' ' . $noun . ' ' . $ser . ' ' . ($index + 1);
    }

    private function generateEan(int $index): string
    {
        // Generate a 13-digit EAN
        $prefix = '400';
        $body = str_pad((string) ($index + 1), 9, '0', STR_PAD_LEFT);
        $ean12 = $prefix . $body;

        // Calculate check digit
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $ean12[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $checkDigit = (10 - ($sum % 10)) % 10;

        return $ean12 . $checkDigit;
    }

    /**
     * @return string[]
     */
    private function getCategoryNames(): array
    {
        return [
            'Elektronik', 'Mechanik', 'Hydraulik', 'Pneumatik', 'Elektrik',
            'Sensorik', 'Antriebstechnik', 'Steuerungstechnik', 'Messtechnik', 'Verbindungstechnik',
            'Werkzeuge', 'Zubehör', 'Ersatzteile', 'Verbrauchsmaterial', 'Sicherheitstechnik',
        ];
    }

    /**
     * @return string[]
     */
    private function getSubcategoryNames(): array
    {
        return [
            'Standardkomponenten', 'Spezialteile', 'Bausätze', 'Zubehör', 'Verbrauchsmaterial',
            'Hochleistung', 'Industrie', 'Kompakt', 'Mini', 'Maxi',
            'Serie A', 'Serie B', 'Serie C', 'Premium', 'Economy',
            'Digital', 'Analog', 'Hybrid', 'Smart', 'Classic',
            'Edelstahl', 'Aluminium', 'Kunststoff', 'Messing', 'Titan',
        ];
    }
}
