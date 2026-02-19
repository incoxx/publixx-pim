<?php

declare(strict_types=1);

namespace Tests\Feature\Inheritance;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Models\VariantInheritanceRule;
use App\Services\Inheritance\AttributeValueResolver;
use App\Services\Inheritance\ResolvedValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttributeValueResolverTest extends TestCase
{
    use RefreshDatabase;

    private AttributeValueResolver $resolver;
    private ProductType $productType;
    private Hierarchy $hierarchy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(AttributeValueResolver::class);

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

        $this->hierarchy = Hierarchy::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'master',
            'name_de' => 'Master',
            'hierarchy_type' => 'master',
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createAttribute(
        string $technicalName,
        bool $isTranslatable = false,
    ): Attribute {
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

    private function createProduct(
        string $sku,
        ?string $parentId = null,
        ?string $nodeId = null,
    ): Product {
        return Product::create([
            'id' => (string) Str::uuid(),
            'product_type_id' => $this->productType->id,
            'sku' => $sku,
            'name' => "Product {$sku}",
            'status' => 'active',
            'product_type_ref' => $parentId ? 'variant' : 'product',
            'parent_product_id' => $parentId,
            'master_hierarchy_node_id' => $nodeId,
        ]);
    }

    private function createNode(
        string $name,
        ?string $parentId = null,
        string $path = '/',
        int $depth = 0,
    ): HierarchyNode {
        $id = (string) Str::uuid();

        return HierarchyNode::create([
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

    private function setAttributeValue(
        Product $product,
        Attribute $attribute,
        string $value,
        ?string $language = null,
        bool $isInherited = false,
        ?string $inheritedFromNodeId = null,
        ?string $inheritedFromProductId = null,
    ): ProductAttributeValue {
        return ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => $value,
            'language' => $language,
            'multiplied_index' => 0,
            'is_inherited' => $isInherited,
            'inherited_from_node_id' => $inheritedFromNodeId,
            'inherited_from_product_id' => $inheritedFromProductId,
        ]);
    }

    private function setInheritanceRule(
        Product $variant,
        Attribute $attribute,
        string $mode,
    ): void {
        VariantInheritanceRule::create([
            'id' => (string) Str::uuid(),
            'product_id' => $variant->id,
            'attribute_id' => $attribute->id,
            'inheritance_mode' => $mode,
        ]);
    }

    // -----------------------------------------------------------------------
    // Stage 1: Own value
    // -----------------------------------------------------------------------

    /** @test */
    public function stage1_returns_own_value_when_present(): void
    {
        $product = $this->createProduct('P1');
        $attr = $this->createAttribute('color');
        $this->setAttributeValue($product, $attr, 'Rot');

        $result = $this->resolver->resolve($product, $attr);

        $this->assertNotNull($result);
        $this->assertEquals('Rot', $result->value);
        $this->assertEquals('own', $result->source);
        $this->assertFalse($result->isInherited());
    }

    /** @test */
    public function stage1_returns_language_specific_own_value(): void
    {
        $product = $this->createProduct('P1');
        $attr = $this->createAttribute('description', isTranslatable: true);

        $this->setAttributeValue($product, $attr, 'Deutsche Beschreibung', 'de');
        $this->setAttributeValue($product, $attr, 'English description', 'en');

        $resultDe = $this->resolver->resolve($product, $attr, 'de');
        $resultEn = $this->resolver->resolve($product, $attr, 'en');

        $this->assertEquals('Deutsche Beschreibung', $resultDe->value);
        $this->assertEquals('English description', $resultEn->value);
    }

    // -----------------------------------------------------------------------
    // Stage 2: Variant inheritance
    // -----------------------------------------------------------------------

    /** @test */
    public function stage2_variant_inherits_from_parent_by_default(): void
    {
        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attr = $this->createAttribute('weight');

        $this->setAttributeValue($parent, $attr, '2.5 kg');
        // Variant has no own value, no explicit rule → default is inherit

        $result = $this->resolver->resolve($variant, $attr);

        $this->assertNotNull($result);
        $this->assertEquals('2.5 kg', $result->value);
        $this->assertEquals('variant_inheritance', $result->source);
        $this->assertTrue($result->isInherited());
        $this->assertEquals($parent->id, $result->inheritedFromProductId);
    }

    /** @test */
    public function stage2_variant_does_not_inherit_when_override(): void
    {
        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attr = $this->createAttribute('price');

        $this->setAttributeValue($parent, $attr, '99.99');
        $this->setInheritanceRule($variant, $attr, 'override');
        // Variant has override but no own value → should not take parent's value

        $result = $this->resolver->resolve($variant, $attr);

        // Override mode + no own value → Stage 2 returns null, falls through
        $this->assertNull($result);
    }

    /** @test */
    public function stage2_variant_own_value_takes_priority_over_parent(): void
    {
        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attr = $this->createAttribute('name');

        $this->setAttributeValue($parent, $attr, 'Parent Name');
        $this->setAttributeValue($variant, $attr, 'Variant Name');

        $result = $this->resolver->resolve($variant, $attr);

        $this->assertNotNull($result);
        $this->assertEquals('Variant Name', $result->value);
        $this->assertEquals('own', $result->source);
        $this->assertFalse($result->isInherited());
    }

    /** @test */
    public function stage2_explicit_inherit_rule_inherits_from_parent(): void
    {
        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attr = $this->createAttribute('material');

        $this->setAttributeValue($parent, $attr, 'Stahl');
        $this->setInheritanceRule($variant, $attr, 'inherit');

        $result = $this->resolver->resolve($variant, $attr);

        $this->assertNotNull($result);
        $this->assertEquals('Stahl', $result->value);
        $this->assertEquals('variant_inheritance', $result->source);
    }

    // -----------------------------------------------------------------------
    // Stage 3: Hierarchy default
    // -----------------------------------------------------------------------

    /** @test */
    public function stage3_falls_back_to_hierarchy_default(): void
    {
        $node = $this->createNode('Werkzeuge');
        $product = $this->createProduct('P1', nodeId: $node->id);
        $attr = $this->createAttribute('category');

        // No own value, but a hierarchy-inherited value exists
        $this->setAttributeValue(
            $product,
            $attr,
            'Werkzeuge',
            isInherited: true,
            inheritedFromNodeId: $node->id,
        );

        // Use a fresh product without own (non-inherited) value
        $product2 = $this->createProduct('P2', nodeId: $node->id);
        ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product2->id,
            'attribute_id' => $attr->id,
            'value_string' => 'Werkzeuge-Default',
            'language' => null,
            'multiplied_index' => 0,
            'is_inherited' => true,
            'inherited_from_node_id' => $node->id,
        ]);

        $result = $this->resolver->resolve($product2, $attr);

        $this->assertNotNull($result);
        $this->assertEquals('Werkzeuge-Default', $result->value);
        $this->assertEquals('hierarchy_inheritance', $result->source);
        $this->assertEquals($node->id, $result->inheritedFromNodeId);
    }

    // -----------------------------------------------------------------------
    // Stage 4: NULL (no value)
    // -----------------------------------------------------------------------

    /** @test */
    public function stage4_returns_null_when_no_value_found(): void
    {
        $product = $this->createProduct('P1');
        $attr = $this->createAttribute('nonexistent');

        $result = $this->resolver->resolve($product, $attr);

        $this->assertNull($result);
    }

    // -----------------------------------------------------------------------
    // Full cascade integration
    // -----------------------------------------------------------------------

    /** @test */
    public function full_cascade_variant_with_hierarchy_and_parent(): void
    {
        // Setup hierarchy
        $node = $this->createNode('Elektro');
        $parent = $this->createProduct('DRILL', nodeId: $node->id);
        $variant = $this->createProduct('DRILL-2AH', $parent->id);

        $attrName = $this->createAttribute('name');
        $attrWeight = $this->createAttribute('weight');
        $attrCategory = $this->createAttribute('category');
        $attrEmpty = $this->createAttribute('empty-attr');

        // Variant has own name (override)
        $this->setAttributeValue($variant, $attrName, 'Drill 2Ah');
        $this->setInheritanceRule($variant, $attrName, 'override');

        // Weight comes from parent (inherit)
        $this->setAttributeValue($parent, $attrWeight, '1.8 kg');

        // Category is a hierarchy default on the parent
        ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $parent->id,
            'attribute_id' => $attrCategory->id,
            'value_string' => 'Elektrowerkzeuge',
            'language' => null,
            'multiplied_index' => 0,
            'is_inherited' => true,
            'inherited_from_node_id' => $node->id,
        ]);

        // Stage 1: own value
        $resName = $this->resolver->resolve($variant, $attrName);
        $this->assertEquals('own', $resName->source);
        $this->assertEquals('Drill 2Ah', $resName->value);

        // Stage 2: variant inheritance
        $resWeight = $this->resolver->resolve($variant, $attrWeight);
        $this->assertEquals('variant_inheritance', $resWeight->source);
        $this->assertEquals('1.8 kg', $resWeight->value);

        // Stage 4: null for empty-attr
        $resEmpty = $this->resolver->resolve($variant, $attrEmpty);
        $this->assertNull($resEmpty);
    }

    // -----------------------------------------------------------------------
    // resolveAll
    // -----------------------------------------------------------------------

    /** @test */
    public function resolve_all_returns_sorted_collection(): void
    {
        $node = $this->createNode('Root');
        $product = $this->createProduct('P1', nodeId: $node->id);

        $attr1 = $this->createAttribute('attr-a');
        $attr2 = $this->createAttribute('attr-b');

        // Assign attributes to hierarchy node
        HierarchyNodeAttributeAssignment::create([
            'id' => (string) Str::uuid(),
            'hierarchy_node_id' => $node->id,
            'attribute_id' => $attr1->id,
            'collection_name' => 'Group1',
            'collection_sort' => 10,
            'attribute_sort' => 10,
            'dont_inherit' => false,
        ]);
        HierarchyNodeAttributeAssignment::create([
            'id' => (string) Str::uuid(),
            'hierarchy_node_id' => $node->id,
            'attribute_id' => $attr2->id,
            'collection_name' => 'Group1',
            'collection_sort' => 10,
            'attribute_sort' => 20,
            'dont_inherit' => false,
        ]);

        $this->setAttributeValue($product, $attr1, 'Value A');
        // attr2 has no value → should still appear in results with source='none'

        $results = $this->resolver->resolveAll($product);

        $this->assertCount(2, $results);

        $first = $results->first();
        $this->assertEquals($attr1->id, $first->attributeId);
        $this->assertEquals('Value A', $first->value);

        $second = $results->last();
        $this->assertEquals($attr2->id, $second->attributeId);
        $this->assertFalse($second->hasValue());
    }

    // -----------------------------------------------------------------------
    // ResolvedValue DTO
    // -----------------------------------------------------------------------

    /** @test */
    public function resolved_value_to_array_contains_all_fields(): void
    {
        $rv = new ResolvedValue(
            attributeId: 'attr-123',
            attributeTechnicalName: 'color',
            value: 'Rot',
            source: 'own',
            collectionName: 'Stammdaten',
            collectionSort: 10,
            attributeSort: 20,
        );

        $array = $rv->toArray();

        $this->assertEquals('attr-123', $array['attribute_id']);
        $this->assertEquals('color', $array['attribute_technical_name']);
        $this->assertEquals('Rot', $array['value']);
        $this->assertEquals('own', $array['source']);
        $this->assertFalse($array['is_inherited']);
        $this->assertEquals('Stammdaten', $array['collection_name']);
    }

    /** @test */
    public function resolved_value_is_inherited_for_hierarchy(): void
    {
        $rv = new ResolvedValue(
            attributeId: 'a',
            attributeTechnicalName: 'x',
            value: 'v',
            source: 'hierarchy_inheritance',
            inheritedFromNodeId: 'node-1',
        );

        $this->assertTrue($rv->isInherited());
        $this->assertTrue($rv->hasValue());
    }

    /** @test */
    public function resolved_value_no_value_for_none_source(): void
    {
        $rv = new ResolvedValue(
            attributeId: 'a',
            attributeTechnicalName: 'x',
            value: null,
            source: 'none',
        );

        $this->assertFalse($rv->isInherited());
        $this->assertFalse($rv->hasValue());
    }
}
