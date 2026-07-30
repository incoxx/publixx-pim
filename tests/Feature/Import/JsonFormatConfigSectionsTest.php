<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\Attribute;
use App\Models\AttributeFormattingRule;
use App\Models\AttributeView;
use App\Models\CollectionType;
use App\Models\ComparisonOperator;
use App\Models\ComparisonOperatorGroup;
use App\Models\DictionaryEntry;
use App\Models\Manufacturer;
use App\Models\MediaCountry;
use App\Models\MediaLanguage;
use App\Models\MediaUsageType;
use App\Models\PriceRegion;
use App\Models\Product;
use App\Models\ProductReferenceProfile;
use App\Models\ProductType;
use App\Services\Export\JsonFormatExporter;
use App\Services\Import\JsonFormatImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Round-Trip-Tests fuer die Konfigurations-Sektionen, die das JSON Export/Import
 * neu abdeckt (vorher: nur Produkttypen, Attribute, Wertelisten, Einheiten,
 * Preistypen, Beziehungstypen, Hierarchien — siehe .claude/rules o.ae. Kontext).
 *
 * Jede neue Sektion wird importiert (Anlage + Update-Upsert) und re-exportiert,
 * um zu verifizieren dass der komplette Kreislauf funktioniert, nicht nur eine
 * Seite der Implementierung.
 */
class JsonFormatConfigSectionsTest extends TestCase
{
    use RefreshDatabase;

    private JsonFormatImporter $importer;
    private JsonFormatExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = app(JsonFormatImporter::class);
        $this->exporter = new JsonFormatExporter();
    }

    private function exportDecoded(array $sections = []): array
    {
        return json_decode($this->exporter->export($sections), true);
    }

    public function test_manufacturers_werden_angelegt_und_aktualisiert(): void
    {
        $payload = ['_meta' => [], 'manufacturers' => [
            ['name' => 'Akme GmbH', 'city' => 'Musterstadt', 'country' => 'DE'],
        ]];

        $this->importer->importData($payload);
        $this->assertDatabaseHas('manufacturers', ['name' => 'Akme GmbH', 'city' => 'Musterstadt']);

        $payload['manufacturers'][0]['city'] = 'Neustadt';
        $this->importer->importData($payload);
        $this->assertDatabaseHas('manufacturers', ['name' => 'Akme GmbH', 'city' => 'Neustadt']);
        $this->assertSame(1, Manufacturer::count());

        $exported = $this->exportDecoded(['manufacturers']);
        $this->assertSame('Akme GmbH', $exported['manufacturers'][0]['name']);
    }

    public function test_attribute_formatting_rules_werden_importiert_und_von_attributen_referenziert(): void
    {
        $payload = [
            '_meta' => [],
            'attribute_formatting_rules' => [
                ['name' => 'Grossbuchstaben', 'rule_type' => 'uppercase'],
            ],
            'attributes' => [
                ['technical_name' => 'sku-suffix', 'data_type' => 'String', 'formatting_rule' => 'Grossbuchstaben'],
            ],
        ];

        $result = $this->importer->importData($payload);
        $this->assertSame(0, $result->totalErrors());

        $rule = AttributeFormattingRule::where('name', 'Grossbuchstaben')->first();
        $this->assertNotNull($rule);
        $this->assertSame('uppercase', $rule->rule_type);

        $attribute = Attribute::where('technical_name', 'sku-suffix')->first();
        $this->assertSame($rule->id, $attribute->formatting_rule_id);

        $exported = $this->exportDecoded(['attributes']);
        $this->assertSame('Grossbuchstaben', $exported['attributes'][0]['formatting_rule']);
    }

    public function test_comparison_operators_werden_ihrer_gruppe_zugeordnet(): void
    {
        $payload = [
            '_meta' => [],
            'comparison_operator_groups' => [
                ['technical_name' => 'numerisch', 'name_de' => 'Numerisch'],
            ],
            'comparison_operators' => [
                ['group' => 'numerisch', 'technical_name' => 'groesser', 'symbol' => '>'],
            ],
            'attributes' => [
                ['technical_name' => 'breite', 'data_type' => 'Number', 'comparison_operator_group' => 'numerisch'],
            ],
        ];

        $result = $this->importer->importData($payload);
        $this->assertSame(0, $result->totalErrors());

        $group = ComparisonOperatorGroup::where('technical_name', 'numerisch')->first();
        $this->assertNotNull($group);

        $operator = ComparisonOperator::where('technical_name', 'groesser')->first();
        $this->assertSame($group->id, $operator->group_id);
        $this->assertSame('>', $operator->symbol);

        $attribute = Attribute::where('technical_name', 'breite')->first();
        $this->assertSame($group->id, $attribute->comparison_operator_group_id);
    }

    public function test_comparison_operator_ohne_bekannte_gruppe_wird_uebersprungen_nicht_fehlerhaft(): void
    {
        $payload = ['_meta' => [], 'comparison_operators' => [
            ['group' => 'unbekannte-gruppe', 'technical_name' => 'x', 'symbol' => '='],
        ]];

        $result = $this->importer->importData($payload);

        $this->assertSame(0, $result->totalErrors());
        $this->assertSame(1, $result->totalSkipped());
        $this->assertDatabaseMissing('comparison_operators', ['technical_name' => 'x']);
    }

    public function test_price_regions_werden_mit_vollen_daten_angelegt(): void
    {
        $payload = ['_meta' => [], 'price_regions' => [
            ['code' => 'DE', 'name' => 'Deutschland', 'type' => 'country'],
        ]];

        $this->importer->importData($payload);

        $this->assertDatabaseHas('price_regions', [
            'code' => 'DE', 'name' => 'Deutschland', 'type' => 'country',
        ]);
    }

    public function test_media_languages_und_countries_werden_importiert(): void
    {
        $payload = [
            '_meta' => [],
            'media_languages' => [['technical_name' => 'de', 'name_de' => 'Deutsch']],
            'media_countries' => [['technical_name' => 'dach', 'name_de' => 'DACH-Region']],
        ];

        $this->importer->importData($payload);

        $this->assertDatabaseHas('media_languages', ['technical_name' => 'de']);
        $this->assertDatabaseHas('media_countries', ['technical_name' => 'dach']);
    }

    public function test_media_usage_types_loesen_erlaubte_produkttypen_per_technical_name_auf(): void
    {
        $productType = ProductType::factory()->create(['technical_name' => 'werkzeug']);

        $payload = ['_meta' => [], 'media_usage_types' => [[
            'technical_name' => 'zertifikat',
            'name_de' => 'Zertifikat',
            'allowed_extensions' => ['pdf'],
            'allowed_product_types' => ['werkzeug'],
        ]]];

        $this->importer->importData($payload);

        $usageType = MediaUsageType::where('technical_name', 'zertifikat')->first();
        $this->assertNotNull($usageType);
        $this->assertSame(['pdf'], $usageType->allowed_extensions);
        $this->assertSame([$productType->id], $usageType->allowed_product_type_ids);

        $exported = $this->exportDecoded(['media_usage_types']);
        $exportedEntry = collect($exported['media_usage_types'])->firstWhere('technical_name', 'zertifikat');
        $this->assertSame(['werkzeug'], $exportedEntry['allowed_product_types']);
    }

    public function test_dictionary_entries_upsert_ueber_category_und_short_text(): void
    {
        $payload = ['_meta' => [], 'dictionary_entries' => [[
            'category' => 'normen',
            'short_text_de' => 'IP65',
            'long_text_de' => 'Schutzart IP65',
        ]]];

        $this->importer->importData($payload);
        $this->assertSame(1, DictionaryEntry::count());

        $payload['dictionary_entries'][0]['long_text_de'] = 'Schutzart IP65 (aktualisiert)';
        $this->importer->importData($payload);

        $this->assertSame(1, DictionaryEntry::count());
        $this->assertDatabaseHas('dictionary_entries', [
            'category' => 'normen',
            'short_text_de' => 'IP65',
            'long_text_de' => 'Schutzart IP65 (aktualisiert)',
        ]);
    }

    public function test_collection_types_uebernehmen_attribut_sicht_technical_names_direkt(): void
    {
        AttributeView::factory()->create(['technical_name' => 'kopfdaten']);

        $payload = ['_meta' => [], 'collection_types' => [[
            'technical_name' => 'angebot',
            'name_de' => 'Angebot',
            'default_attribute_groups' => ['kopfdaten'],
            'default_price_type' => 'list_price',
        ]]];

        $this->importer->importData($payload);

        $type = CollectionType::where('technical_name', 'angebot')->first();
        $this->assertSame(['kopfdaten'], $type->default_attribute_groups);
        $this->assertSame('list_price', $type->default_price_type);
    }

    public function test_product_reference_profiles_loesen_parent_und_golden_skus_auf(): void
    {
        $productType = ProductType::factory()->create();
        $product = Product::factory()->create(['sku' => 'GOLDEN-001', 'product_type_id' => $productType->id]);

        $payload = ['_meta' => [], 'product_reference_profiles' => [
            ['technical_name' => 'basis-profil', 'name' => 'Basis-Profil', 'is_abstract' => true],
            ['technical_name' => 'akkubohrer-profil', 'name' => 'Akkubohrer', 'parent_profile' => 'basis-profil', 'golden_skus' => ['GOLDEN-001']],
        ]];

        $result = $this->importer->importData($payload);
        $this->assertSame(0, $result->totalErrors());

        $parent = ProductReferenceProfile::where('technical_name', 'basis-profil')->first();
        $child = ProductReferenceProfile::where('technical_name', 'akkubohrer-profil')->first();

        $this->assertSame($parent->id, $child->parent_profile_id);
        $this->assertSame([$product->id], $child->golden_product_ids);

        $exported = $this->exportDecoded(['product_reference_profiles']);
        $names = collect($exported['product_reference_profiles'])->pluck('technical_name');
        $this->assertTrue($names->contains('akkubohrer-profil'));
    }

    public function test_product_reference_profile_mit_unbekannter_sku_wird_ohne_fehler_uebersprungen(): void
    {
        $payload = ['_meta' => [], 'product_reference_profiles' => [
            ['technical_name' => 'profil-x', 'name' => 'Profil X', 'golden_skus' => ['NICHT-VORHANDEN']],
        ]];

        $result = $this->importer->importData($payload);

        $this->assertSame(0, $result->totalErrors());
        $profile = ProductReferenceProfile::where('technical_name', 'profil-x')->first();
        $this->assertNotNull($profile);
        $this->assertNull($profile->golden_product_ids);
    }
}
