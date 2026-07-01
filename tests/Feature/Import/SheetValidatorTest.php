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

    public function test_detects_shifted_columns_and_reports_single_clear_error(): void
    {
        // Reproduziert einen realen Kundenfall: In der Datei fehlt die Spalte "Max.
        // Vermehrungen" (K), wodurch "Übersetzbar" nach K rutscht, "Pflicht" nach L
        // und "Quellsystem" nach M. Ohne Kopfzeilen-Prüfung würde z.B. der Wert aus
        // "Pflicht" als is_translatable gelesen und einen verwirrenden Ja/Nein-Fehler
        // auslösen – erwartet wird stattdessen EIN klarer Hinweis auf die Spaltenverschiebung.
        $parseResult = new ParseResult(
            sheetsFound: ['05_Attribute'],
            data: [
                '05_Attribute' => [
                    2 => [
                        'technical_name' => 'marke',
                        'name_de' => 'Marke',
                        'name_en' => null,
                        'description' => null,
                        'data_type' => 'String',
                        'attribute_group' => null,
                        'value_list' => null,
                        'unit_group' => null,
                        'default_unit' => null,
                        'is_multipliable' => null,
                        'max_multiplied' => 'Ja', // eigentlich "Übersetzbar"-Wert
                        'is_translatable' => 'Pflicht', // eigentlich "Pflicht"-Wert
                        'is_mandatory' => 'SAP ERP', // eigentlich "Quellsystem"-Wert
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
            headers: [
                '05_Attribute' => [
                    'A' => 'Technischer Name*',
                    'B' => 'Name (Deutsch)*',
                    'C' => 'Name (Englisch)',
                    'D' => 'Beschreibung',
                    'E' => 'Datentyp*',
                    'F' => 'Attributgruppe',
                    'G' => 'Werteliste',
                    'H' => 'Einheitengruppe',
                    'I' => 'Standard-Einheit',
                    'J' => 'Vermehrbar',
                    'K' => 'Übersetzbar', // erwartet: "Max. Vermehrungen"
                    'L' => 'Pflicht',     // erwartet: "Übersetzbar (Ja/Nein)"
                    'M' => 'Quellsystem', // erwartet: "Pflicht (Optional/Pflicht)"
                ],
            ],
        );

        $result = $this->validator->validate($parseResult);

        $this->assertTrue($result->hasErrors);

        $headerErrors = collect($result->errors)->where('field', 'Spaltenkopf');
        $this->assertCount(1, $headerErrors, 'Es sollte genau ein zusammengefasster Kopfzeilen-Fehler entstehen, keine Flut von Folgefehlern.');

        $headerError = $headerErrors->first();
        $this->assertStringContainsString('Spaltenreihenfolge', $headerError['error']);
        $this->assertStringContainsString('Spalte K', $headerError['error']);

        // Keine verwirrenden Ja/Nein-Folgefehler auf den falsch zugeordneten Werten
        $this->assertCount(0, collect($result->errors)->where('field', 'is_translatable'));

        $this->assertEquals(0, $result->summary['05_Attribute']['valid']);
        $this->assertEquals(1, $result->summary['05_Attribute']['errors']);
    }

    public function test_tolerates_missing_trailing_columns(): void
    {
        // Eine Datei, die nur die ersten 5 Spalten der Vorlage befüllt (Rest weggelassen),
        // ist gültig und darf keinen Kopfzeilen-Fehler auslösen.
        $parseResult = new ParseResult(
            sheetsFound: ['05_Attribute'],
            data: [
                '05_Attribute' => [
                    2 => [
                        'technical_name' => 'weight',
                        'name_de' => 'Gewicht',
                        'name_en' => null,
                        'description' => null,
                        'data_type' => 'Number',
                        '_row' => 2,
                    ],
                ],
            ],
            headers: [
                '05_Attribute' => [
                    'A' => 'Technischer Name*',
                    'B' => 'Name (Deutsch)*',
                    'C' => 'Name (Englisch)',
                    'D' => 'Beschreibung',
                    'E' => 'Datentyp*',
                ],
            ],
        );

        $result = $this->validator->validate($parseResult);

        $this->assertCount(0, collect($result->errors)->where('field', 'Spaltenkopf'));
    }

    public function test_tolerates_differently_worded_but_correctly_placed_headers(): void
    {
        // Reale Kundendatei: Spalten sind inhaltlich korrekt befüllt, nur anders benannt
        // als die Vorlage ("Werteliste" statt "Liste Techn. Name", "Wert-Code" statt
        // "Eintrag Techn. Name" usw.). Das darf NICHT als Spaltenverschiebung gemeldet
        // werden, weil die gefundenen Texte zu keiner anderen Spalte der Vorlage passen.
        $parseResult = new ParseResult(
            sheetsFound: ['04_Wertelisten'],
            data: [
                '04_Wertelisten' => [
                    2 => [
                        'list_technical_name' => 'farben',
                        'list_name_de' => 'Farben',
                        'entry_technical_name' => 'rot',
                        'display_value_de' => 'Rot',
                        'display_value_en' => 'Red',
                        'sort_order' => null,
                        '_row' => 2,
                    ],
                ],
            ],
            headers: [
                '04_Wertelisten' => [
                    'A' => 'Werteliste*',
                    'B' => 'Listenname (Deutsch)*',
                    'C' => 'Wert-Code*',
                    'D' => 'Wert (Deutsch)*',
                    'E' => 'Wert (Englisch)',
                ],
            ],
        );

        $result = $this->validator->validate($parseResult);

        $this->assertCount(0, collect($result->errors)->where('field', 'Spaltenkopf'));
    }

    public function test_tolerates_minor_header_variations(): void
    {
        // Fehlendes Pflicht-Sternchen, Klammer-Hinweis und andere Groß-/Kleinschreibung
        // dürfen keinen Kopfzeilen-Fehler auslösen (nur echte inhaltliche Abweichungen).
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
                        'status' => 'active',
                        '_row' => 2,
                    ],
                ],
            ],
            headers: [
                '08_Produkte' => [
                    'A' => 'sku', // ohne Sternchen, klein geschrieben
                    'B' => 'Produktname',
                    'C' => 'Produktname (EN)',
                    'D' => 'Produkttyp',
                    'E' => 'EAN',
                    'F' => 'STATUS (draft/active/inactive)', // Klammer-Hinweis + Großschreibung
                ],
            ],
        );

        $result = $this->validator->validate($parseResult);

        $this->assertCount(0, collect($result->errors)->where('field', 'Spaltenkopf'));
    }
}
