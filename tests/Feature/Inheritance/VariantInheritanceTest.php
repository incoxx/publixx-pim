<?php

declare(strict_types=1);

namespace Tests\Feature\Inheritance;

use App\Events\AttributeValuesChanged;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\VariantInheritanceRule;
use App\Services\Inheritance\VariantInheritanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class VariantInheritanceTest extends TestCase
{
    use RefreshDatabase;

    private VariantInheritanceService $service;
    private ProductType $productType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VariantInheritanceService::class);

        $this->productType = ProductType::create([
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

    private function createProduct(string $sku, ?string $parentId = null): Product
    {
        return Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'product_type_id' => $this->productType->id,
            'sku' => $sku,
            'name' => "Product {$sku}",
            'status' => 'active',
            'product_type_ref' => $parentId ? 'variant' : 'product',
            'parent_product_id' => $parentId,
        ]);
    }

    private function createAttribute(string $technicalName): Attribute
    {
        return Attribute::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'technical_name' => $technicalName,
            'name_de' => ucfirst($technicalName),
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

    // -----------------------------------------------------------------------
    // Tests: Default behavior (inherit)
    // -----------------------------------------------------------------------

    /** @test */
    public function default_mode_is_inherit_when_no_rule_exists(): void
    {
        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attr = $this->createAttribute('color');

        $mode = $this->service->getMode($variant, $attr->id);

        $this->assertEquals('inherit', $mode);
    }

    /** @test */
    public function non_variant_returns_override_mode(): void
    {
        $product = $this->createProduct('STANDALONE');
        $attr = $this->createAttribute('name');

        $mode = $this->service->getMode($product, $attr->id);

        $this->assertEquals('override', $mode);
    }

    /** @test */
    public function get_rules_returns_empty_for_non_variant(): void
    {
        $product = $this->createProduct('STANDALONE');

        $rules = $this->service->getRules($product);

        $this->assertTrue($rules->isEmpty());
    }

    // -----------------------------------------------------------------------
    // Tests: Setting rules
    // -----------------------------------------------------------------------

    /** @test */
    public function set_rules_creates_inheritance_rules(): void
    {
        Event::fake([AttributeValuesChanged::class]);

        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attrName = $this->createAttribute('name');
        $attrWeight = $this->createAttribute('weight');

        $this->service->setRules($variant, [
            $attrName->id => 'override',
            $attrWeight->id => 'inherit',
        ]);

        $this->assertEquals('override', $this->service->getMode($variant, $attrName->id));
        $this->assertEquals('inherit', $this->service->getMode($variant, $attrWeight->id));

        $this->assertDatabaseHas('variant_inheritance_rules', [
            'product_id' => $variant->id,
            'attribute_id' => $attrName->id,
            'inheritance_mode' => 'override',
        ]);
    }

    /** @test */
    public function set_rules_dispatches_event_for_changed_rules(): void
    {
        Event::fake([AttributeValuesChanged::class]);

        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attr = $this->createAttribute('color');

        // Default is inherit, changing to override triggers event
        $this->service->setRules($variant, [
            $attr->id => 'override',
        ]);

        Event::assertDispatched(AttributeValuesChanged::class, function ($event) use ($variant, $attr) {
            return $event->productId === $variant->id
                && in_array($attr->id, $event->attributeIds);
        });
    }

    /** @test */
    public function set_rules_does_not_dispatch_event_when_unchanged(): void
    {
        Event::fake([AttributeValuesChanged::class]);

        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attr = $this->createAttribute('color');

        // First set to override
        $this->service->setRules($variant, [$attr->id => 'override']);
        Event::assertDispatchedTimes(AttributeValuesChanged::class, 1);

        // Set to override again — no change, no event
        $this->service->setRules($variant, [$attr->id => 'override']);
        Event::assertDispatchedTimes(AttributeValuesChanged::class, 1);
    }

    /** @test */
    public function set_rules_throws_for_non_variant(): void
    {
        $product = $this->createProduct('STANDALONE');
        $attr = $this->createAttribute('name');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->setRules($product, [$attr->id => 'inherit']);
    }

    /** @test */
    public function set_rules_throws_for_invalid_mode(): void
    {
        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attr = $this->createAttribute('name');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->setRules($variant, [$attr->id => 'invalid_mode']);
    }

    // -----------------------------------------------------------------------
    // Tests: Reset rules
    // -----------------------------------------------------------------------

    /** @test */
    public function reset_rule_removes_explicit_rule(): void
    {
        Event::fake([AttributeValuesChanged::class]);

        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attr = $this->createAttribute('name');

        $this->service->setRule($variant, $attr->id, 'override');
        $this->assertEquals('override', $this->service->getMode($variant, $attr->id));

        $this->service->resetRule($variant, $attr->id);
        // Falls back to default: inherit
        $this->assertEquals('inherit', $this->service->getMode($variant, $attr->id));
    }

    /** @test */
    public function reset_all_rules_removes_all_rules(): void
    {
        Event::fake([AttributeValuesChanged::class]);

        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attr1 = $this->createAttribute('name');
        $attr2 = $this->createAttribute('weight');

        $this->service->setRules($variant, [
            $attr1->id => 'override',
            $attr2->id => 'override',
        ]);

        $this->service->resetAllRules($variant);

        $rules = $this->service->getRules($variant);
        $this->assertTrue($rules->isEmpty());
    }

    // -----------------------------------------------------------------------
    // Tests: Get variant IDs
    // -----------------------------------------------------------------------

    /** @test */
    public function get_variant_ids_returns_all_variants(): void
    {
        $parent = $this->createProduct('PARENT');
        $v1 = $this->createProduct('V1', $parent->id);
        $v2 = $this->createProduct('V2', $parent->id);
        $other = $this->createProduct('OTHER');

        $variantIds = $this->service->getVariantIds($parent);

        $this->assertCount(2, $variantIds);
        $this->assertContains($v1->id, $variantIds->toArray());
        $this->assertContains($v2->id, $variantIds->toArray());
    }
}
