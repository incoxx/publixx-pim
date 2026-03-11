<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Services\ComputedAttributeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComputedAttributeTest extends TestCase
{
    use RefreshDatabase;

    private ProductType $productType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productType = ProductType::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'test_product_type',
            'name_de' => 'Testprodukttyp',
            'has_variants' => false,
            'has_ean' => false,
            'has_prices' => false,
            'has_media' => false,
            'has_stock' => false,
            'has_physical_dimensions' => false,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createProduct(string $sku): Product
    {
        return Product::create([
            'id' => (string) Str::uuid(),
            'product_type_id' => $this->productType->id,
            'sku' => $sku,
            'name' => "Product {$sku}",
            'status' => 'active',
            'product_type_ref' => 'product',
        ]);
    }

    private function createCompositeWithChildren(
        string $technicalName,
        string $expression,
        array $childNames,
    ): array {
        $composite = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => $technicalName,
            'name_de' => ucfirst($technicalName),
            'data_type' => 'Composite',
            'composite_expression' => $expression,
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        $children = [];
        foreach ($childNames as $i => $name) {
            $children[] = Attribute::create([
                'id' => (string) Str::uuid(),
                'technical_name' => $name,
                'name_de' => ucfirst($name),
                'data_type' => 'Float',
                'parent_attribute_id' => $composite->id,
                'position' => $i,
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

        return ['composite' => $composite, 'children' => $children];
    }

    private function setNumericValue(Product $product, Attribute $attribute, float $value): ProductAttributeValue
    {
        return ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_number' => $value,
            'language' => null,
            'multiplied_index' => 0,
            'is_inherited' => false,
        ]);
    }

    // ─── Tests ───────────────────────────────────────────────────

    public function test_volume_computation(): void
    {
        $product = $this->createProduct('PROD-001');
        $attrs = $this->createCompositeWithChildren(
            'volumen',
            '{0} * {1} * {2}',
            ['breite', 'hoehe', 'tiefe'],
        );

        $this->setNumericValue($product, $attrs['children'][0], 120.0);
        $this->setNumericValue($product, $attrs['children'][1], 80.0);
        $this->setNumericValue($product, $attrs['children'][2], 45.0);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $attrs['composite']);

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['composite']->id)
            ->first();

        $this->assertNotNull($pav);
        $this->assertEqualsWithDelta(432000.0, (float) $pav->value_number, 0.01);
    }

    public function test_metric_to_inch_conversion(): void
    {
        $product = $this->createProduct('PROD-002');
        $attrs = $this->createCompositeWithChildren(
            'breite_inch',
            '{0} * 0.0393701',
            ['breite_mm'],
        );

        $this->setNumericValue($product, $attrs['children'][0], 25.4);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $attrs['composite']);

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['composite']->id)
            ->first();

        $this->assertNotNull($pav);
        $this->assertEqualsWithDelta(1.0, (float) $pav->value_number, 0.001);
    }

    public function test_missing_child_value_produces_no_result(): void
    {
        $product = $this->createProduct('PROD-003');
        $attrs = $this->createCompositeWithChildren(
            'volumen2',
            '{0} * {1} * {2}',
            ['b', 'h', 't'],
        );

        // Nur 2 von 3 Werten setzen
        $this->setNumericValue($product, $attrs['children'][0], 120.0);
        $this->setNumericValue($product, $attrs['children'][1], 80.0);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $attrs['composite']);

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['composite']->id)
            ->first();

        $this->assertNull($pav);
    }

    public function test_recompute_updates_existing_value(): void
    {
        $product = $this->createProduct('PROD-004');
        $attrs = $this->createCompositeWithChildren(
            'area',
            '{0} * {1}',
            ['laenge', 'breite2'],
        );

        $this->setNumericValue($product, $attrs['children'][0], 10.0);
        $this->setNumericValue($product, $attrs['children'][1], 5.0);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $attrs['composite']);

        // Überprüfe initialen Wert
        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['composite']->id)
            ->first();
        $this->assertEqualsWithDelta(50.0, (float) $pav->value_number, 0.01);

        // Kind-Wert ändern
        ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['children'][1]->id)
            ->update(['value_number' => 8.0]);

        // Neu berechnen
        $service->computeAndStore($product, $attrs['composite']);

        $pav->refresh();
        $this->assertEqualsWithDelta(80.0, (float) $pav->value_number, 0.01);
    }

    public function test_recompute_for_changed_attributes(): void
    {
        $product = $this->createProduct('PROD-005');
        $attrs = $this->createCompositeWithChildren(
            'sum',
            '{0} + {1}',
            ['val_a', 'val_b'],
        );

        $this->setNumericValue($product, $attrs['children'][0], 3.0);
        $this->setNumericValue($product, $attrs['children'][1], 7.0);

        $service = app(ComputedAttributeService::class);
        $service->recomputeForChangedAttributes(
            $product->id,
            [$attrs['children'][0]->id],
        );

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['composite']->id)
            ->first();

        $this->assertNotNull($pav);
        $this->assertEqualsWithDelta(10.0, (float) $pav->value_number, 0.01);
    }

    public function test_no_expression_does_nothing(): void
    {
        $product = $this->createProduct('PROD-006');

        // Composite ohne Expression (nur Format)
        $composite = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'dimensions',
            'name_de' => 'Abmessungen',
            'data_type' => 'Composite',
            'composite_format' => '{0} x {1} x {2} mm',
            'composite_expression' => null,
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $composite);

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $composite->id)
            ->first();

        $this->assertNull($pav);
    }
}
