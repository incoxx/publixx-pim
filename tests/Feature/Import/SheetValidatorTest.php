<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\ProductType;
use App\Models\UnitGroup;
use App\Models\ValueList;
use App\Services\Import\FuzzyMatch;
use App\Services\Import\FuzzyMatcher;
use App\Services\Import\ParseResult;
use App\Services\Import\ReferenceResolver;
use App\Services\Import\SheetValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SheetValidatorTest extends TestCase
{
    use RefreshDatabase;

    private SheetValidator $validator;
    private ReferenceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ReferenceResolver(new FuzzyMatcher());
        $this->validator = new SheetValidator($this->resolver, new FuzzyMatcher());
    }

    public function test_validates_required_fields(): void
    {
        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => null, // Pflichtfeld fehlt
                        'name' => 'Test',
                        'name_en' => null,
                        'product_type' => 'physical_product',
                        'ean' => null,
                        'status' => null,
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->validator->validate($parseResult);

        $this->assertTrue($result->hasErrors);
        $this->assertNotEmpty($result->errors);

        $skuError = collect($result->errors)->firstWhere('field', 'SKU');
        $this->assertNotNull($skuError);
        $this->assertEquals(2, $skuError['row']);
        $this->assertEquals('A', $skuError['column']);
    }

    public function test_validates_invalid_data_type(): void
    {
        $parseResult = new ParseResult(
            sheetsFound: ['05_Attribute'],
            data: [
                '05_Attribute' => [
                    2 => [
                        'technical_name' => 'test-attr',
                        'name_de' => 'Test',
                        'name_en' => null,
                        'description' => null,
                        'data_type' => 'Texxt', // Tippfehler
                        'attribute_group' => null,
                        'value_list' => null,
                        'unit_group' => null,
                        'default_unit' => null,
                        'is_multipliable' => null,
                        'max_multiplied' => null,
                        'is_translatable' => null,
                        'is_mandatory' => null,
                        'is_unique' => null,
                        'is_searchable' => null,
                        'is_inheritable' => null,
                        'parent_attribute' => null,
                        'source_system' => null,
                        'views' => null,
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->validator->validate($parseResult);

        $this->assertTrue($result->hasErrors);
        $dataTypeError = collect($result->errors)->firstWhere('field', 'Datentyp');
        $this->assertNotNull($dataTypeError);
        $this->assertStringContainsString('Ungültiger Datentyp', $dataTypeError['error']);
    }

    public function test_validates_product_type_reference(): void
    {
        // Produkttyp in DB anlegen
        ProductType::create([
            'id' => 'pt-uuid-1',
            'technical_name' => 'physical_product',
            'name_de' => 'Physisches Produkt',
            'has_variants' => true,
            'has_ean' => true,
            'has_prices' => true,
            'has_media' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'SKU-001',
                        'name' => 'Test Product',
                        'name_en' => null,
                        'product_type' => 'nonexistent_type',
                        'ean' => null,
                        'status' => 'draft',
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->validator->validate($parseResult);

        $this->assertTrue($result->hasErrors);
        $typeError = collect($result->errors)->firstWhere('field', 'Produkttyp');
        $this->assertNotNull($typeError);
        $this->assertStringContainsString('nicht gefunden', $typeError['error']);
    }

    public function test_validates_invalid_product_status(): void
    {
        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'SKU-001',
                        'name' => 'Test',
                        'name_en' => null,
                        'product_type' => 'physical_product',
                        'ean' => null,
                        'status' => 'invalid_status',
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->validator->validate($parseResult);

        $statusError = collect($result->errors)->firstWhere('field', 'Status');
        $this->assertNotNull($statusError);
        $this->assertStringContainsString('Ungültiger Status', $statusError['error']);
    }

    public function test_validation_summary_counts(): void
    {
        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'SKU-001', 'name' => 'Test', 'name_en' => null,
                        'product_type' => 'x', 'ean' => null, 'status' => 'draft', '_row' => 2,
                    ],
                    3 => [
                        'sku' => 'SKU-002', 'name' => 'Test2', 'name_en' => null,
                        'product_type' => 'x', 'ean' => null, 'status' => 'draft', '_row' => 3,
                    ],
                    4 => [
                        'sku' => null, 'name' => null, 'name_en' => null, // Fehler
                        'product_type' => null, 'ean' => null, 'status' => null, '_row' => 4,
                    ],
                ],
            ],
        );

        $result = $this->validator->validate($parseResult);

        $this->assertArrayHasKey('08_Produkte', $result->summary);
        $this->assertEquals(3, $result->summary['08_Produkte']['total']);
    }

    public function test_fuzzy_matcher_standalone(): void
    {
        $matcher = new FuzzyMatcher();

        // "Gwicht" → "Gewicht" (85%+ Ähnlichkeit)
        $result = $matcher->findMatch('Gwicht', ['Gewicht', 'Farbe', 'Material', 'Breite']);
        $this->assertNotNull($result);
        $this->assertEquals('Gewicht', $result->match);
        $this->assertGreaterThanOrEqual(0.85, $result->similarity);

        // Exakter Match
        $result = $matcher->findMatch('Farbe', ['Gewicht', 'Farbe', 'Material']);
        $this->assertNotNull($result);
        $this->assertTrue($result->exact);

        // Case-insensitive
        $result = $matcher->findMatch('gewicht', ['Gewicht', 'Farbe']);
        $this->assertNotNull($result);
        $this->assertTrue($result->exact);

        // Kein Match
        $result = $matcher->findMatch('xyz', ['Gewicht', 'Farbe', 'Material']);
        $this->assertNull($result);
    }

    public function test_validates_invalid_language_code(): void
    {
        $parseResult = new ParseResult(
            sheetsFound: ['09_Produktwerte'],
            data: [
                '09_Produktwerte' => [
                    2 => [
                        'sku' => 'SKU-001',
                        'attribute' => 'weight',
                        'value' => '4.5',
                        'unit' => null,
                        'language' => 'deutsch', // Ungültig
                        'index' => null,
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->validator->validate($parseResult);

        $langError = collect($result->errors)->firstWhere('field', 'Sprache');
        $this->assertNotNull($langError);
        $this->assertStringContainsString('Ungültiger Sprachcode', $langError['error']);
    }

    public function test_validates_non_numeric_price(): void
    {
        $parseResult = new ParseResult(
            sheetsFound: ['13_Preise'],
            data: [
                '13_Preise' => [
                    2 => [
                        'sku' => 'SKU-001',
                        'price_type' => 'list_price',
                        'amount' => 'nicht-numerisch',
                        'currency' => 'EUR',
                        'valid_from' => null,
                        'valid_to' => null,
                        'country' => null,
                        'scale_from' => null,
                        'scale_to' => null,
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->validator->validate($parseResult);

        $priceError = collect($result->errors)->firstWhere('field', 'Betrag');
        $this->assertNotNull($priceError);
        $this->assertStringContainsString('Zahl', $priceError['error']);
    }
}
