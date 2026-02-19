<?php

declare(strict_types=1);

namespace Tests\Feature\Inheritance;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\Product;
use App\Models\ProductType;
use App\Services\Inheritance\HierarchyInheritanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HierarchyInheritanceTest extends TestCase
{
    use RefreshDatabase;

    private HierarchyInheritanceService $service;
    private Hierarchy $hierarchy;
    private ProductType $productType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HierarchyInheritanceService::class);

        $this->hierarchy = Hierarchy::forceCreate([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'technical_name' => 'master',
            'name_de' => 'Master-Hierarchie',
            'hierarchy_type' => 'master',
        ]);

        $this->productType = ProductType::forceCreate([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'technical_name' => 'physical_product',
            'name_de' => 'Physisches Produkt',
            'has_variants' => true,
            'has_ean' => true,
            'has_prices' => true,
            'has_media' => true,
            'has_stock' => true,
            'has_physical_dimensions' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    // -----------------------------------------------------------------------
    // Helper methods
    // -----------------------------------------------------------------------

    private function createNode(
        string $name,
        ?string $parentId = null,
        string $path = '/',
        int $depth = 0,
    ): HierarchyNode {
        $id = (string) \Illuminate\Support\Str::uuid();

        return HierarchyNode::forceCreate([
            'id' => $id,
            'hierarchy_id' => $this->hierarchy->id,
            'parent_node_id' => $parentId,
            'name_de' => $name,
            'path' => $path . $id . '/',
            'depth' => $depth,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createAttribute(string $technicalName, string $nameDe): Attribute
    {
        return Attribute::forceCreate([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'technical_name' => $technicalName,
            'name_de' => $nameDe,
            'data_type' => 'String',
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);
    }

    private function assignAttribute(
        HierarchyNode $node,
        Attribute $attribute,
        string $collection = 'Default',
        int $collectionSort = 10,
        int $attributeSort = 10,
        bool $dontInherit = false,
    ): HierarchyNodeAttributeAssignment {
        return HierarchyNodeAttributeAssignment::forceCreate([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'hierarchy_node_id' => $node->id,
            'attribute_id' => $attribute->id,
            'collection_name' => $collection,
            'collection_sort' => $collectionSort,
            'attribute_sort' => $attributeSort,
            'dont_inherit' => $dontInherit,
        ]);
    }

    private function createProduct(string $sku, ?string $nodeId = null): Product
    {
        return Product::forceCreate([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'product_type_id' => $this->productType->id,
            'sku' => $sku,
            'name' => "Product {$sku}",
            'status' => 'active',
            'product_type_ref' => 'product',
            'master_hierarchy_node_id' => $nodeId,
        ]);
    }

    // -----------------------------------------------------------------------
    // Tests: 3-level hierarchy attribute inheritance
    // -----------------------------------------------------------------------

    /** @test */
    public function it_returns_empty_for_node_without_attributes(): void
    {
        $node = $this->createNode('Empty Node');
        $result = $this->service->getEffectiveAttributes($node);

        $this->assertTrue($result->isEmpty());
    }

    /** @test */
    public function it_returns_own_attributes_for_root_node(): void
    {
        $root = $this->createNode('Root');
        $attrName = $this->createAttribute('product-name', 'Produktname');
        $attrSku = $this->createAttribute('product-sku', 'SKU');

        $this->assignAttribute($root, $attrName, 'Stammdaten', 10, 10);
        $this->assignAttribute($root, $attrSku, 'Stammdaten', 10, 20);

        $result = $this->service->getEffectiveAttributes($root);

        $this->assertCount(2, $result);
        $ids = $result->pluck('attribute_id')->toArray();
        $this->assertContains($attrName->id, $ids);
        $this->assertContains($attrSku->id, $ids);
    }

    /** @test */
    public function it_inherits_attributes_from_3_level_hierarchy(): void
    {
        // Level 0: Elektrowerkzeuge (3 attributes)
        $root = $this->createNode('Elektrowerkzeuge');
        $attrName = $this->createAttribute('product-name', 'Produktname');
        $attrSku = $this->createAttribute('product-sku', 'SKU');
        $attrWeight = $this->createAttribute('product-weight', 'Gewicht');

        $this->assignAttribute($root, $attrName, 'Stammdaten', 10, 10);
        $this->assignAttribute($root, $attrSku, 'Stammdaten', 10, 20);
        $this->assignAttribute($root, $attrWeight, 'Technik', 20, 10);

        // Level 1: Akkubohrschrauber (+2 attributes = 5 total)
        $child = $this->createNode('Akkubohrschrauber', $root->id, $root->path, 1);
        $attrTorque = $this->createAttribute('torque', 'Drehmoment');
        $attrRpm = $this->createAttribute('rpm', 'Drehzahl');

        $this->assignAttribute($child, $attrTorque, 'Technik', 20, 20);
        $this->assignAttribute($child, $attrRpm, 'Technik', 20, 30);

        // Level 2: mit Akku (+2 attributes = 7 total)
        $grandchild = $this->createNode('mit Akku', $child->id, $child->path, 2);
        $attrCap = $this->createAttribute('battery-capacity', 'Akkukapazität');
        $attrCharge = $this->createAttribute('charge-time', 'Ladedauer');

        $this->assignAttribute($grandchild, $attrCap, 'Akku', 30, 10);
        $this->assignAttribute($grandchild, $attrCharge, 'Akku', 30, 20);

        // Grandchild should have all 7 attributes
        $result = $this->service->getEffectiveAttributes($grandchild);

        $this->assertCount(7, $result);

        $ids = $result->pluck('attribute_id')->toArray();
        $this->assertContains($attrName->id, $ids);
        $this->assertContains($attrSku->id, $ids);
        $this->assertContains($attrWeight->id, $ids);
        $this->assertContains($attrTorque->id, $ids);
        $this->assertContains($attrRpm->id, $ids);
        $this->assertContains($attrCap->id, $ids);
        $this->assertContains($attrCharge->id, $ids);
    }

    /** @test */
    public function it_sorts_by_collection_sort_then_attribute_sort(): void
    {
        $root = $this->createNode('Root');
        $attrA = $this->createAttribute('attr-a', 'A');
        $attrB = $this->createAttribute('attr-b', 'B');
        $attrC = $this->createAttribute('attr-c', 'C');

        // Out of order: C(20,10), A(10,20), B(10,10)
        $this->assignAttribute($root, $attrC, 'Group2', 20, 10);
        $this->assignAttribute($root, $attrA, 'Group1', 10, 20);
        $this->assignAttribute($root, $attrB, 'Group1', 10, 10);

        $result = $this->service->getEffectiveAttributes($root);

        $sortedIds = $result->pluck('attribute_id')->toArray();
        // Expected order: B(10,10) → A(10,20) → C(20,10)
        $this->assertEquals($attrB->id, $sortedIds[0]);
        $this->assertEquals($attrA->id, $sortedIds[1]);
        $this->assertEquals($attrC->id, $sortedIds[2]);
    }

    // -----------------------------------------------------------------------
    // Tests: dont_inherit flag
    // -----------------------------------------------------------------------

    /** @test */
    public function dont_inherit_prevents_attribute_from_propagating_to_children(): void
    {
        $root = $this->createNode('Root');
        $attrName = $this->createAttribute('name', 'Name');
        $attrInternal = $this->createAttribute('internal-note', 'Interner Vermerk');

        $this->assignAttribute($root, $attrName, 'Stammdaten', 10, 10, dontInherit: false);
        $this->assignAttribute($root, $attrInternal, 'Intern', 99, 10, dontInherit: true);

        $child = $this->createNode('Child', $root->id, $root->path, 1);

        // Root itself should have both
        $rootAttrs = $this->service->getEffectiveAttributes($root);
        $this->assertCount(2, $rootAttrs);

        // Child should only have 'name', NOT 'internal-note'
        $childAttrs = $this->service->getEffectiveAttributes($child);
        $this->assertCount(1, $childAttrs);
        $this->assertEquals($attrName->id, $childAttrs->first()->attribute_id);
    }

    /** @test */
    public function dont_inherit_blocks_at_one_level_but_node_itself_sees_attribute(): void
    {
        $root = $this->createNode('Root');
        $mid = $this->createNode('Mid', $root->id, $root->path, 1);
        $leaf = $this->createNode('Leaf', $mid->id, $mid->path, 2);

        $attrGlobal = $this->createAttribute('global', 'Global');
        $attrMidOnly = $this->createAttribute('mid-only', 'Nur für Mitte');

        $this->assignAttribute($root, $attrGlobal, 'Default', 10, 10);
        $this->assignAttribute($mid, $attrMidOnly, 'Spezial', 20, 10, dontInherit: true);

        // Mid sees both: global (inherited) + mid-only (own, dont_inherit)
        $midAttrs = $this->service->getEffectiveAttributes($mid);
        $this->assertCount(2, $midAttrs);

        // Leaf sees only global — mid-only is blocked by dont_inherit
        $leafAttrs = $this->service->getEffectiveAttributes($leaf);
        $this->assertCount(1, $leafAttrs);
        $this->assertEquals($attrGlobal->id, $leafAttrs->first()->attribute_id);
    }

    // -----------------------------------------------------------------------
    // Tests: Product attribute inheritance via hierarchy
    // -----------------------------------------------------------------------

    /** @test */
    public function product_inherits_all_hierarchy_attributes(): void
    {
        $root = $this->createNode('Root');
        $child = $this->createNode('Child', $root->id, $root->path, 1);

        $attr1 = $this->createAttribute('a1', 'Attr 1');
        $attr2 = $this->createAttribute('a2', 'Attr 2');

        $this->assignAttribute($root, $attr1, 'Default', 10, 10);
        $this->assignAttribute($child, $attr2, 'Default', 10, 20);

        $product = $this->createProduct('PROD-001', $child->id);

        $result = $this->service->getProductAttributes($product);

        $this->assertCount(2, $result);
    }

    /** @test */
    public function product_without_hierarchy_returns_empty(): void
    {
        $product = $this->createProduct('PROD-NOHIER');
        $result = $this->service->getProductAttributes($product);

        $this->assertTrue($result->isEmpty());
    }

    // -----------------------------------------------------------------------
    // Tests: Deeper node overrides same attribute from ancestor
    // -----------------------------------------------------------------------

    /** @test */
    public function deeper_node_overrides_same_attribute_from_ancestor(): void
    {
        $root = $this->createNode('Root');
        $child = $this->createNode('Child', $root->id, $root->path, 1);

        $attr = $this->createAttribute('shared-attr', 'Shared');

        // Both nodes assign the same attribute — child should override
        $this->assignAttribute($root, $attr, 'GroupA', 10, 10);
        $this->assignAttribute($child, $attr, 'GroupB', 20, 10);

        $result = $this->service->getEffectiveAttributes($child);

        // Should be 1 attribute, not 2 (deduplicated)
        $this->assertCount(1, $result);
        // Should have the child's collection info (override)
        $this->assertEquals('GroupB', $result->first()->collection_name);
    }

    // -----------------------------------------------------------------------
    // Tests: Cache invalidation
    // -----------------------------------------------------------------------

    /** @test */
    public function invalidate_node_cache_clears_cached_attributes(): void
    {
        $root = $this->createNode('Root');
        $attr = $this->createAttribute('cached-attr', 'Cached');
        $this->assignAttribute($root, $attr, 'Default', 10, 10);

        // Prime the cache
        $result1 = $this->service->getEffectiveAttributes($root);
        $this->assertCount(1, $result1);

        // Invalidate
        $this->service->invalidateNodeCache($root);

        // Add another attribute (simulating external change)
        $attr2 = $this->createAttribute('new-attr', 'New');
        $this->assignAttribute($root, $attr2, 'Default', 10, 20);

        // Should now return fresh data
        $result2 = $this->service->getEffectiveAttributes($root);
        $this->assertCount(2, $result2);
    }

    /** @test */
    public function get_affected_product_ids_returns_all_products_under_node(): void
    {
        $root = $this->createNode('Root');
        $child = $this->createNode('Child', $root->id, $root->path, 1);

        $p1 = $this->createProduct('P1', $root->id);
        $p2 = $this->createProduct('P2', $child->id);
        $p3 = $this->createProduct('P3'); // No hierarchy

        $affected = $this->service->getAffectedProductIds($root);

        $this->assertCount(2, $affected);
        $this->assertContains($p1->id, $affected->toArray());
        $this->assertContains($p2->id, $affected->toArray());
        $this->assertNotContains($p3->id, $affected->toArray());
    }
}
