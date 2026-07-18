<?php

declare(strict_types=1);

namespace Tests\Feature\Inheritance;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Services\Inheritance\VariantAxisService;
use App\Services\Inheritance\VariantInheritanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VariantAxisServiceTest extends TestCase
{
    use RefreshDatabase;

    private VariantAxisService $service;
    private VariantInheritanceService $inheritanceService;
    private ProductType $productType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VariantAxisService::class);
        $this->inheritanceService = app(VariantInheritanceService::class);

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
    }

    private function createProduct(string $sku, ?string $parentId = null): Product
    {
        return Product::create([
            'id' => (string) Str::uuid(),
            'product_type_id' => $this->productType->id,
            'sku' => $sku,
            'name' => "Product {$sku}",
            'status' => 'active',
            'product_type_ref' => $parentId ? 'variant' : 'product',
            'parent_product_id' => $parentId,
        ]);
    }

    private function createAttribute(string $technicalName, bool $isVariantAttribute = true, string $dataType = 'String'): Attribute
    {
        return Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => $technicalName,
            'name_de' => ucfirst($technicalName),
            'data_type' => $dataType,
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'is_variant_attribute' => $isVariantAttribute,
            'status' => 'active',
        ]);
    }

    private function setValue(Product $product, Attribute $attribute, string $value): void
    {
        ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => $value,
            'language' => null,
            'multiplied_index' => 0,
            'is_inherited' => false,
        ]);
    }

    // -----------------------------------------------------------------------
    // setAxes
    // -----------------------------------------------------------------------

    /** @test */
    public function set_axes_rejects_attribute_without_is_variant_attribute(): void
    {
        $parent = $this->createProduct('PARENT');
        $attr = $this->createAttribute('color', isVariantAttribute: false);

        $this->expectException(ValidationException::class);

        $this->service->setAxes($parent, [$attr->id]);
    }

    /** @test */
    public function set_axes_persists_configured_attributes_in_order(): void
    {
        $parent = $this->createProduct('PARENT');
        $attr1 = $this->createAttribute('color');
        $attr2 = $this->createAttribute('size');

        $this->service->setAxes($parent, [$attr2->id, $attr1->id]);

        $ids = $this->service->getAxisAttributeIds($parent);
        $this->assertSame([$attr2->id, $attr1->id], $ids);
    }

    /** @test */
    public function set_axes_forces_override_rules_on_existing_variants(): void
    {
        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attr = $this->createAttribute('color');

        $this->service->setAxes($parent, [$attr->id]);

        $this->assertEquals('override', $this->inheritanceService->getMode($variant, $attr->id));
    }

    /** @test */
    public function set_axes_rejects_when_existing_variants_would_collide(): void
    {
        $parent = $this->createProduct('PARENT');
        $v1 = $this->createProduct('V1', $parent->id);
        $v2 = $this->createProduct('V2', $parent->id);
        $attr = $this->createAttribute('color');

        $this->setValue($v1, $attr, 'Red');
        $this->setValue($v2, $attr, 'Red');

        $this->expectException(ValidationException::class);

        $this->service->setAxes($parent, [$attr->id]);
    }

    // -----------------------------------------------------------------------
    // combinationFor / assertUniqueCombination
    // -----------------------------------------------------------------------

    /** @test */
    public function combination_for_normalizes_string_values(): void
    {
        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attr = $this->createAttribute('color');
        $this->service->setAxes($parent, [$attr->id]);
        $this->setValue($variant, $attr, '  Red  ');

        $combination = $this->service->combinationFor($variant);

        $this->assertSame(['red'], array_values($combination));
    }

    /** @test */
    public function assert_unique_combination_passes_when_no_axes_configured(): void
    {
        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);

        $this->service->assertUniqueCombination($variant);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function assert_unique_combination_passes_when_combination_incomplete(): void
    {
        $parent = $this->createProduct('PARENT');
        $v1 = $this->createProduct('V1', $parent->id);
        $v2 = $this->createProduct('V2', $parent->id);
        $attr = $this->createAttribute('color');
        $this->service->setAxes($parent, [$attr->id]);

        // Beide Varianten haben noch keinen Achsen-Wert — kein Duplikat.
        $this->service->assertUniqueCombination($v1);
        $this->service->assertUniqueCombination($v2);
        $this->addToAssertionCount(2);
    }

    /** @test */
    public function assert_unique_combination_throws_for_duplicate_sibling(): void
    {
        $parent = $this->createProduct('PARENT');
        $v1 = $this->createProduct('V1', $parent->id);
        $v2 = $this->createProduct('V2', $parent->id);
        $attr = $this->createAttribute('color');
        $this->service->setAxes($parent, [$attr->id]);

        $this->setValue($v1, $attr, 'Red');
        $this->setValue($v2, $attr, 'Red');

        $this->expectException(ValidationException::class);

        $this->service->assertUniqueCombination($v2);
    }

    /** @test */
    public function assert_unique_combination_allows_distinct_values(): void
    {
        $parent = $this->createProduct('PARENT');
        $v1 = $this->createProduct('V1', $parent->id);
        $v2 = $this->createProduct('V2', $parent->id);
        $attr = $this->createAttribute('color');
        $this->service->setAxes($parent, [$attr->id]);

        $this->setValue($v1, $attr, 'Red');
        $this->setValue($v2, $attr, 'Green');

        $this->service->assertUniqueCombination($v2);
        $this->addToAssertionCount(1);
    }

    // -----------------------------------------------------------------------
    // Guard in VariantInheritanceService
    // -----------------------------------------------------------------------

    /** @test */
    public function inheritance_service_rejects_inherit_mode_for_axis_attribute(): void
    {
        $parent = $this->createProduct('PARENT');
        $variant = $this->createProduct('VARIANT', $parent->id);
        $attr = $this->createAttribute('color');
        $this->service->setAxes($parent, [$attr->id]);

        $this->expectException(ValidationException::class);

        $this->inheritanceService->setRules($variant, [$attr->id => 'inherit']);
    }
}
