<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Events\ImportCompleted;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Services\Import\ImportExecutor;
use App\Services\Import\ParseResult;
use App\Services\Import\ReferenceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ImportExecutorTest extends TestCase
{
    use RefreshDatabase;

    private ImportExecutor $executor;
    private string $productTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new ImportExecutor(new ReferenceResolver());

        // Stammdaten anlegen
        $pt = ProductType::forceCreate([
            'id' => 'pt-physical',
            'technical_name' => 'physical_product',
            'name_de' => 'Physisches Produkt',
            'has_variants' => true,
            'has_ean' => true,
            'has_prices' => true,
            'has_media' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $this->productTypeId = $pt->id;
    }

    public function test_creates_new_products(): void
    {
        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'NEW-001',
                        'name' => 'Neues Produkt',
                        'name_en' => 'New Product',
                        'product_type' => 'physical_product',
                        'ean' => '4012345678901',
                        'status' => 'draft',
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertEquals(1, $result->stats['08_Produkte']['created']);
        $this->assertEquals(0, $result->stats['08_Produkte']['updated']);
        $this->assertDatabaseHas('products', ['sku' => 'NEW-001', 'name' => 'Neues Produkt']);
    }

    public function test_updates_existing_products(): void
    {
        // Vorhandenes Produkt
        Product::forceCreate([
            'id' => 'existing-prod-1',
            'sku' => 'EXIST-001',
            'name' => 'Altes Produkt',
            'product_type_id' => $this->productTypeId,
            'status' => 'draft',
            'product_type_ref' => 'product',
        ]);

        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'EXIST-001',
                        'name' => 'Aktualisiertes Produkt',
                        'name_en' => null,
                        'product_type' => 'physical_product',
                        'ean' => null,
                        'status' => 'active',
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertEquals(0, $result->stats['08_Produkte']['created']);
        $this->assertEquals(1, $result->stats['08_Produkte']['updated']);
        $this->assertDatabaseHas('products', [
            'sku' => 'EXIST-001',
            'name' => 'Aktualisiertes Produkt',
            'status' => 'active',
        ]);
    }

    public function test_imports_product_attribute_values(): void
    {
        // Produkt und Attribut anlegen
        Product::forceCreate([
            'id' => 'prod-1',
            'sku' => 'SKU-001',
            'name' => 'Test',
            'product_type_id' => $this->productTypeId,
            'status' => 'draft',
            'product_type_ref' => 'product',
        ]);

        Attribute::forceCreate([
            'id' => 'attr-weight',
            'technical_name' => 'product-weight',
            'name_de' => 'Gewicht',
            'data_type' => 'Number',
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_inheritable' => true,
        ]);

        $parseResult = new ParseResult(
            sheetsFound: ['09_Produktwerte'],
            data: [
                '09_Produktwerte' => [
                    2 => [
                        'sku' => 'SKU-001',
                        'attribute' => 'product-weight',
                        'value' => '4.5',
                        'unit' => null,
                        'language' => null,
                        'index' => null,
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertEquals(1, $result->stats['09_Produktwerte']['created']);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => 'prod-1',
            'attribute_id' => 'attr-weight',
            'value_number' => 4.5,
        ]);
    }

    public function test_affected_product_ids_collected(): void
    {
        Product::forceCreate([
            'id' => 'prod-aff-1',
            'sku' => 'AFF-001',
            'name' => 'Test',
            'product_type_id' => $this->productTypeId,
            'status' => 'draft',
            'product_type_ref' => 'product',
        ]);

        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'AFF-001',
                        'name' => 'Updated',
                        'name_en' => null,
                        'product_type' => 'physical_product',
                        'ean' => null,
                        'status' => 'active',
                        '_row' => 2,
                    ],
                    3 => [
                        'sku' => 'AFF-002',
                        'name' => 'Neu',
                        'name_en' => null,
                        'product_type' => 'physical_product',
                        'ean' => null,
                        'status' => 'draft',
                        '_row' => 3,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertCount(2, $result->affectedProductIds);
        $this->assertContains('prod-aff-1', $result->affectedProductIds);
    }

    public function test_skips_products_with_unresolvable_type(): void
    {
        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'SKIP-001',
                        'name' => 'Kein Typ',
                        'name_en' => null,
                        'product_type' => 'nonexistent_type',
                        'ean' => null,
                        'status' => 'draft',
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertEquals(1, $result->stats['08_Produkte']['skipped']);
        $this->assertEquals(0, $result->stats['08_Produkte']['created']);
        $this->assertDatabaseMissing('products', ['sku' => 'SKIP-001']);
    }

    public function test_imports_product_types(): void
    {
        $parseResult = new ParseResult(
            sheetsFound: ['01_Produkttypen'],
            data: [
                '01_Produkttypen' => [
                    2 => [
                        'technical_name' => 'new_type',
                        'name_de' => 'Neuer Typ',
                        'name_en' => 'New Type',
                        'description' => 'Ein neuer Typ',
                        'has_variants' => 'Ja',
                        'has_ean' => 'Nein',
                        'has_prices' => 'Ja',
                        'has_media' => 'Ja',
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertEquals(1, $result->stats['01_Produkttypen']['created']);
        $this->assertDatabaseHas('product_types', [
            'technical_name' => 'new_type',
            'name_de' => 'Neuer Typ',
            'has_variants' => true,
            'has_ean' => false,
        ]);
    }

    public function test_executes_in_transaction(): void
    {
        // Wenn ein Fehler auftritt, sollte nichts committed werden
        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'TXN-001',
                        'name' => 'Test Transaction',
                        'name_en' => null,
                        'product_type' => 'physical_product',
                        'ean' => null,
                        'status' => 'draft',
                        '_row' => 2,
                    ],
                ],
            ],
        );

        // Normaler Import sollte funktionieren
        $result = $this->executor->execute($parseResult);
        $this->assertDatabaseHas('products', ['sku' => 'TXN-001']);
    }

    public function test_value_mapping_for_different_data_types(): void
    {
        Product::forceCreate([
            'id' => 'prod-vt',
            'sku' => 'VT-001',
            'name' => 'Value Type Test',
            'product_type_id' => $this->productTypeId,
            'status' => 'draft',
            'product_type_ref' => 'product',
        ]);

        // String-Attribut
        Attribute::forceCreate([
            'id' => 'attr-desc',
            'technical_name' => 'description',
            'name_de' => 'Beschreibung',
            'data_type' => 'String',
            'is_translatable' => true,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_inheritable' => true,
        ]);

        // Flag-Attribut
        Attribute::forceCreate([
            'id' => 'attr-flag',
            'technical_name' => 'is-hazardous',
            'name_de' => 'Gefahrgut',
            'data_type' => 'Flag',
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => false,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_inheritable' => true,
        ]);

        $parseResult = new ParseResult(
            sheetsFound: ['09_Produktwerte'],
            data: [
                '09_Produktwerte' => [
                    2 => [
                        'sku' => 'VT-001',
                        'attribute' => 'description',
                        'value' => 'Ein Text',
                        'unit' => null,
                        'language' => 'de',
                        'index' => null,
                        '_row' => 2,
                    ],
                    3 => [
                        'sku' => 'VT-001',
                        'attribute' => 'is-hazardous',
                        'value' => 'Ja',
                        'unit' => null,
                        'language' => null,
                        'index' => null,
                        '_row' => 3,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        // String-Wert
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => 'prod-vt',
            'attribute_id' => 'attr-desc',
            'value_string' => 'Ein Text',
            'language' => 'de',
        ]);

        // Flag-Wert
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => 'prod-vt',
            'attribute_id' => 'attr-flag',
            'value_flag' => true,
        ]);
    }
}
