<?php

declare(strict_types=1);

namespace Tests\Feature\Inheritance;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Services\Inheritance\AttributeValueResolver;
use App\Services\Inheritance\HierarchyInheritanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OutputHierarchyInheritanceTest extends TestCase
{
    use RefreshDatabase;

    private AttributeValueResolver $resolver;
    private HierarchyInheritanceService $hierarchyService;
    private ProductType $productType;
    private Hierarchy $masterHierarchy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(AttributeValueResolver::class);
        $this->hierarchyService = app(HierarchyInheritanceService::class);

        $this->productType = ProductType::create([
            'id' => (string) Str::uuid(),
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

        $this->masterHierarchy = Hierarchy::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'master',
            'name_de' => 'Master',
            'hierarchy_type' => 'master',
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createOutputHierarchy(string $technicalName, string $nameDe): Hierarchy
    {
        return Hierarchy::create([
            'id' => (string) Str::uuid(),
            'technical_name' => $technicalName,
            'name_de' => $nameDe,
            'hierarchy_type' => 'output',
        ]);
    }

    private function createAttribute(string $technicalName, bool $isTranslatable = false): Attribute
    {
        return Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => $technicalName,
            'name_de' => ucfirst($technicalName),
            'data_type' => 'String',
            'is_translatable' => $isTranslatable,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);
    }

    private function createProduct(string $sku, ?string $nodeId = null): Product
    {
        return Product::create([
            'id' => (string) Str::uuid(),
            'product_type_id' => $this->productType->id,
            'sku' => $sku,
            'name' => "Product {$sku}",
            'status' => 'active',
            'product_type_ref' => 'product',
            'master_hierarchy_node_id' => $nodeId,
        ]);
    }

    private function createNode(
        Hierarchy $hierarchy,
        string $name,
        ?string $parentId = null,
        string $path = '/',
        int $depth = 0,
    ): HierarchyNode {
        // Create with a placeholder path first, then update with the generated UUID
        $node = HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'parent_node_id' => $parentId,
            'name_de' => $name,
            'path' => $path, // temporary
            'depth' => $depth,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        // Now set the correct path using the auto-generated ID
        $node->update(['path' => $path . $node->id . '/']);

        return $node->fresh();
    }

    private function assignAttributeToNode(
        HierarchyNode $node,
        Attribute $attribute,
        string $collectionName = 'Default',
        int $collectionSort = 10,
        int $attributeSort = 10,
    ): HierarchyNodeAttributeAssignment {
        return HierarchyNodeAttributeAssignment::create([
            'id' => (string) Str::uuid(),
            'hierarchy_node_id' => $node->id,
            'attribute_id' => $attribute->id,
            'collection_name' => $collectionName,
            'collection_sort' => $collectionSort,
            'attribute_sort' => $attributeSort,
            'dont_inherit' => false,
        ]);
    }

    private function assignProductToOutputNode(
        Product $product,
        HierarchyNode $node,
        int $sortOrder = 0,
    ): OutputHierarchyProductAssignment {
        return OutputHierarchyProductAssignment::create([
            'id' => (string) Str::uuid(),
            'hierarchy_node_id' => $node->id,
            'product_id' => $product->id,
            'sort_order' => $sortOrder,
        ]);
    }

    private function setMasterValue(
        Product $product,
        Attribute $attribute,
        string $value,
        ?string $language = null,
    ): ProductAttributeValue {
        return ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => $value,
            'language' => $language,
            'multiplied_index' => 0,
            'is_inherited' => false,
            'output_hierarchy_id' => null,
        ]);
    }

    private function setChannelValue(
        Product $product,
        Attribute $attribute,
        string $outputHierarchyId,
        string $value,
        ?string $language = null,
    ): ProductAttributeValue {
        return ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => $value,
            'language' => $language,
            'multiplied_index' => 0,
            'is_inherited' => false,
            'output_hierarchy_id' => $outputHierarchyId,
        ]);
    }

    // -----------------------------------------------------------------------
    // HierarchyInheritanceService: getProductOutputHierarchyAttributes
    // -----------------------------------------------------------------------

    /** @test */
    public function get_output_hierarchy_attributes_returns_empty_for_unassigned_product(): void
    {
        $product = $this->createProduct('P1');

        $result = $this->hierarchyService->getProductOutputHierarchyAttributes($product);

        $this->assertTrue($result->isEmpty());
    }

    /** @test */
    public function get_output_hierarchy_attributes_returns_attributes_for_assigned_product(): void
    {
        $website = $this->createOutputHierarchy('website', 'Website');
        $webNode = $this->createNode($website, 'Shop');
        $attr = $this->createAttribute('seo_title');

        $this->assignAttributeToNode($webNode, $attr, 'SEO', 10, 10);

        $product = $this->createProduct('P1');
        $this->assignProductToOutputNode($product, $webNode);

        $result = $this->hierarchyService->getProductOutputHierarchyAttributes($product);

        $this->assertCount(1, $result);
        $this->assertTrue($result->has($website->id));

        $hierarchyData = $result->get($website->id);
        $this->assertEquals($website->id, $hierarchyData['hierarchy']->id);
        $this->assertCount(1, $hierarchyData['attributes']);
        $this->assertEquals($attr->id, $hierarchyData['attributes']->first()->attribute_id);
    }

    /** @test */
    public function get_output_hierarchy_attributes_groups_by_hierarchy(): void
    {
        $website = $this->createOutputHierarchy('website', 'Website');
        $catalog = $this->createOutputHierarchy('catalog', 'Druckkatalog');

        $webNode = $this->createNode($website, 'Shop');
        $catNode = $this->createNode($catalog, 'Katalog 2026');

        $webAttr = $this->createAttribute('seo_title');
        $catAttr = $this->createAttribute('print_description');

        $this->assignAttributeToNode($webNode, $webAttr);
        $this->assignAttributeToNode($catNode, $catAttr);

        $product = $this->createProduct('P1');
        $this->assignProductToOutputNode($product, $webNode);
        $this->assignProductToOutputNode($product, $catNode);

        $result = $this->hierarchyService->getProductOutputHierarchyAttributes($product);

        $this->assertCount(2, $result);
        $this->assertTrue($result->has($website->id));
        $this->assertTrue($result->has($catalog->id));

        $this->assertEquals($webAttr->id, $result->get($website->id)['attributes']->first()->attribute_id);
        $this->assertEquals($catAttr->id, $result->get($catalog->id)['attributes']->first()->attribute_id);
    }

    /** @test */
    public function output_hierarchy_inherits_attributes_from_ancestor_nodes(): void
    {
        $website = $this->createOutputHierarchy('website', 'Website');
        $rootNode = $this->createNode($website, 'Root', depth: 0);
        $childNode = $this->createNode($website, 'Child', $rootNode->id, $rootNode->path, 1);

        $rootAttr = $this->createAttribute('global_flag');
        $childAttr = $this->createAttribute('category_flag');

        $this->assignAttributeToNode($rootNode, $rootAttr, 'Global', 1, 1);
        $this->assignAttributeToNode($childNode, $childAttr, 'Category', 2, 1);

        $product = $this->createProduct('P1');
        $this->assignProductToOutputNode($product, $childNode);

        // Verify node paths are correct for inheritance
        $this->assertStringContainsString($rootNode->id, $childNode->path);

        $result = $this->hierarchyService->getProductOutputHierarchyAttributes($product);
        $attributes = $result->get($website->id)['attributes'];

        // Should have both root-level and child-level attributes
        $this->assertCount(2, $attributes, sprintf(
            'Expected 2 attributes, got %d. childNode path: %s, rootNode id: %s',
            $attributes->count(),
            $childNode->path,
            $rootNode->id,
        ));
        $attrIds = $attributes->pluck('attribute_id')->all();
        $this->assertContains($rootAttr->id, $attrIds);
        $this->assertContains($childAttr->id, $attrIds);
    }

    // -----------------------------------------------------------------------
    // AttributeValueResolver: Output Hierarchy
    // -----------------------------------------------------------------------

    /** @test */
    public function resolve_for_output_hierarchy_returns_channel_value(): void
    {
        $website = $this->createOutputHierarchy('website', 'Website');
        $webNode = $this->createNode($website, 'Shop');
        $attr = $this->createAttribute('seo_title');

        $this->assignAttributeToNode($webNode, $attr);

        $product = $this->createProduct('P1');
        $this->assignProductToOutputNode($product, $webNode);
        $this->setChannelValue($product, $attr, $website->id, 'SEO Titel Website');

        $result = $this->resolver->resolveForOutputHierarchy($product, $attr, $website->id);

        $this->assertNotNull($result);
        $this->assertEquals('SEO Titel Website', $result->value);
        $this->assertEquals('own', $result->source);
        $this->assertEquals($website->id, $result->outputHierarchyId);
    }

    /** @test */
    public function resolve_for_output_hierarchy_falls_back_to_master_value(): void
    {
        $website = $this->createOutputHierarchy('website', 'Website');
        $webNode = $this->createNode($website, 'Shop');
        $attr = $this->createAttribute('description');

        $this->assignAttributeToNode($webNode, $attr);

        $product = $this->createProduct('P1');
        $this->assignProductToOutputNode($product, $webNode);

        // Only master value exists, no channel-specific value
        $this->setMasterValue($product, $attr, 'Master Description');

        $result = $this->resolver->resolveForOutputHierarchy($product, $attr, $website->id);

        $this->assertNotNull($result);
        $this->assertEquals('Master Description', $result->value);
        $this->assertEquals('master_fallback', $result->source);
        $this->assertTrue($result->isMasterFallback());
        $this->assertEquals($website->id, $result->outputHierarchyId);
    }

    /** @test */
    public function resolve_for_output_hierarchy_channel_value_overrides_master(): void
    {
        $website = $this->createOutputHierarchy('website', 'Website');
        $webNode = $this->createNode($website, 'Shop');
        $attr = $this->createAttribute('description');

        $this->assignAttributeToNode($webNode, $attr);

        $product = $this->createProduct('P1');
        $this->assignProductToOutputNode($product, $webNode);

        $this->setMasterValue($product, $attr, 'Master Description');
        $this->setChannelValue($product, $attr, $website->id, 'Website Description');

        $result = $this->resolver->resolveForOutputHierarchy($product, $attr, $website->id);

        $this->assertNotNull($result);
        $this->assertEquals('Website Description', $result->value);
        $this->assertEquals('own', $result->source);
        $this->assertFalse($result->isMasterFallback());
    }

    /** @test */
    public function resolve_for_output_hierarchy_returns_null_when_no_value(): void
    {
        $website = $this->createOutputHierarchy('website', 'Website');
        $attr = $this->createAttribute('empty_attr');

        $product = $this->createProduct('P1');

        $result = $this->resolver->resolveForOutputHierarchy($product, $attr, $website->id);

        $this->assertNull($result);
    }

    /** @test */
    public function same_attribute_different_values_per_channel(): void
    {
        $website = $this->createOutputHierarchy('website', 'Website');
        $catalog = $this->createOutputHierarchy('catalog', 'Druckkatalog');
        $attr = $this->createAttribute('short_text');

        $product = $this->createProduct('P1');

        $this->setMasterValue($product, $attr, 'Master Text');
        $this->setChannelValue($product, $attr, $website->id, 'Web Short Text');
        $this->setChannelValue($product, $attr, $catalog->id, 'Print Short Text');

        $webResult = $this->resolver->resolveForOutputHierarchy($product, $attr, $website->id);
        $catResult = $this->resolver->resolveForOutputHierarchy($product, $attr, $catalog->id);

        $this->assertEquals('Web Short Text', $webResult->value);
        $this->assertEquals('Print Short Text', $catResult->value);
        $this->assertEquals('own', $webResult->source);
        $this->assertEquals('own', $catResult->source);
    }

    /** @test */
    public function resolve_all_for_output_hierarchy_returns_all_attributes(): void
    {
        $website = $this->createOutputHierarchy('website', 'Website');
        $webNode = $this->createNode($website, 'Shop');

        $attr1 = $this->createAttribute('seo_title');
        $attr2 = $this->createAttribute('seo_description');

        $this->assignAttributeToNode($webNode, $attr1, 'SEO', 10, 10);
        $this->assignAttributeToNode($webNode, $attr2, 'SEO', 10, 20);

        $product = $this->createProduct('P1');
        $this->assignProductToOutputNode($product, $webNode);

        $this->setChannelValue($product, $attr1, $website->id, 'Title');
        // attr2 has no channel value, but master value exists
        $this->setMasterValue($product, $attr2, 'Master Description');

        $results = $this->resolver->resolveAllForOutputHierarchy($product, $website->id);

        $this->assertCount(2, $results);

        $first = $results->first();
        $this->assertEquals($attr1->id, $first->attributeId);
        $this->assertEquals('Title', $first->value);
        $this->assertEquals('own', $first->source);

        $second = $results->last();
        $this->assertEquals($attr2->id, $second->attributeId);
        $this->assertEquals('Master Description', $second->value);
        $this->assertEquals('master_fallback', $second->source);
    }

    // -----------------------------------------------------------------------
    // ResolvedValue DTO: master_fallback source
    // -----------------------------------------------------------------------

    /** @test */
    public function resolved_value_master_fallback_is_inherited(): void
    {
        $rv = new \App\Services\Inheritance\ResolvedValue(
            attributeId: 'a',
            attributeTechnicalName: 'x',
            value: 'v',
            source: 'master_fallback',
            outputHierarchyId: 'h1',
        );

        $this->assertTrue($rv->isInherited());
        $this->assertTrue($rv->isMasterFallback());
        $this->assertTrue($rv->hasValue());
        $this->assertEquals('h1', $rv->toArray()['output_hierarchy_id']);
    }

    // -----------------------------------------------------------------------
    // Database: output_hierarchy_id unique constraint
    // -----------------------------------------------------------------------

    /** @test */
    public function can_store_same_attribute_for_master_and_channel(): void
    {
        $website = $this->createOutputHierarchy('website', 'Website');
        $attr = $this->createAttribute('description');
        $product = $this->createProduct('P1');

        // Master value (output_hierarchy_id = null)
        $masterPav = $this->setMasterValue($product, $attr, 'Master');

        // Channel value (output_hierarchy_id = website)
        $channelPav = $this->setChannelValue($product, $attr, $website->id, 'Website');

        $this->assertDatabaseCount('product_attribute_values', 2);
        $this->assertNotEquals($masterPav->id, $channelPav->id);
    }

    /** @test */
    public function can_store_same_attribute_for_different_channels(): void
    {
        $website = $this->createOutputHierarchy('website', 'Website');
        $catalog = $this->createOutputHierarchy('catalog', 'Druckkatalog');
        $attr = $this->createAttribute('title');
        $product = $this->createProduct('P1');

        $this->setChannelValue($product, $attr, $website->id, 'Web Title');
        $this->setChannelValue($product, $attr, $catalog->id, 'Print Title');

        $this->assertDatabaseCount('product_attribute_values', 2);
    }
}
