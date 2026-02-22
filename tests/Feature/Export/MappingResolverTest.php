<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Models\Attribute;
use App\Models\Media;
use App\Models\MediaUsageType;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use App\Models\ProductRelation;
use App\Models\ProductRelationType;
use App\Models\Unit;
use App\Services\Export\MappingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MappingResolverTest extends TestCase
{
    use RefreshDatabase;

    protected MappingResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new MappingResolver();
    }

    // ─── text type ──────────────────────────────────────────────

    public function test_resolve_text_attribute(): void
    {
        $product = Product::factory()->create(['name' => 'Akkubohrschrauber']);
        $attribute = Attribute::factory()->create(['technical_name' => 'product-name-dict']);
        $pav = ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Akkubohrschrauber ProDrill 18V',
            'language' => 'de',
        ]);

        $product->load('attributeValues.attribute');

        $rules = [
            ['source' => 'attribute:product-name-dict', 'target' => 'productName', 'type' => 'text'],
        ];

        $result = $this->resolver->resolve($rules, $product, ['de']);

        $this->assertArrayHasKey('productName', $result);
        $this->assertEquals('Akkubohrschrauber ProDrill 18V', $result['productName']);
    }

    public function test_resolve_text_with_i18n_suffix(): void
    {
        $product = Product::factory()->create();
        $attribute = Attribute::factory()->create(['technical_name' => 'product-name-dict']);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Akkubohrschrauber',
            'language' => 'de',
        ]);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Cordless Drill',
            'language' => 'en',
        ]);

        $product->load('attributeValues.attribute');

        $rules = [
            ['source' => 'attribute:product-name-dict', 'target' => 'productName', 'type' => 'text'],
        ];

        $result = $this->resolver->resolve($rules, $product, ['de', 'en']);

        $this->assertEquals('Akkubohrschrauber', $result['productName']);
        $this->assertArrayHasKey('productName_en', $result);
        $this->assertEquals('Cordless Drill', $result['productName_en']);
    }

    public function test_resolve_text_no_suffix_when_same_value(): void
    {
        $product = Product::factory()->create();
        $attribute = Attribute::factory()->create(['technical_name' => 'product-sku']);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'SKU-001',
            'language' => 'de',
        ]);

        // Same value in English
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'SKU-001',
            'language' => 'en',
        ]);

        $product->load('attributeValues.attribute');

        $rules = [
            ['source' => 'attribute:product-sku', 'target' => 'sku_field', 'type' => 'text'],
        ];

        $result = $this->resolver->resolve($rules, $product, ['de', 'en']);

        $this->assertEquals('SKU-001', $result['sku_field']);
        // No suffix because values are identical
        $this->assertArrayNotHasKey('sku_field_en', $result);
    }

    // ─── unit_value type ────────────────────────────────────────

    public function test_resolve_unit_value(): void
    {
        $product = Product::factory()->create();
        $attribute = Attribute::factory()->create(['technical_name' => 'product-weight-num']);
        $unit = Unit::factory()->create(['abbreviation' => 'kg']);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_number' => 1.8,
            'unit_id' => $unit->id,
            'language' => null,
        ]);

        $product->load('attributeValues.attribute', 'attributeValues.unit');

        $rules = [
            ['source' => 'attribute:product-weight-num', 'target' => 'specs.weight', 'type' => 'unit_value'],
        ];

        $result = $this->resolver->resolve($rules, $product, ['de']);

        $this->assertArrayHasKey('specs.weight', $result);
        $this->assertEquals(['value' => 1.8, 'unit' => 'kg'], $result['specs.weight']);
    }

    // ─── media_url type ─────────────────────────────────────────

    public function test_resolve_media_url(): void
    {
        $product = Product::factory()->create();
        $media = Media::factory()->create(['file_path' => 'https://pim.example.com/media/prodrill-18v.jpg']);

        $teaserType = MediaUsageType::factory()->create(['technical_name' => 'teaser']);

        ProductMediaAssignment::factory()->create([
            'product_id' => $product->id,
            'media_id' => $media->id,
            'usage_type_id' => $teaserType->id,
            'sort_order' => 0,
        ]);

        $product->load(['mediaAssignments.media', 'mediaAssignments.usageType']);

        $rules = [
            ['source' => 'media:teaser', 'target' => 'productImage', 'type' => 'media_url'],
        ];

        $result = $this->resolver->resolve($rules, $product, ['de']);

        $this->assertEquals('https://pim.example.com/media/prodrill-18v.jpg', $result['productImage']);
    }

    // ─── media_array type ───────────────────────────────────────

    public function test_resolve_media_array(): void
    {
        $product = Product::factory()->create();
        $media1 = Media::factory()->create(['file_path' => 'https://pim.example.com/media/front.jpg']);
        $media2 = Media::factory()->create(['file_path' => 'https://pim.example.com/media/side.jpg']);

        $galleryType = MediaUsageType::factory()->create(['technical_name' => 'gallery']);

        ProductMediaAssignment::factory()->create([
            'product_id' => $product->id,
            'media_id' => $media1->id,
            'usage_type_id' => $galleryType->id,
            'sort_order' => 0,
        ]);

        ProductMediaAssignment::factory()->create([
            'product_id' => $product->id,
            'media_id' => $media2->id,
            'usage_type_id' => $galleryType->id,
            'sort_order' => 1,
        ]);

        $product->load(['mediaAssignments.media', 'mediaAssignments.usageType']);

        $rules = [
            ['source' => 'media:gallery', 'target' => 'gallery', 'type' => 'media_array'],
        ];

        $result = $this->resolver->resolve($rules, $product, ['de']);

        $this->assertCount(2, $result['gallery']);
        $this->assertContains('https://pim.example.com/media/front.jpg', $result['gallery']);
    }

    // ─── price type ─────────────────────────────────────────────

    public function test_resolve_price(): void
    {
        $product = Product::factory()->create();
        $priceType = PriceType::factory()->create(['technical_name' => 'list_price']);

        ProductPrice::factory()->create([
            'product_id' => $product->id,
            'price_type_id' => $priceType->id,
            'amount' => 189.99,
            'currency' => 'EUR',
        ]);

        $product->load('prices.priceType');

        $rules = [
            ['source' => 'prices:list_price', 'target' => 'preis.listenpreis', 'type' => 'price'],
        ];

        $result = $this->resolver->resolve($rules, $product, ['de']);

        $this->assertEquals(189.99, $result['preis.listenpreis']);
    }

    // ─── variant_array type ─────────────────────────────────────

    public function test_resolve_variant_array(): void
    {
        $parent = Product::factory()->create(['sku' => 'PARENT-001']);
        $variant1 = Product::factory()->create([
            'parent_product_id' => $parent->id,
            'product_type_ref' => 'variant',
            'sku' => 'VAR-001',
            'name' => '2.0 Ah Akku',
        ]);
        $variant2 = Product::factory()->create([
            'parent_product_id' => $parent->id,
            'product_type_ref' => 'variant',
            'sku' => 'VAR-002',
            'name' => '5.0 Ah Akku',
        ]);

        $parent->load('variants');

        $rules = [
            ['source' => 'variants', 'target' => 'varianten', 'type' => 'variant_array'],
        ];

        $result = $this->resolver->resolve($rules, $parent, ['de']);

        $this->assertCount(2, $result['varianten']);
        $this->assertEquals('VAR-001', $result['varianten'][0]['sku']);
        $this->assertEquals('2.0 Ah Akku', $result['varianten'][0]['name']);
    }

    // ─── relation_array type ────────────────────────────────────

    public function test_resolve_relation_array(): void
    {
        $product = Product::factory()->create();
        $accessory = Product::factory()->create(['sku' => 'ZB-BIT-SET', 'name' => 'Bit-Set 32-teilig']);
        $relType = ProductRelationType::factory()->create(['technical_name' => 'accessory']);

        ProductRelation::factory()->create([
            'source_product_id' => $product->id,
            'target_product_id' => $accessory->id,
            'relation_type_id' => $relType->id,
        ]);

        $product->load('outgoingRelations.relationType', 'outgoingRelations.targetProduct');

        $rules = [
            ['source' => 'relations:accessory', 'target' => 'zubehoer', 'type' => 'relation_array'],
        ];

        $result = $this->resolver->resolve($rules, $product, ['de']);

        $this->assertCount(1, $result['zubehoer']);
        $this->assertEquals('ZB-BIT-SET', $result['zubehoer'][0]['sku']);
        $this->assertEquals('Bit-Set 32-teilig', $result['zubehoer'][0]['name']);
    }

    // ─── Multiple rules together ────────────────────────────────

    public function test_resolve_multiple_rules(): void
    {
        $product = Product::factory()->create(['sku' => 'EW-001']);
        $nameAttr = Attribute::factory()->create(['technical_name' => 'product-name-dict']);
        $weightAttr = Attribute::factory()->create(['technical_name' => 'product-weight-num']);
        $unit = Unit::factory()->create(['abbreviation' => 'kg']);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $nameAttr->id,
            'value_string' => 'Testprodukt',
            'language' => 'de',
        ]);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $weightAttr->id,
            'value_number' => 2.5,
            'unit_id' => $unit->id,
            'language' => null,
        ]);

        $product->load('attributeValues.attribute', 'attributeValues.unit');

        $rules = [
            ['source' => 'attribute:product-name-dict', 'target' => 'productName', 'type' => 'text'],
            ['source' => 'attribute:product-weight-num', 'target' => 'specs.weight', 'type' => 'unit_value'],
        ];

        $result = $this->resolver->resolve($rules, $product, ['de']);

        $this->assertEquals('Testprodukt', $result['productName']);
        $this->assertEquals(['value' => 2.5, 'unit' => 'kg'], $result['specs.weight']);
    }

    // ─── Edge cases ─────────────────────────────────────────────

    public function test_resolve_empty_rules(): void
    {
        $product = Product::factory()->create();

        $result = $this->resolver->resolve([], $product, ['de']);

        $this->assertEmpty($result);
    }

    public function test_resolve_missing_attribute(): void
    {
        $product = Product::factory()->create();
        $product->load('attributeValues.attribute');

        $rules = [
            ['source' => 'attribute:nonexistent', 'target' => 'missing', 'type' => 'text'],
        ];

        $result = $this->resolver->resolve($rules, $product, ['de']);

        $this->assertNull($result['missing']);
    }

    public function test_resolve_skips_rules_without_target(): void
    {
        $product = Product::factory()->create();

        $rules = [
            ['source' => 'attribute:something', 'target' => '', 'type' => 'text'],
        ];

        $result = $this->resolver->resolve($rules, $product, ['de']);

        $this->assertEmpty($result);
    }
}
