<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Models\Attribute;
use App\Models\Media;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use App\Models\PublixxExportMapping;
use App\Models\Unit;
use App\Services\Export\DatasetBuilder;
use App\Services\Export\MappingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatasetBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected DatasetBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new DatasetBuilder(new MappingResolver());
    }

    // ─── Format: flat ───────────────────────────────────────────

    public function test_build_flat_format(): void
    {
        $product = $this->createProductWithAttributes();
        $mapping = $this->createMapping('flat');

        $dataset = $this->builder->build($product, $mapping);

        $this->assertEquals($product->id, $dataset['id']);
        $this->assertEquals($product->sku, $dataset['sku']);
        $this->assertArrayHasKey('productName', $dataset);
        // In flat mode, dots are preserved in keys
        $this->assertArrayHasKey('specs.weight', $dataset);
    }

    // ─── Format: nested ─────────────────────────────────────────

    public function test_build_nested_format(): void
    {
        $product = $this->createProductWithAttributes();
        $mapping = $this->createMapping('nested');

        $dataset = $this->builder->build($product, $mapping);

        $this->assertEquals($product->id, $dataset['id']);
        $this->assertEquals($product->sku, $dataset['sku']);
        $this->assertArrayHasKey('productName', $dataset);
        // In nested mode, dots become nested objects
        $this->assertArrayHasKey('specs', $dataset);
        $this->assertIsArray($dataset['specs']);
        $this->assertArrayHasKey('weight', $dataset['specs']);
    }

    // ─── Format: publixx ────────────────────────────────────────

    public function test_build_publixx_format(): void
    {
        $product = $this->createProductWithAttributes();
        $mapping = $this->createMapping('publixx');

        $dataset = $this->builder->build($product, $mapping);

        // Publixx envelope fields
        $this->assertArrayHasKey('id', $dataset);
        $this->assertArrayHasKey('sku', $dataset);
        $this->assertArrayHasKey('ean', $dataset);
        $this->assertArrayHasKey('status', $dataset);
        $this->assertArrayHasKey('productName', $dataset);
        $this->assertArrayHasKey('hierarchy', $dataset);

        // Nested from dot-notation
        $this->assertArrayHasKey('specs', $dataset);
        $this->assertIsArray($dataset['specs']);
    }

    // ─── Dot-notation nesting ───────────────────────────────────

    public function test_dot_notation_creates_nested_structure(): void
    {
        $product = $this->createProductWithPrices();
        $mapping = $this->createPriceMapping('nested');

        $dataset = $this->builder->build($product, $mapping);

        // "preis.listenpreis" → ['preis' => ['listenpreis' => 189.99]]
        $this->assertArrayHasKey('preis', $dataset);
        $this->assertIsArray($dataset['preis']);
        $this->assertArrayHasKey('listenpreis', $dataset['preis']);
        $this->assertEquals(189.99, $dataset['preis']['listenpreis']);
    }

    public function test_deep_dot_notation(): void
    {
        $dataset = [];
        $this->builder->setNestedValue($dataset, 'a.b.c.d', 'deep');

        $this->assertEquals('deep', $dataset['a']['b']['c']['d']);
    }

    public function test_multiple_dot_notation_same_parent(): void
    {
        $dataset = [];
        $this->builder->setNestedValue($dataset, 'preis.listenpreis', 189.99);
        $this->builder->setNestedValue($dataset, 'preis.aktionspreis', 149.99);

        $this->assertEquals(189.99, $dataset['preis']['listenpreis']);
        $this->assertEquals(149.99, $dataset['preis']['aktionspreis']);
    }

    // ─── i18n in datasets ───────────────────────────────────────

    public function test_build_with_multiple_languages(): void
    {
        $product = Product::factory()->create(['sku' => 'TEST-i18n', 'ean' => '1234567890123']);
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

        $product->load('attributeValues.attribute', 'attributeValues.unit');

        $mapping = PublixxExportMapping::factory()->create([
            'mapping_rules' => [
                'rules' => [
                    ['source' => 'attribute:product-name-dict', 'target' => 'productName', 'type' => 'text'],
                ],
            ],
            'languages' => ['de', 'en'],
            'flatten_mode' => 'publixx',
            'include_media' => false,
            'include_prices' => false,
            'include_variants' => false,
            'include_relations' => false,
        ]);

        $dataset = $this->builder->build($product, $mapping);

        // Primary language = main field
        $this->assertEquals('Akkubohrschrauber', $dataset['productName']);
        // Additional language = suffix
        $this->assertArrayHasKey('productName_en', $dataset);
        $this->assertEquals('Cordless Drill', $dataset['productName_en']);
    }

    // ─── Price currency in publixx format ───────────────────────

    public function test_publixx_format_adds_currency(): void
    {
        $product = $this->createProductWithPrices();
        $mapping = $this->createPriceMapping('publixx');

        $dataset = $this->builder->build($product, $mapping);

        $this->assertArrayHasKey('preis', $dataset);
        $this->assertArrayHasKey('currency', $dataset['preis']);
        $this->assertEquals('EUR', $dataset['preis']['currency']);
    }

    // ─── buildMany ──────────────────────────────────────────────

    public function test_build_many_products(): void
    {
        $products = Product::factory()->count(3)->create();
        $mapping = PublixxExportMapping::factory()->create([
            'mapping_rules' => ['rules' => []],
            'languages' => ['de'],
            'flatten_mode' => 'flat',
            'include_media' => false,
            'include_prices' => false,
            'include_variants' => false,
            'include_relations' => false,
        ]);

        $datasets = $this->builder->buildMany($products, $mapping);

        $this->assertCount(3, $datasets);
        foreach ($datasets as $i => $dataset) {
            $this->assertEquals($products[$i]->id, $dataset['id']);
        }
    }

    // ─── Helpers ────────────────────────────────────────────────

    protected function createProductWithAttributes(): Product
    {
        $product = Product::factory()->create([
            'sku' => 'EW-ABS-001',
            'ean' => '4012345678901',
            'name' => 'Akkubohrschrauber ProDrill 18V',
            'status' => 'active',
        ]);

        $nameAttr = Attribute::factory()->create(['technical_name' => 'product-name-dict']);
        $weightAttr = Attribute::factory()->create(['technical_name' => 'product-weight-num']);
        $unit = Unit::factory()->create(['abbreviation' => 'kg']);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $nameAttr->id,
            'value_string' => 'Akkubohrschrauber ProDrill 18V',
            'language' => 'de',
        ]);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $weightAttr->id,
            'value_number' => 1.8,
            'unit_id' => $unit->id,
            'language' => null,
        ]);

        $product->load('attributeValues.attribute', 'attributeValues.unit', 'attributeValues.valueListEntry');

        return $product;
    }

    protected function createProductWithPrices(): Product
    {
        $product = Product::factory()->create([
            'sku' => 'PRICE-001',
            'ean' => '4012345678902',
            'status' => 'active',
        ]);

        $priceType = PriceType::factory()->create(['technical_name' => 'list_price']);

        ProductPrice::factory()->create([
            'product_id' => $product->id,
            'price_type_id' => $priceType->id,
            'amount' => 189.99,
            'currency' => 'EUR',
        ]);

        $product->load('prices.priceType');

        return $product;
    }

    protected function createMapping(string $flattenMode): PublixxExportMapping
    {
        return PublixxExportMapping::factory()->create([
            'mapping_rules' => [
                'rules' => [
                    ['source' => 'attribute:product-name-dict', 'target' => 'productName', 'type' => 'text'],
                    ['source' => 'attribute:product-weight-num', 'target' => 'specs.weight', 'type' => 'unit_value'],
                ],
            ],
            'languages' => ['de'],
            'flatten_mode' => $flattenMode,
            'include_media' => false,
            'include_prices' => false,
            'include_variants' => false,
            'include_relations' => false,
        ]);
    }

    protected function createPriceMapping(string $flattenMode): PublixxExportMapping
    {
        return PublixxExportMapping::factory()->create([
            'mapping_rules' => [
                'rules' => [
                    ['source' => 'prices:list_price', 'target' => 'preis.listenpreis', 'type' => 'price'],
                ],
            ],
            'languages' => ['de'],
            'flatten_mode' => $flattenMode,
            'include_media' => false,
            'include_prices' => true,
            'include_variants' => false,
            'include_relations' => false,
        ]);
    }
}
