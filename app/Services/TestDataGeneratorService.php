<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\AttributeValuesChanged;
use App\Events\ProductCreated;
use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Manufacturer;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\ProductType;
use App\Models\HierarchyNodeAttributeAssignment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestDataGeneratorService
{
    public const SKU_PREFIX = 'TEST-';
    public const HIERARCHY_TECHNICAL_NAME = '__testdata__';
    private const CACHE_KEY_PROGRESS = 'testdata:progress';
    private const CACHE_KEY_CANCEL = 'testdata:cancel';

    /**
     * Generate test products with attribute values, prices, and hierarchy assignments.
     * Uses Eloquent models to trigger observers, events, and business logic.
     */
    public function generate(
        int $count = 1000,
        bool $withPrices = true,
        int $categoryCount = 20,
        int $categoryDepth = 3,
        ?int $attributesPerProduct = null,
        ?string $userId = null,
    ): array {
        // Disable PHP execution timeout for long-running generation
        set_time_limit(0);

        $startTime = microtime(true);

        // System health check before starting
        $this->assertSystemHealth($count);

        // Reset cancel flag and init progress
        Cache::forget(self::CACHE_KEY_CANCEL);
        $this->updateProgress('Initialisiere...', 0, $count);

        $productTypes = ProductType::where('is_active', true)->get();
        if ($productTypes->isEmpty()) {
            $productTypes = ProductType::all();
        }
        if ($productTypes->isEmpty()) {
            $this->clearProgress();
            throw new \RuntimeException('Keine Produkttypen vorhanden. Bitte zuerst mindestens einen Produkttyp anlegen.');
        }

        $attributes = Attribute::whereNull('parent_attribute_id')
            ->where('status', '!=', 'inactive')
            ->with(['valueList.entries'])
            ->get();

        $priceTypes = $withPrices ? PriceType::all() : collect();

        $this->updateProgress('Hierarchie wird erstellt...', 0, $count);
        $hierarchy = $this->createTestHierarchy();
        $nodes = $this->createCategoryTree($hierarchy, $categoryCount, $categoryDepth);

        // Assign attributes to hierarchy root nodes so they appear in the UI
        $hierarchyAttributes = $attributes;
        if ($attributesPerProduct !== null && $attributesPerProduct < $attributes->count()) {
            $hierarchyAttributes = $attributes->random($attributesPerProduct);
        }
        $this->assignAttributesToHierarchyNodes($nodes, $hierarchyAttributes);

        // Create test manufacturers
        $manufacturers = $this->createTestManufacturers();

        // Determine next free SKU number (avoid duplicates on re-run)
        $nextSkuNumber = $this->getNextSkuNumber();

        $this->updateProgress('Produkte werden generiert...', 0, $count);

        $result = $this->generateProducts(
            $count,
            $nextSkuNumber,
            $productTypes,
            $attributes,
            $priceTypes,
            $nodes,
            $attributesPerProduct,
            $userId,
            $manufacturers,
        );

        $duration = round(microtime(true) - $startTime, 2);

        $this->clearProgress();

        Log::info('Test data generated', [
            'products' => $result['products'],
            'attribute_values' => $result['attribute_values'],
            'prices' => $result['prices'],
            'categories' => count($nodes),
            'duration' => $duration,
        ]);

        return [
            'products_created' => $result['products'],
            'hierarchy_id' => $hierarchy->id,
            'categories_created' => count($nodes),
            'attribute_values_created' => $result['attribute_values'],
            'prices_created' => $result['prices'],
            'duration_seconds' => $duration,
            'cancelled' => $result['cancelled'] ?? false,
        ];
    }

    /**
     * Get current generation progress.
     */
    public function progress(): ?array
    {
        return Cache::get(self::CACHE_KEY_PROGRESS);
    }

    /**
     * Request cancellation of the running generation.
     */
    public function cancel(): void
    {
        Cache::put(self::CACHE_KEY_CANCEL, true, 300);
    }

    private function isCancelled(): bool
    {
        return (bool) Cache::get(self::CACHE_KEY_CANCEL, false);
    }

    private function updateProgress(string $phase, int $current, int $total): void
    {
        Cache::put(self::CACHE_KEY_PROGRESS, [
            'phase' => $phase,
            'current' => $current,
            'total' => $total,
            'percent' => $total > 0 ? round(($current / $total) * 100) : 0,
        ], 300);
    }

    private function clearProgress(): void
    {
        Cache::forget(self::CACHE_KEY_PROGRESS);
        Cache::forget(self::CACHE_KEY_CANCEL);
    }

    /**
     * Find the next available SKU number to avoid duplicate key errors.
     */
    private function getNextSkuNumber(): int
    {
        $lastSku = Product::where('sku', 'like', self::SKU_PREFIX . '%')
            ->orderByRaw("CAST(SUBSTRING(sku, ?) AS UNSIGNED) DESC", [strlen(self::SKU_PREFIX) + 1])
            ->value('sku');

        if (!$lastSku) {
            return 1;
        }

        $number = (int) str_replace(self::SKU_PREFIX, '', $lastSku);

        return $number + 1;
    }

    /**
     * Check disk space and memory before starting generation.
     */
    private function assertSystemHealth(int $count): void
    {
        // Check disk space (need at least 500MB free)
        $freeBytes = disk_free_space(storage_path());
        if ($freeBytes !== false && $freeBytes < 500 * 1024 * 1024) {
            $freeMb = round($freeBytes / 1024 / 1024);
            throw new \RuntimeException("Zu wenig Speicherplatz: nur {$freeMb} MB frei (min. 500 MB benötigt).");
        }

        // Check PHP memory limit vs expected usage (~2KB per product as rough estimate)
        $memoryLimit = $this->getMemoryLimitBytes();
        if ($memoryLimit > 0) {
            $currentUsage = memory_get_usage(true);
            $estimatedNeed = $count * 2048; // ~2KB per product (rough)
            $available = $memoryLimit - $currentUsage;

            if ($estimatedNeed > $available * 0.8) {
                $availableMb = round($available / 1024 / 1024);
                $neededMb = round($estimatedNeed / 1024 / 1024);
                throw new \RuntimeException(
                    "Möglicherweise nicht genug PHP-Speicher: ~{$neededMb} MB benötigt, {$availableMb} MB verfügbar. "
                    . "Versuche weniger Produkte oder erhöhe memory_limit."
                );
            }
        }
    }

    private function getMemoryLimitBytes(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1' || $limit === false) {
            return 0; // Unlimited
        }

        $value = (int) $limit;
        $unit = strtolower(substr($limit, -1));

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Delete all test data (products with TEST- prefix and the test hierarchy).
     */
    public function cleanup(): array
    {
        set_time_limit(0);
        $startTime = microtime(true);

        $productsDeleted = 0;
        $hierarchyDeleted = false;

        DB::transaction(function () use (&$productsDeleted, &$hierarchyDeleted) {
            // Use subquery to avoid too many placeholders with large test datasets
            $testProductIdQuery = Product::where('sku', 'like', self::SKU_PREFIX . '%')
                ->select('id');

            if (Product::where('sku', 'like', self::SKU_PREFIX . '%')->exists()) {
                ProductAttributeValue::whereIn('product_id', $testProductIdQuery)->delete();
                ProductPrice::whereIn('product_id', $testProductIdQuery)->delete();

                DB::table('product_relations')
                    ->where(function ($q) use ($testProductIdQuery) {
                        $q->whereIn('source_product_id', $testProductIdQuery)
                            ->orWhereIn('target_product_id', $testProductIdQuery);
                    })
                    ->delete();

                DB::table('product_media_assignments')
                    ->whereIn('product_id', $testProductIdQuery)->delete();
                DB::table('output_hierarchy_product_assignments')
                    ->whereIn('product_id', $testProductIdQuery)->delete();
                DB::table('products_search_index')
                    ->whereIn('product_id', $testProductIdQuery)->delete();
                DB::table('watchlist_items')
                    ->whereIn('product_id', $testProductIdQuery)->delete();
                DB::table('product_versions')
                    ->whereIn('product_id', $testProductIdQuery)->delete();
                DB::table('variant_inheritance_rules')
                    ->whereIn('product_id', $testProductIdQuery)->delete();

                $productsDeleted = Product::whereIn('id', $testProductIdQuery)->delete();
            }

            $hierarchy = Hierarchy::where('technical_name', self::HIERARCHY_TECHNICAL_NAME)->first();
            if ($hierarchy) {
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

    public function stats(): array
    {
        // Use subquery to avoid too many placeholders
        $testProductIdQuery = Product::where('sku', 'like', self::SKU_PREFIX . '%')->select('id');
        $testCount = Product::where('sku', 'like', self::SKU_PREFIX . '%')->count();

        return [
            'test_products' => $testCount,
            'test_attribute_values' => $testCount > 0
                ? ProductAttributeValue::whereIn('product_id', $testProductIdQuery)->count()
                : 0,
            'test_prices' => $testCount > 0
                ? ProductPrice::whereIn('product_id', $testProductIdQuery)->count()
                : 0,
            'total_products' => Product::count(),
            'test_hierarchy_exists' => Hierarchy::where('technical_name', self::HIERARCHY_TECHNICAL_NAME)->exists(),
        ];
    }

    // ── Product Generation ──────────────────────────────────────────────

    private function generateProducts(
        int $count,
        int $startSkuNumber,
        $productTypes,
        $attributes,
        $priceTypes,
        array $nodes,
        ?int $attributesPerProduct,
        ?string $userId,
        ?array $manufacturers = null,
    ): array {
        $productsCreated = 0;
        $attributeValuesCreated = 0;
        $pricesCreated = 0;
        $statuses = ['draft', 'active', 'active', 'active']; // 75% active
        $cancelled = false;

        for ($i = 0; $i < $count; $i++) {
            // Check for cancellation every 10 products
            if ($i % 10 === 0) {
                $this->updateProgress('Produkte werden generiert...', $i, $count);
                if ($this->isCancelled()) {
                    $cancelled = true;
                    break;
                }
            }

            $skuNumber = $startSkuNumber + $i;
            $productType = $productTypes->random();
            $node = !empty($nodes) ? $nodes[array_rand($nodes)] : null;
            $manufacturer = !empty($manufacturers) ? $manufacturers[array_rand($manufacturers)] : null;

            // Create product via Eloquent (triggers ProductObserver)
            $product = Product::create([
                'sku' => self::SKU_PREFIX . str_pad((string) $skuNumber, 6, '0', STR_PAD_LEFT),
                'name' => $this->generateProductName($i),
                'ean' => $this->generateEan($skuNumber),
                'status' => $statuses[array_rand($statuses)],
                'product_type_id' => $productType->id,
                'product_type_ref' => 'product',
                'master_hierarchy_node_id' => $node?->id,
                'manufacturer_id' => $manufacturer?->id,
                'created_by' => $userId,
            ]);
            $productsCreated++;

            try {
                event(new ProductCreated($product));
            } catch (\Throwable $e) {
                Log::warning('TestDataGenerator: ProductCreated event failed', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Select attributes for this product (limit if configured)
            $productAttributes = $attributes;
            if ($attributesPerProduct !== null && $attributesPerProduct < $attributes->count()) {
                $productAttributes = $attributes->random($attributesPerProduct);
            }

            // Create attribute values via Eloquent (triggers AttributeValueObserver)
            $changedAttributeIds = [];
            foreach ($productAttributes as $attribute) {
                $valueData = $this->generateAttributeValue($attribute, $i);
                if ($valueData === null) {
                    continue;
                }

                $valueData['product_id'] = $product->id;
                $valueData['attribute_id'] = $attribute->id;
                ProductAttributeValue::create($valueData);
                $changedAttributeIds[] = $attribute->id;
                $attributeValuesCreated++;
            }

            if (!empty($changedAttributeIds)) {
                try {
                    event(new AttributeValuesChanged($product->id, $changedAttributeIds));
                } catch (\Throwable $e) {
                    Log::warning('TestDataGenerator: AttributeValuesChanged event failed', [
                        'product_id' => $product->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Create prices via Eloquent relationship
            foreach ($priceTypes as $priceType) {
                $product->prices()->create([
                    'price_type_id' => $priceType->id,
                    'amount' => round(mt_rand(100, 999900) / 100, 2),
                    'currency' => 'EUR',
                ]);
                $pricesCreated++;
            }
        }

        return [
            'products' => $productsCreated,
            'attribute_values' => $attributeValuesCreated,
            'prices' => $pricesCreated,
            'cancelled' => $cancelled,
        ];
    }

    // ── Attribute Value Generation ──────────────────────────────────────

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
            'multiplied_index' => 0,
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
            'composite' => null,
            default => array_merge($base, [
                'value_string' => 'Test-' . $attribute->technical_name . '-' . ($index + 1),
            ]),
        };
    }

    private function generateTextValue(Attribute $attribute, int $index): string
    {
        $prefixes = ['Premium', 'Standard', 'Professional', 'Basic', 'Advanced',
            'Ultra', 'Classic', 'Modern', 'Eco', 'Industrial'];
        $prefix = $prefixes[$index % count($prefixes)];

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

        return array_merge($base, [
            'value_selection_id' => $entries->random()->id,
        ]);
    }

    // ── Hierarchy ───────────────────────────────────────────────────────

    private function createTestHierarchy(): Hierarchy
    {
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
     * Assign attributes to hierarchy root nodes (depth 0) so they are visible in the UI.
     * Child nodes inherit these assignments automatically.
     */
    private function assignAttributesToHierarchyNodes(array $nodes, $attributes): void
    {
        $rootNodes = array_filter($nodes, fn (HierarchyNode $n) => $n->depth === 0);

        if (empty($rootNodes) || $attributes->isEmpty()) {
            return;
        }

        // Group attributes into collections by data type
        $collections = [
            'Basisdaten' => ['text', 'string', 'textarea'],
            'Technisch' => ['number', 'integer', 'float', 'decimal', 'boolean'],
            'Marketing' => ['html', 'richtext', 'date'],
            'Ausstattung' => ['select', 'dropdown', 'multiselect'],
        ];
        $defaultCollection = 'Allgemein';

        foreach ($rootNodes as $rootNode) {
            $collectionSort = 10;
            $attributeSort = 10;
            $lastCollection = null;

            foreach ($attributes as $attribute) {
                // Determine collection based on data type
                $dataType = strtolower($attribute->data_type ?? '');
                $collection = $defaultCollection;
                foreach ($collections as $colName => $types) {
                    if (in_array($dataType, $types)) {
                        $collection = $colName;
                        break;
                    }
                }

                if ($lastCollection !== $collection) {
                    $collectionSort += 10;
                    $attributeSort = 10;
                    $lastCollection = $collection;
                }

                HierarchyNodeAttributeAssignment::firstOrCreate(
                    ['hierarchy_node_id' => $rootNode->id, 'attribute_id' => $attribute->id],
                    [
                        'collection_name' => $collection,
                        'collection_sort' => $collectionSort,
                        'attribute_sort' => $attributeSort,
                    ],
                );

                $attributeSort += 10;
            }
        }
    }

    // ── Product Name & EAN Generation ───────────────────────────────────

    private function generateProductName(int $index): string
    {
        $adjectives = ['Robuster', 'Kompakter', 'Leistungsstarker', 'Vielseitiger', 'Innovativer',
            'Hochwertiger', 'Professioneller', 'Ergonomischer', 'Modularer', 'Präziser',
            'Effizienter', 'Nachhaltiger', 'Flexibler', 'Intelligenter', 'Zuverlässiger'];

        $nouns = ['Werkzeugsatz', 'Adapter', 'Sensor', 'Regler', 'Verbinder',
            'Antrieb', 'Wandler', 'Filter', 'Halter', 'Schalter',
            'Detektor', 'Verstärker', 'Transformator', 'Generator', 'Kompressor',
            'Ventil', 'Motor', 'Pumpe', 'Zylinder', 'Getriebe'];

        $series = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Sigma', 'Omega', 'Pro', 'Max', 'Plus', 'Eco'];

        $adj = $adjectives[$index % count($adjectives)];
        $noun = $nouns[($index / count($adjectives)) % count($nouns)];
        $ser = $series[($index / (count($adjectives) * count($nouns))) % count($series)];

        return $adj . ' ' . $noun . ' ' . $ser . ' ' . ($index + 1);
    }

    private function generateEan(int $index): string
    {
        $ean12 = '400' . str_pad((string) ($index + 1), 9, '0', STR_PAD_LEFT);

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $ean12[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $ean12 . ((10 - ($sum % 10)) % 10);
    }

    // ── Test Manufacturers ──────────────────────────────────────────────

    /**
     * Create or reuse test manufacturers.
     *
     * @return Manufacturer[]
     */
    private function createTestManufacturers(): array
    {
        $names = [
            ['name' => 'TechCorp GmbH', 'city' => 'München', 'country' => 'DE'],
            ['name' => 'IndustrieWerk AG', 'city' => 'Stuttgart', 'country' => 'DE'],
            ['name' => 'PräzisionsTech KG', 'city' => 'Nürnberg', 'country' => 'DE'],
            ['name' => 'NordParts OHG', 'city' => 'Hamburg', 'country' => 'DE'],
            ['name' => 'AlpenMechanik GmbH', 'city' => 'Innsbruck', 'country' => 'AT'],
        ];

        $manufacturers = [];
        foreach ($names as $data) {
            $manufacturers[] = Manufacturer::firstOrCreate(
                ['name' => $data['name']],
                [
                    'city' => $data['city'],
                    'country' => $data['country'],
                ],
            );
        }

        return $manufacturers;
    }

    // ── Static Data ─────────────────────────────────────────────────────

    /** @return string[] */
    private function getCategoryNames(): array
    {
        return [
            'Elektronik', 'Mechanik', 'Hydraulik', 'Pneumatik', 'Elektrik',
            'Sensorik', 'Antriebstechnik', 'Steuerungstechnik', 'Messtechnik', 'Verbindungstechnik',
            'Werkzeuge', 'Zubehör', 'Ersatzteile', 'Verbrauchsmaterial', 'Sicherheitstechnik',
        ];
    }

    /** @return string[] */
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
