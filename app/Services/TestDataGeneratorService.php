<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\AttributeValuesChanged;
use App\Events\ProductCreated;
use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\Manufacturer;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\ProductRelation;
use App\Models\ProductRelationType;
use App\Models\ProductType;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestDataGeneratorService
{
    public const SKU_PREFIX = 'TEST-';

    public const HIERARCHY_TECHNICAL_NAME = '__testdata__';

    public const TEST_PREFIX = '__test__';

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

        // Ensure foundation/master data exists (idempotent, skips if data already present)
        $foundationStats = $this->ensureFoundationData();

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

        // Create product relations between test products
        $this->updateProgress('Produktbeziehungen werden erstellt...', 0, 1);
        $relationsCreated = $this->createTestProductRelations();

        $duration = round(microtime(true) - $startTime, 2);

        $this->clearProgress();

        Log::info('Test data generated', [
            'products' => $result['products'],
            'attribute_values' => $result['attribute_values'],
            'prices' => $result['prices'],
            'relations' => $relationsCreated,
            'categories' => count($nodes),
            'foundation_data' => $foundationStats,
            'duration' => $duration,
        ]);

        return [
            'products_created' => $result['products'],
            'hierarchy_id' => $hierarchy->id,
            'categories_created' => count($nodes),
            'attribute_values_created' => $result['attribute_values'],
            'prices_created' => $result['prices'],
            'relations_created' => $relationsCreated,
            'foundation_data' => $foundationStats,
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
        $lastSku = Product::where('sku', 'like', self::SKU_PREFIX.'%')
            ->orderByRaw('CAST(SUBSTRING(sku, ?) AS UNSIGNED) DESC', [strlen(self::SKU_PREFIX) + 1])
            ->value('sku');

        if (! $lastSku) {
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
                    .'Versuche weniger Produkte oder erhöhe memory_limit.'
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
     * Delete all test data: products (TEST- prefix), hierarchy, and all foundation
     * data with __test__ prefix (attributes, types, value lists, units, etc.).
     */
    public function cleanup(): array
    {
        set_time_limit(0);
        $startTime = microtime(true);

        $productsDeleted = 0;
        $hierarchyDeleted = false;
        $attributesDeleted = 0;
        $attributeTypesDeleted = 0;
        $valueListsDeleted = 0;
        $unitGroupsDeleted = 0;
        $relationTypesDeleted = 0;
        $productTypesDeleted = 0;
        $priceTypesDeleted = 0;

        DB::transaction(function () use (
            &$productsDeleted, &$hierarchyDeleted,
            &$attributesDeleted, &$attributeTypesDeleted, &$valueListsDeleted,
            &$unitGroupsDeleted, &$relationTypesDeleted, &$productTypesDeleted, &$priceTypesDeleted,
        ) {
            // Use subquery to avoid too many placeholders with large test datasets
            $testProductIdQuery = Product::where('sku', 'like', self::SKU_PREFIX.'%')
                ->select('id');

            if (Product::where('sku', 'like', self::SKU_PREFIX.'%')->exists()) {
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

            // Clean up test foundation data (order respects FK constraints)

            // 1. Test attributes (must go before attribute types & value lists)
            $testAttributeIds = Attribute::where('technical_name', 'like', self::TEST_PREFIX.'%')->pluck('id');
            if ($testAttributeIds->isNotEmpty()) {
                DB::table('hierarchy_node_attribute_assignments')
                    ->whereIn('attribute_id', $testAttributeIds)->delete();
                DB::table('attribute_view_assignments')
                    ->whereIn('attribute_id', $testAttributeIds)->delete();
                DB::table('variant_inheritance_rules')
                    ->whereIn('attribute_id', $testAttributeIds)->delete();
                $attributesDeleted = Attribute::whereIn('id', $testAttributeIds)->delete();
            }

            // 2. Test attribute types
            $attributeTypesDeleted = AttributeType::where('technical_name', 'like', self::TEST_PREFIX.'%')->delete();

            // 3. Test value list entries, then value lists
            $testValueListIds = ValueList::where('technical_name', 'like', self::TEST_PREFIX.'%')->pluck('id');
            if ($testValueListIds->isNotEmpty()) {
                ValueListEntry::whereIn('value_list_id', $testValueListIds)->delete();
                $valueListsDeleted = ValueList::whereIn('id', $testValueListIds)->delete();
            }

            // 4. Test units, then unit groups
            $testUnitGroupIds = UnitGroup::where('technical_name', 'like', self::TEST_PREFIX.'%')->pluck('id');
            if ($testUnitGroupIds->isNotEmpty()) {
                Unit::whereIn('unit_group_id', $testUnitGroupIds)->delete();
                $unitGroupsDeleted = UnitGroup::whereIn('id', $testUnitGroupIds)->delete();
            }

            // 5. Test product relation types
            $relationTypesDeleted = ProductRelationType::where('technical_name', 'like', self::TEST_PREFIX.'%')->delete();

            // 6. Test product types
            $productTypesDeleted = ProductType::where('technical_name', 'like', self::TEST_PREFIX.'%')->delete();

            // 7. Test price types
            $priceTypesDeleted = PriceType::where('technical_name', 'like', self::TEST_PREFIX.'%')->delete();
        });

        $duration = round(microtime(true) - $startTime, 2);

        Log::info('Test data cleaned up', [
            'products_deleted' => $productsDeleted,
            'hierarchy_deleted' => $hierarchyDeleted,
            'attributes_deleted' => $attributesDeleted,
            'attribute_types_deleted' => $attributeTypesDeleted,
            'value_lists_deleted' => $valueListsDeleted,
            'unit_groups_deleted' => $unitGroupsDeleted,
            'relation_types_deleted' => $relationTypesDeleted,
            'product_types_deleted' => $productTypesDeleted,
            'price_types_deleted' => $priceTypesDeleted,
            'duration' => $duration,
        ]);

        return [
            'products_deleted' => $productsDeleted,
            'hierarchy_deleted' => $hierarchyDeleted,
            'attributes_deleted' => $attributesDeleted,
            'attribute_types_deleted' => $attributeTypesDeleted,
            'value_lists_deleted' => $valueListsDeleted,
            'unit_groups_deleted' => $unitGroupsDeleted,
            'relation_types_deleted' => $relationTypesDeleted,
            'product_types_deleted' => $productTypesDeleted,
            'price_types_deleted' => $priceTypesDeleted,
            'duration_seconds' => $duration,
        ];
    }

    public function stats(): array
    {
        // Use subquery to avoid too many placeholders
        $testProductIdQuery = Product::where('sku', 'like', self::SKU_PREFIX.'%')->select('id');
        $testCount = Product::where('sku', 'like', self::SKU_PREFIX.'%')->count();

        return [
            'test_products' => $testCount,
            'test_attribute_values' => $testCount > 0
                ? ProductAttributeValue::whereIn('product_id', $testProductIdQuery)->count()
                : 0,
            'test_prices' => $testCount > 0
                ? ProductPrice::whereIn('product_id', $testProductIdQuery)->count()
                : 0,
            'test_product_relations' => $testCount > 0
                ? DB::table('product_relations')
                    ->whereIn('source_product_id', $testProductIdQuery)
                    ->count()
                : 0,
            'test_product_types' => ProductType::where('technical_name', 'like', self::TEST_PREFIX.'%')->count(),
            'test_attribute_types' => AttributeType::where('technical_name', 'like', self::TEST_PREFIX.'%')->count(),
            'test_attributes' => Attribute::where('technical_name', 'like', self::TEST_PREFIX.'%')->count(),
            'test_value_lists' => ValueList::where('technical_name', 'like', self::TEST_PREFIX.'%')->count(),
            'test_unit_groups' => UnitGroup::where('technical_name', 'like', self::TEST_PREFIX.'%')->count(),
            'test_price_types' => PriceType::where('technical_name', 'like', self::TEST_PREFIX.'%')->count(),
            'test_relation_types' => ProductRelationType::where('technical_name', 'like', self::TEST_PREFIX.'%')->count(),
            'total_products' => Product::count(),
            'test_hierarchy_exists' => Hierarchy::where('technical_name', self::HIERARCHY_TECHNICAL_NAME)->exists(),
        ];
    }

    // ── Foundation Data ───────────────────────────────────────────────

    /**
     * Ensure all foundation/master data exists so product generation can work on an empty DB.
     * Each entity type is only created if none exist globally. Uses firstOrCreate for idempotency.
     */
    private function ensureFoundationData(): array
    {
        $stats = [];

        $this->updateProgress('Stammdaten werden geprüft...', 0, 7);

        $stats['product_types_created'] = $this->ensureProductTypes();
        $this->updateProgress('Stammdaten werden geprüft...', 1, 7);

        $stats['unit_groups_created'] = $this->ensureUnitGroups();
        $this->updateProgress('Stammdaten werden geprüft...', 2, 7);

        $stats['value_lists_created'] = $this->ensureValueLists();
        $this->updateProgress('Stammdaten werden geprüft...', 3, 7);

        $stats['price_types_created'] = $this->ensurePriceTypes();
        $this->updateProgress('Stammdaten werden geprüft...', 4, 7);

        $stats['attribute_types_created'] = $this->ensureAttributeTypes();
        $this->updateProgress('Stammdaten werden geprüft...', 5, 7);

        $stats['attributes_created'] = $this->ensureAttributes();
        $this->updateProgress('Stammdaten werden geprüft...', 6, 7);

        $stats['relation_types_created'] = $this->ensureProductRelationTypes();

        return $stats;
    }

    private function ensureProductTypes(): int
    {
        if (ProductType::exists()) {
            return 0;
        }

        $types = [
            [
                'technical_name' => 'elektronik',
                'name_de' => 'Elektronik',
                'name_en' => 'Electronics',
                'name_json' => ['de' => 'Elektronik', 'en' => 'Electronics'],
                'description' => 'Elektronische Geräte und Unterhaltungselektronik',
                'has_variants' => true,
                'has_ean' => true,
                'has_prices' => true,
                'has_media' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'technical_name' => 'zubehoer',
                'name_de' => 'Zubehör',
                'name_en' => 'Accessories',
                'name_json' => ['de' => 'Zubehör', 'en' => 'Accessories'],
                'description' => 'Zubehörteile und Peripheriegeräte',
                'has_variants' => false,
                'has_ean' => true,
                'has_prices' => true,
                'has_media' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'technical_name' => 'software',
                'name_de' => 'Software',
                'name_en' => 'Software',
                'name_json' => ['de' => 'Software', 'en' => 'Software'],
                'description' => 'Softwareprodukte und Lizenzen',
                'has_variants' => false,
                'has_ean' => false,
                'has_prices' => true,
                'has_media' => false,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        $created = 0;
        foreach ($types as $data) {
            $techName = self::TEST_PREFIX.$data['technical_name'];
            ProductType::firstOrCreate(
                ['technical_name' => $techName],
                array_merge($data, ['technical_name' => $techName]),
            );
            $created++;
        }

        return $created;
    }

    private function ensureUnitGroups(): int
    {
        if (UnitGroup::exists()) {
            return 0;
        }

        $groups = [
            [
                'technical_name' => 'gewicht',
                'name_de' => 'Gewicht',
                'name_en' => 'Weight',
                'name_json' => ['de' => 'Gewicht', 'en' => 'Weight'],
                'units' => [
                    ['technical_name' => 'gramm', 'abbreviation' => 'g', 'conversion_factor' => 1, 'is_base_unit' => true],
                    ['technical_name' => 'kilogramm', 'abbreviation' => 'kg', 'conversion_factor' => 1000, 'is_base_unit' => false],
                ],
            ],
            [
                'technical_name' => 'laenge',
                'name_de' => 'Länge',
                'name_en' => 'Length',
                'name_json' => ['de' => 'Länge', 'en' => 'Length'],
                'units' => [
                    ['technical_name' => 'millimeter', 'abbreviation' => 'mm', 'conversion_factor' => 1, 'is_base_unit' => true],
                    ['technical_name' => 'zentimeter', 'abbreviation' => 'cm', 'conversion_factor' => 10, 'is_base_unit' => false],
                    ['technical_name' => 'meter', 'abbreviation' => 'm', 'conversion_factor' => 1000, 'is_base_unit' => false],
                ],
            ],
            [
                'technical_name' => 'speicher',
                'name_de' => 'Speicher',
                'name_en' => 'Storage',
                'name_json' => ['de' => 'Speicher', 'en' => 'Storage'],
                'units' => [
                    ['technical_name' => 'megabyte', 'abbreviation' => 'MB', 'conversion_factor' => 1, 'is_base_unit' => true],
                    ['technical_name' => 'gigabyte', 'abbreviation' => 'GB', 'conversion_factor' => 1024, 'is_base_unit' => false],
                    ['technical_name' => 'terabyte', 'abbreviation' => 'TB', 'conversion_factor' => 1048576, 'is_base_unit' => false],
                ],
            ],
            [
                'technical_name' => 'zeit',
                'name_de' => 'Zeit',
                'name_en' => 'Time',
                'name_json' => ['de' => 'Zeit', 'en' => 'Time'],
                'units' => [
                    ['technical_name' => 'stunde', 'abbreviation' => 'h', 'conversion_factor' => 1, 'is_base_unit' => true],
                    ['technical_name' => 'minute', 'abbreviation' => 'min', 'conversion_factor' => 0.0166666667, 'is_base_unit' => false],
                ],
            ],
            [
                'technical_name' => 'leistung',
                'name_de' => 'Leistung',
                'name_en' => 'Power',
                'name_json' => ['de' => 'Leistung', 'en' => 'Power'],
                'units' => [
                    ['technical_name' => 'watt', 'abbreviation' => 'W', 'conversion_factor' => 1, 'is_base_unit' => true],
                    ['technical_name' => 'kilowatt', 'abbreviation' => 'kW', 'conversion_factor' => 1000, 'is_base_unit' => false],
                ],
            ],
        ];

        $created = 0;
        foreach ($groups as $groupData) {
            $units = $groupData['units'];
            unset($groupData['units']);

            $techName = self::TEST_PREFIX.$groupData['technical_name'];
            $group = UnitGroup::firstOrCreate(
                ['technical_name' => $techName],
                array_merge($groupData, ['technical_name' => $techName]),
            );

            foreach ($units as $unitData) {
                $unitTechName = self::TEST_PREFIX.$unitData['technical_name'];
                Unit::firstOrCreate(
                    ['unit_group_id' => $group->id, 'technical_name' => $unitTechName],
                    array_merge($unitData, [
                        'unit_group_id' => $group->id,
                        'technical_name' => $unitTechName,
                    ]),
                );
                $created++;
            }
        }

        return $created;
    }

    private function ensureValueLists(): int
    {
        if (ValueList::exists()) {
            return 0;
        }

        $lists = [
            [
                'technical_name' => 'farben',
                'name_de' => 'Farben',
                'name_en' => 'Colors',
                'name_json' => ['de' => 'Farben', 'en' => 'Colors'],
                'entries' => [
                    ['technical_name' => 'schwarz', 'display_value_de' => 'Schwarz', 'display_value_en' => 'Black'],
                    ['technical_name' => 'weiss', 'display_value_de' => 'Weiß', 'display_value_en' => 'White'],
                    ['technical_name' => 'silber', 'display_value_de' => 'Silber', 'display_value_en' => 'Silver'],
                    ['technical_name' => 'blau', 'display_value_de' => 'Blau', 'display_value_en' => 'Blue'],
                    ['technical_name' => 'rot', 'display_value_de' => 'Rot', 'display_value_en' => 'Red'],
                    ['technical_name' => 'gruen', 'display_value_de' => 'Grün', 'display_value_en' => 'Green'],
                ],
            ],
            [
                'technical_name' => 'materialien',
                'name_de' => 'Materialien',
                'name_en' => 'Materials',
                'name_json' => ['de' => 'Materialien', 'en' => 'Materials'],
                'entries' => [
                    ['technical_name' => 'kunststoff', 'display_value_de' => 'Kunststoff', 'display_value_en' => 'Plastic'],
                    ['technical_name' => 'aluminium', 'display_value_de' => 'Aluminium', 'display_value_en' => 'Aluminum'],
                    ['technical_name' => 'stahl', 'display_value_de' => 'Stahl', 'display_value_en' => 'Steel'],
                    ['technical_name' => 'glas', 'display_value_de' => 'Glas', 'display_value_en' => 'Glass'],
                    ['technical_name' => 'holz', 'display_value_de' => 'Holz', 'display_value_en' => 'Wood'],
                ],
            ],
            [
                'technical_name' => 'energieeffizienz',
                'name_de' => 'Energieeffizienz',
                'name_en' => 'Energy Efficiency',
                'name_json' => ['de' => 'Energieeffizienz', 'en' => 'Energy Efficiency'],
                'entries' => [
                    ['technical_name' => 'a_plus_plus_plus', 'display_value_de' => 'A+++', 'display_value_en' => 'A+++'],
                    ['technical_name' => 'a_plus_plus', 'display_value_de' => 'A++', 'display_value_en' => 'A++'],
                    ['technical_name' => 'a_plus', 'display_value_de' => 'A+', 'display_value_en' => 'A+'],
                    ['technical_name' => 'a', 'display_value_de' => 'A', 'display_value_en' => 'A'],
                    ['technical_name' => 'b', 'display_value_de' => 'B', 'display_value_en' => 'B'],
                ],
            ],
            [
                'technical_name' => 'anschlusstypen',
                'name_de' => 'Anschlusstypen',
                'name_en' => 'Connection Types',
                'name_json' => ['de' => 'Anschlusstypen', 'en' => 'Connection Types'],
                'entries' => [
                    ['technical_name' => 'usb_c', 'display_value_de' => 'USB-C', 'display_value_en' => 'USB-C'],
                    ['technical_name' => 'usb_a', 'display_value_de' => 'USB-A', 'display_value_en' => 'USB-A'],
                    ['technical_name' => 'hdmi', 'display_value_de' => 'HDMI', 'display_value_en' => 'HDMI'],
                    ['technical_name' => 'klinke_35', 'display_value_de' => '3,5mm Klinke', 'display_value_en' => '3.5mm Jack'],
                    ['technical_name' => 'bluetooth', 'display_value_de' => 'Bluetooth', 'display_value_en' => 'Bluetooth'],
                ],
            ],
            [
                'technical_name' => 'betriebssysteme',
                'name_de' => 'Betriebssysteme',
                'name_en' => 'Operating Systems',
                'name_json' => ['de' => 'Betriebssysteme', 'en' => 'Operating Systems'],
                'entries' => [
                    ['technical_name' => 'windows', 'display_value_de' => 'Windows', 'display_value_en' => 'Windows'],
                    ['technical_name' => 'macos', 'display_value_de' => 'macOS', 'display_value_en' => 'macOS'],
                    ['technical_name' => 'linux', 'display_value_de' => 'Linux', 'display_value_en' => 'Linux'],
                    ['technical_name' => 'android', 'display_value_de' => 'Android', 'display_value_en' => 'Android'],
                    ['technical_name' => 'ios', 'display_value_de' => 'iOS', 'display_value_en' => 'iOS'],
                ],
            ],
        ];

        $created = 0;
        foreach ($lists as $listData) {
            $entries = $listData['entries'];
            unset($listData['entries']);

            $techName = self::TEST_PREFIX.$listData['technical_name'];
            $list = ValueList::firstOrCreate(
                ['technical_name' => $techName],
                array_merge($listData, ['technical_name' => $techName]),
            );

            foreach ($entries as $sortOrder => $entryData) {
                $entryTechName = self::TEST_PREFIX.$entryData['technical_name'];
                ValueListEntry::firstOrCreate(
                    ['value_list_id' => $list->id, 'technical_name' => $entryTechName],
                    [
                        'value_list_id' => $list->id,
                        'technical_name' => $entryTechName,
                        'display_value_de' => $entryData['display_value_de'],
                        'display_value_en' => $entryData['display_value_en'],
                        'display_value_json' => [
                            'de' => $entryData['display_value_de'],
                            'en' => $entryData['display_value_en'],
                        ],
                        'sort_order' => $sortOrder + 1,
                        'is_active' => true,
                    ],
                );
                $created++;
            }
        }

        return $created;
    }

    private function ensurePriceTypes(): int
    {
        if (PriceType::exists()) {
            return 0;
        }

        $types = [
            [
                'technical_name' => 'listenpreis',
                'name_de' => 'Listenpreis',
                'name_en' => 'List Price',
                'name_json' => ['de' => 'Listenpreis', 'en' => 'List Price'],
            ],
            [
                'technical_name' => 'aktionspreis',
                'name_de' => 'Aktionspreis',
                'name_en' => 'Sale Price',
                'name_json' => ['de' => 'Aktionspreis', 'en' => 'Sale Price'],
            ],
        ];

        $created = 0;
        foreach ($types as $data) {
            $techName = self::TEST_PREFIX.$data['technical_name'];
            PriceType::firstOrCreate(
                ['technical_name' => $techName],
                array_merge($data, ['technical_name' => $techName]),
            );
            $created++;
        }

        return $created;
    }

    private function ensureAttributeTypes(): int
    {
        if (AttributeType::exists()) {
            return 0;
        }

        $types = [
            ['technical_name' => 'allgemein', 'name_de' => 'Allgemein', 'name_en' => 'General', 'sort_order' => 1],
            ['technical_name' => 'technische_daten', 'name_de' => 'Technische Daten', 'name_en' => 'Technical Data', 'sort_order' => 2],
            ['technical_name' => 'abmessungen', 'name_de' => 'Abmessungen', 'name_en' => 'Dimensions', 'sort_order' => 3],
            ['technical_name' => 'marketing', 'name_de' => 'Marketing', 'name_en' => 'Marketing', 'sort_order' => 4],
            ['technical_name' => 'konnektivitaet', 'name_de' => 'Konnektivität', 'name_en' => 'Connectivity', 'sort_order' => 5],
        ];

        $created = 0;
        foreach ($types as $data) {
            $techName = self::TEST_PREFIX.$data['technical_name'];
            AttributeType::firstOrCreate(
                ['technical_name' => $techName],
                [
                    'technical_name' => $techName,
                    'name_de' => $data['name_de'],
                    'name_en' => $data['name_en'],
                    'name_json' => ['de' => $data['name_de'], 'en' => $data['name_en']],
                    'sort_order' => $data['sort_order'],
                ],
            );
            $created++;
        }

        return $created;
    }

    private function ensureAttributes(): int
    {
        if (Attribute::exists()) {
            return 0;
        }

        // Look up created foundation entities by technical_name
        $attrTypes = AttributeType::where('technical_name', 'like', self::TEST_PREFIX.'%')
            ->pluck('id', 'technical_name');
        $valueLists = ValueList::where('technical_name', 'like', self::TEST_PREFIX.'%')
            ->pluck('id', 'technical_name');
        $unitGroups = UnitGroup::where('technical_name', 'like', self::TEST_PREFIX.'%')
            ->pluck('id', 'technical_name');

        // Look up default units by abbreviation within test unit groups
        $defaultUnits = Unit::where('technical_name', 'like', self::TEST_PREFIX.'%')
            ->get()
            ->keyBy('abbreviation');

        $attributes = [
            ['technical_name' => 'beschreibung', 'name_de' => 'Beschreibung', 'name_en' => 'Description', 'data_type' => 'String', 'attribute_type' => 'allgemein', 'is_translatable' => true, 'is_mandatory' => true, 'is_searchable' => true, 'is_inheritable' => true],
            ['technical_name' => 'kurzbeschreibung', 'name_de' => 'Kurzbeschreibung', 'name_en' => 'Short Description', 'data_type' => 'String', 'attribute_type' => 'allgemein', 'is_translatable' => true, 'is_searchable' => true, 'is_inheritable' => true],
            ['technical_name' => 'herstellerland', 'name_de' => 'Herstellerland', 'name_en' => 'Country of Origin', 'data_type' => 'String', 'attribute_type' => 'allgemein', 'is_searchable' => true, 'is_inheritable' => true],
            ['technical_name' => 'garantie_jahre', 'name_de' => 'Garantie (Jahre)', 'name_en' => 'Warranty (Years)', 'data_type' => 'Number', 'attribute_type' => 'allgemein', 'is_inheritable' => true],
            ['technical_name' => 'farbe', 'name_de' => 'Farbe', 'name_en' => 'Color', 'data_type' => 'Selection', 'attribute_type' => 'technische_daten', 'value_list' => 'farben', 'is_mandatory' => true, 'is_searchable' => true],
            ['technical_name' => 'material', 'name_de' => 'Material', 'name_en' => 'Material', 'data_type' => 'Selection', 'attribute_type' => 'technische_daten', 'value_list' => 'materialien', 'is_searchable' => true, 'is_inheritable' => true],
            ['technical_name' => 'energieeffizienzklasse', 'name_de' => 'Energieeffizienzklasse', 'name_en' => 'Energy Class', 'data_type' => 'Selection', 'attribute_type' => 'technische_daten', 'value_list' => 'energieeffizienz', 'is_searchable' => true, 'is_inheritable' => true],
            ['technical_name' => 'anschluss', 'name_de' => 'Anschluss', 'name_en' => 'Connection', 'data_type' => 'Selection', 'attribute_type' => 'konnektivitaet', 'value_list' => 'anschlusstypen', 'is_multipliable' => true, 'max_multiplied' => 5, 'is_searchable' => true],
            ['technical_name' => 'bluetooth_version', 'name_de' => 'Bluetooth Version', 'name_en' => 'Bluetooth Version', 'data_type' => 'String', 'attribute_type' => 'konnektivitaet', 'is_searchable' => true],
            ['technical_name' => 'betriebssystem', 'name_de' => 'Betriebssystem', 'name_en' => 'Operating System', 'data_type' => 'Selection', 'attribute_type' => 'technische_daten', 'value_list' => 'betriebssysteme', 'is_searchable' => true],
            ['technical_name' => 'speicherkapazitaet', 'name_de' => 'Speicherkapazität', 'name_en' => 'Storage Capacity', 'data_type' => 'Number', 'attribute_type' => 'technische_daten', 'unit_group' => 'speicher', 'default_unit' => 'GB', 'is_searchable' => true],
            ['technical_name' => 'akkulaufzeit', 'name_de' => 'Akkulaufzeit', 'name_en' => 'Battery Life', 'data_type' => 'Number', 'attribute_type' => 'technische_daten', 'unit_group' => 'zeit', 'default_unit' => 'h', 'is_searchable' => true],
            ['technical_name' => 'leistungsaufnahme', 'name_de' => 'Leistungsaufnahme', 'name_en' => 'Power Consumption', 'data_type' => 'Number', 'attribute_type' => 'technische_daten', 'unit_group' => 'leistung', 'default_unit' => 'W'],
            ['technical_name' => 'gewicht', 'name_de' => 'Gewicht', 'name_en' => 'Weight', 'data_type' => 'Float', 'attribute_type' => 'abmessungen', 'unit_group' => 'gewicht', 'default_unit' => 'g'],
            ['technical_name' => 'breite', 'name_de' => 'Breite', 'name_en' => 'Width', 'data_type' => 'Float', 'attribute_type' => 'abmessungen', 'unit_group' => 'laenge', 'default_unit' => 'mm'],
            ['technical_name' => 'hoehe', 'name_de' => 'Höhe', 'name_en' => 'Height', 'data_type' => 'Float', 'attribute_type' => 'abmessungen', 'unit_group' => 'laenge', 'default_unit' => 'mm'],
            ['technical_name' => 'tiefe', 'name_de' => 'Tiefe', 'name_en' => 'Depth', 'data_type' => 'Float', 'attribute_type' => 'abmessungen', 'unit_group' => 'laenge', 'default_unit' => 'mm'],
            ['technical_name' => 'marketing_text', 'name_de' => 'Marketing-Text', 'name_en' => 'Marketing Copy', 'data_type' => 'String', 'attribute_type' => 'marketing', 'is_translatable' => true, 'is_inheritable' => true],
            ['technical_name' => 'usp', 'name_de' => 'USP', 'name_en' => 'USP', 'data_type' => 'String', 'attribute_type' => 'marketing', 'is_translatable' => true, 'is_multipliable' => true, 'max_multiplied' => 3],
        ];

        $created = 0;
        foreach ($attributes as $position => $data) {
            $techName = self::TEST_PREFIX.$data['technical_name'];

            $record = [
                'technical_name' => $techName,
                'name_de' => $data['name_de'],
                'name_en' => $data['name_en'],
                'name_json' => ['de' => $data['name_de'], 'en' => $data['name_en']],
                'data_type' => $data['data_type'],
                'attribute_type_id' => $attrTypes[self::TEST_PREFIX.$data['attribute_type']] ?? null,
                'is_translatable' => $data['is_translatable'] ?? false,
                'is_mandatory' => $data['is_mandatory'] ?? false,
                'is_searchable' => $data['is_searchable'] ?? false,
                'is_inheritable' => $data['is_inheritable'] ?? false,
                'is_multipliable' => $data['is_multipliable'] ?? false,
                'max_multiplied' => $data['max_multiplied'] ?? null,
                'status' => 'active',
                'position' => ($position + 1) * 10,
            ];

            if (isset($data['value_list'])) {
                $record['value_list_id'] = $valueLists[self::TEST_PREFIX.$data['value_list']] ?? null;
            }

            if (isset($data['unit_group'])) {
                $record['unit_group_id'] = $unitGroups[self::TEST_PREFIX.$data['unit_group']] ?? null;
            }

            if (isset($data['default_unit'])) {
                $record['default_unit_id'] = $defaultUnits[$data['default_unit']]->id ?? null;
            }

            Attribute::firstOrCreate(
                ['technical_name' => $techName],
                $record,
            );
            $created++;
        }

        return $created;
    }

    private function ensureProductRelationTypes(): int
    {
        if (ProductRelationType::exists()) {
            return 0;
        }

        $types = [
            ['technical_name' => 'zubehoer', 'name_de' => 'Zubehör', 'name_en' => 'Accessories', 'is_bidirectional' => false],
            ['technical_name' => 'cross_sell', 'name_de' => 'Cross-Sell', 'name_en' => 'Cross-Sell', 'is_bidirectional' => true],
            ['technical_name' => 'up_sell', 'name_de' => 'Up-Sell', 'name_en' => 'Up-Sell', 'is_bidirectional' => false],
        ];

        $created = 0;
        foreach ($types as $data) {
            $techName = self::TEST_PREFIX.$data['technical_name'];
            ProductRelationType::firstOrCreate(
                ['technical_name' => $techName],
                [
                    'technical_name' => $techName,
                    'name_de' => $data['name_de'],
                    'name_en' => $data['name_en'],
                    'name_json' => ['de' => $data['name_de'], 'en' => $data['name_en']],
                    'is_bidirectional' => $data['is_bidirectional'],
                ],
            );
            $created++;
        }

        return $created;
    }

    // ── Product Relations ─────────────────────────────────────────────

    /**
     * Create random relations between test products.
     */
    private function createTestProductRelations(): int
    {
        $relationTypes = ProductRelationType::all();
        if ($relationTypes->isEmpty()) {
            return 0;
        }

        $testProductIds = Product::where('sku', 'like', self::SKU_PREFIX.'%')
            ->pluck('id')
            ->toArray();

        if (count($testProductIds) < 2) {
            return 0;
        }

        $created = 0;
        // ~20% of products get relations
        $productsWithRelations = (int) ceil(count($testProductIds) * 0.2);

        $selectedProducts = array_slice($testProductIds, 0, $productsWithRelations);

        foreach ($selectedProducts as $sourceId) {
            $relationType = $relationTypes->random();
            $relationCount = mt_rand(1, min(3, count($testProductIds) - 1));
            $targetIds = collect($testProductIds)
                ->filter(fn ($id) => $id !== $sourceId)
                ->random(min($relationCount, count($testProductIds) - 1));

            foreach ($targetIds as $targetId) {
                ProductRelation::firstOrCreate(
                    [
                        'source_product_id' => $sourceId,
                        'target_product_id' => $targetId,
                        'relation_type_id' => $relationType->id,
                    ],
                    [
                        'source_product_id' => $sourceId,
                        'target_product_id' => $targetId,
                        'relation_type_id' => $relationType->id,
                        'sort_order' => $created,
                    ],
                );
                $created++;
            }
        }

        return $created;
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
            $node = ! empty($nodes) ? $nodes[array_rand($nodes)] : null;
            $manufacturer = ! empty($manufacturers) ? $manufacturers[array_rand($manufacturers)] : null;

            // Create product via Eloquent (triggers ProductObserver)
            $product = Product::create([
                'sku' => self::SKU_PREFIX.str_pad((string) $skuNumber, 6, '0', STR_PAD_LEFT),
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

            if (! empty($changedAttributeIds)) {
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
                'value_string' => 'Test-'.$attribute->technical_name.'-'.($index + 1),
            ]),
        };
    }

    private function generateTextValue(Attribute $attribute, int $index): string
    {
        $prefixes = ['Premium', 'Standard', 'Professional', 'Basic', 'Advanced',
            'Ultra', 'Classic', 'Modern', 'Eco', 'Industrial'];
        $prefix = $prefixes[$index % count($prefixes)];

        return $prefix.' '.($attribute->name_de ?? $attribute->technical_name).' '.($index + 1);
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
        return '<p><strong>'.($attribute->name_de ?? 'Beschreibung').'</strong></p>'
            .'<p>Testprodukt Nr. '.($index + 1).' mit umfangreichen Eigenschaften.</p>'
            .'<ul><li>Eigenschaft A</li><li>Eigenschaft B</li><li>Eigenschaft C</li></ul>';
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
        if (! $attribute->valueList || $attribute->valueList->entries->isEmpty()) {
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
            $suffix = $i >= count($categories) ? ' '.($i + 1) : '';

            $rootNode = HierarchyNode::create([
                'hierarchy_id' => $hierarchy->id,
                'parent_node_id' => null,
                'name_de' => $catName.$suffix,
                'name_en' => $catName.$suffix,
                'name_json' => ['de' => $catName.$suffix, 'en' => $catName.$suffix],
                'path' => '',
                'depth' => 0,
                'sort_order' => $i,
                'is_active' => true,
            ]);
            $rootNode->update(['path' => $rootNode->id.'/']);
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
                'path' => $parent->path.'/',
                'depth' => $currentDepth,
                'sort_order' => $i,
                'is_active' => true,
            ]);
            $childNode->update(['path' => $parent->path.$childNode->id.'/']);
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

        return $adj.' '.$noun.' '.$ser.' '.($index + 1);
    }

    private function generateEan(int $index): string
    {
        $ean12 = '400'.str_pad((string) ($index + 1), 9, '0', STR_PAD_LEFT);

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $ean12[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $ean12.((10 - ($sum % 10)) % 10);
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
