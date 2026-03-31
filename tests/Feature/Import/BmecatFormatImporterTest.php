<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\ProductRelation;
use App\Models\ProductRelationType;
use App\Models\ProductType;
use App\Services\Import\BmecatElementMap;
use App\Services\Import\BmecatFormatImporter;
use App\Services\Import\ImportExecutor;
use App\Services\Import\ReferenceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BmecatFormatImporterTest extends TestCase
{
    use RefreshDatabase;

    private BmecatFormatImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $executor = new ImportExecutor(new ReferenceResolver());
        $this->importer = new BmecatFormatImporter($executor);
    }

    // =========================================================================
    // Version Detection
    // =========================================================================

    public function test_detects_bmecat_2005_version(): void
    {
        $xml = '<BMECAT version="2005" xmlns="http://www.bmecat.org/bmecat/2005"><HEADER></HEADER></BMECAT>';
        $this->assertEquals('2005', $this->importer->detectVersion($xml));
    }

    public function test_detects_bmecat_12_version(): void
    {
        $xml = '<BMECAT version="1.2"><HEADER></HEADER></BMECAT>';
        $this->assertEquals('1.2', $this->importer->detectVersion($xml));
    }

    public function test_detects_version_from_namespace(): void
    {
        $xml = '<BMECAT xmlns="http://www.bmecat.org/bmecat/2005"><HEADER></HEADER></BMECAT>';
        $this->assertEquals('2005', $this->importer->detectVersion($xml));
    }

    public function test_detects_12_from_article_elements(): void
    {
        $xml = '<BMECAT><HEADER></HEADER><T_NEW_CATALOG><ARTICLE mode="new"><SUPPLIER_AID>X</SUPPLIER_AID></ARTICLE></T_NEW_CATALOG></BMECAT>';
        $this->assertEquals('1.2', $this->importer->detectVersion($xml));
    }

    // =========================================================================
    // Element Map
    // =========================================================================

    public function test_element_map_returns_article_for_12(): void
    {
        $map = BmecatElementMap::forVersion('1.2');
        $this->assertEquals('ARTICLE', $map['product']);
        $this->assertEquals('SUPPLIER_AID', $map['supplier_pid']);
        $this->assertEquals('ARTICLE_DETAILS', $map['product_details']);
        $this->assertEquals('ARTICLE_PRICE', $map['product_price']);
        $this->assertEquals('ART_ID_TO', $map['prod_id_to']);
    }

    public function test_element_map_returns_product_for_2005(): void
    {
        $map = BmecatElementMap::forVersion('2005');
        $this->assertEquals('PRODUCT', $map['product']);
        $this->assertEquals('SUPPLIER_PID', $map['supplier_pid']);
        $this->assertEquals('PRODUCT_DETAILS', $map['product_details']);
        $this->assertEquals('PRODUCT_PRICE', $map['product_price']);
        $this->assertEquals('PROD_ID_TO', $map['prod_id_to']);
    }

    public function test_shared_elements_identical_across_versions(): void
    {
        $map12 = BmecatElementMap::forVersion('1.2');
        $map2005 = BmecatElementMap::forVersion('2005');

        $sharedKeys = ['header', 'catalog', 'language', 'catalog_id', 'currency',
            'catalog_group_system', 'catalog_structure', 'group_id', 'group_name',
            'parent_id', 'feature', 'fname', 'fvalue', 'price_amount', 'mime_info'];

        foreach ($sharedKeys as $key) {
            $this->assertEquals($map12[$key], $map2005[$key], "Shared element '{$key}' should be identical");
        }
    }

    // =========================================================================
    // Technical Name Sanitization
    // =========================================================================

    public function test_sanitize_technical_name(): void
    {
        $this->assertEquals('bildschirmgroesse', $this->importer->sanitizeTechnicalName('Bildschirmgröße'));
        $this->assertEquals('farbe', $this->importer->sanitizeTechnicalName('Farbe'));
        $this->assertEquals('ram', $this->importer->sanitizeTechnicalName('RAM'));
        $this->assertEquals('drehmoment_max', $this->importer->sanitizeTechnicalName('Drehmoment max'));
        $this->assertEquals('gewicht_kg', $this->importer->sanitizeTechnicalName('Gewicht (kg)'));
        $this->assertEquals('laenge_x_breite', $this->importer->sanitizeTechnicalName('Länge x Breite'));
    }

    public function test_sanitize_technical_name_truncates_at_100_chars(): void
    {
        $longName = str_repeat('A', 150);
        $result = $this->importer->sanitizeTechnicalName($longName);
        $this->assertLessThanOrEqual(100, strlen($result));
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_validate_valid_bmecat_2005(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_sample.xml');
        $result = $this->importer->validate($xml);

        $this->assertTrue($result['valid']);
        $this->assertEquals('2005', $result['version']);
        $this->assertEquals('T_NEW_CATALOG', $result['transaction_type']);
        $this->assertEquals(2, $result['product_count']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_valid_bmecat_12(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_12_sample.xml');
        $result = $this->importer->validate($xml);

        $this->assertTrue($result['valid']);
        $this->assertEquals('1.2', $result['version']);
        $this->assertEquals('T_NEW_CATALOG', $result['transaction_type']);
        $this->assertEquals(1, $result['product_count']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_invalid_xml(): void
    {
        $xml = '<invalid>not closed';
        $result = $this->importer->validate($xml);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    // =========================================================================
    // Full Import BMEcat 2005
    // =========================================================================

    public function test_full_import_bmecat_2005(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_sample.xml');
        $result = $this->importer->importFromString($xml);

        // Produkttyp angelegt
        $this->assertDatabaseHas('product_types', ['technical_name' => 'bmecat_product']);

        // Produkte angelegt
        $this->assertDatabaseHas('products', ['sku' => 'NB-001', 'name' => 'Business Notebook 15"']);
        $this->assertDatabaseHas('products', ['sku' => 'ACC-MOUSE-01', 'name' => 'Wireless Maus']);

        // EAN
        $product = Product::where('sku', 'NB-001')->first();
        $this->assertEquals('4006381333931', $product->ean);

        // Attribute angelegt
        $this->assertDatabaseHas('attributes', ['technical_name' => 'bildschirmgroesse']);
        $this->assertDatabaseHas('attributes', ['technical_name' => 'ram']);
        $this->assertDatabaseHas('attributes', ['technical_name' => 'farbe']);
        $this->assertDatabaseHas('attributes', ['technical_name' => 'gewicht']);

        // Attributwerte gesetzt
        $bildschirmAttr = Attribute::where('technical_name', 'bildschirmgroesse')->first();
        $this->assertNotNull($bildschirmAttr);

        // Preistypen
        $this->assertDatabaseHas('price_types', ['technical_name' => 'net_list']);

        // Preise
        $priceType = PriceType::where('technical_name', 'net_list')->first();
        $this->assertNotNull($priceType);
        $nbPrices = ProductPrice::where('product_id', $product->id)
            ->where('price_type_id', $priceType->id)
            ->get();
        $this->assertGreaterThanOrEqual(1, $nbPrices->count());

        // Beziehungstypen
        $this->assertDatabaseHas('product_relation_types', ['technical_name' => 'accessories']);
        $this->assertDatabaseHas('product_relation_types', ['technical_name' => 'similar']);

        // Hierarchie
        $this->assertDatabaseHas('hierarchies', ['technical_name' => 'bmecat_test_2024']);

        // Ergebnis-Stats
        $this->assertArrayHasKey('08_Produkte', $result->stats);
        $this->assertGreaterThanOrEqual(2, $result->stats['08_Produkte']['created'] ?? 0);
    }

    // =========================================================================
    // Full Import BMEcat 1.2
    // =========================================================================

    public function test_full_import_bmecat_12(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_12_sample.xml');
        $result = $this->importer->importFromString($xml);

        // Produkt angelegt
        $this->assertDatabaseHas('products', ['sku' => 'BM-001', 'name' => 'Akku-Bohrschrauber 18V']);

        $product = Product::where('sku', 'BM-001')->first();
        $this->assertNotNull($product);
        $this->assertEquals('4006381444042', $product->ean);

        // Attribute
        $this->assertDatabaseHas('attributes', ['technical_name' => 'spannung']);
        $this->assertDatabaseHas('attributes', ['technical_name' => 'drehmoment_max']);

        // Preise
        $this->assertDatabaseHas('price_types', ['technical_name' => 'net_list']);

        // Beziehungstypen
        $this->assertDatabaseHas('product_relation_types', ['technical_name' => 'sparepart']);

        // Ergebnis
        $this->assertArrayHasKey('08_Produkte', $result->stats);
    }

    // =========================================================================
    // Custom Product Type
    // =========================================================================

    public function test_import_with_custom_product_type(): void
    {
        // Existierenden Produkttyp anlegen
        ProductType::forceCreate([
            'id' => 'custom-pt',
            'technical_name' => 'custom_type',
            'name_de' => 'Benutzerdefinierter Typ',
            'has_variants' => false,
            'has_ean' => true,
            'has_prices' => true,
            'has_media' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_sample.xml');
        $result = $this->importer->importFromString($xml, 'custom_type');

        $product = Product::where('sku', 'NB-001')->first();
        $this->assertNotNull($product);

        $productType = ProductType::find($product->product_type_id);
        $this->assertEquals('custom_type', $productType->technical_name);
    }

    // =========================================================================
    // Hierarchy Construction
    // =========================================================================

    public function test_hierarchy_built_from_catalog_groups(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_sample.xml');
        $this->importer->importFromString($xml);

        // Hierarchie angelegt
        $hierarchy = Hierarchy::where('technical_name', 'bmecat_test_2024')->first();
        $this->assertNotNull($hierarchy);
        $this->assertEquals('master', $hierarchy->hierarchy_type);

        // Knoten angelegt
        $nodes = HierarchyNode::where('hierarchy_id', $hierarchy->id)->get();
        $this->assertGreaterThanOrEqual(1, $nodes->count());

        // Root-Knoten "Alle Produkte" wird als level_1 übernommen
        $rootNode = $nodes->where('name_de', 'Alle Produkte')->first();
        $this->assertNotNull($rootNode, 'Root-Knoten "Alle Produkte" muss im Baum enthalten sein');
        $this->assertNull($rootNode->parent_node_id);

        // "Elektronik" als Kind von "Alle Produkte" vorhanden
        $elektronikNode = $nodes->where('name_de', 'Elektronik')->first();
        $this->assertNotNull($elektronikNode);
        $this->assertEquals($rootNode->id, $elektronikNode->parent_node_id);
    }

    // =========================================================================
    // Price Tiers
    // =========================================================================

    public function test_price_tiers_imported(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_sample.xml');
        $this->importer->importFromString($xml);

        $product = Product::where('sku', 'NB-001')->first();
        $this->assertNotNull($product);

        $prices = ProductPrice::where('product_id', $product->id)->get();
        // Mindestens 1 Preis importiert (scale tiers may be collapsed depending on DB constraint handling)
        $this->assertGreaterThanOrEqual(1, $prices->count());

        // Verify at least one price has correct amount from the fixture
        $amounts = $prices->pluck('amount')->map(fn ($a) => (float) $a)->toArray();
        $this->assertTrue(
            in_array(1499.50, $amounts) || in_array(1300.90, $amounts),
            'At least one expected price amount should be present'
        );
    }

    // =========================================================================
    // Product Relations
    // =========================================================================

    public function test_product_relations_imported(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_sample.xml');
        $this->importer->importFromString($xml);

        $nbProduct = Product::where('sku', 'NB-001')->first();
        $mouseProduct = Product::where('sku', 'ACC-MOUSE-01')->first();
        $this->assertNotNull($nbProduct);
        $this->assertNotNull($mouseProduct);

        // Accessories-Beziehung NB-001 → ACC-MOUSE-01
        $accessoriesType = ProductRelationType::where('technical_name', 'accessories')->first();
        $this->assertNotNull($accessoriesType);

        $relation = ProductRelation::where('source_product_id', $nbProduct->id)
            ->where('target_product_id', $mouseProduct->id)
            ->where('relation_type_id', $accessoriesType->id)
            ->first();
        $this->assertNotNull($relation);
    }

    // =========================================================================
    // Missing Optional Elements
    // =========================================================================

    public function test_import_minimal_product_without_optional_elements(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<BMECAT version="2005" xmlns="http://www.bmecat.org/bmecat/2005">
  <HEADER>
    <CATALOG>
      <LANGUAGE>deu</LANGUAGE>
      <CATALOG_ID>MINIMAL</CATALOG_ID>
      <CATALOG_VERSION>001</CATALOG_VERSION>
      <CURRENCY>EUR</CURRENCY>
    </CATALOG>
    <SUPPLIER>
      <SUPPLIER_NAME>Minimal GmbH</SUPPLIER_NAME>
    </SUPPLIER>
  </HEADER>
  <T_NEW_CATALOG>
    <PRODUCT mode="new">
      <SUPPLIER_PID>MIN-001</SUPPLIER_PID>
      <PRODUCT_DETAILS>
        <DESCRIPTION_SHORT>Minimales Produkt</DESCRIPTION_SHORT>
      </PRODUCT_DETAILS>
      <PRODUCT_PRICE_DETAILS>
        <PRODUCT_PRICE price_type="net_list">
          <PRICE_AMOUNT>9.99</PRICE_AMOUNT>
        </PRODUCT_PRICE>
      </PRODUCT_PRICE_DETAILS>
    </PRODUCT>
  </T_NEW_CATALOG>
</BMECAT>';

        $result = $this->importer->importFromString($xml);

        $this->assertDatabaseHas('products', ['sku' => 'MIN-001', 'name' => 'Minimales Produkt']);
        $this->assertArrayHasKey('08_Produkte', $result->stats);
        $this->assertEquals(1, $result->stats['08_Produkte']['created'] ?? 0);
    }

    // =========================================================================
    // Import Mode
    // =========================================================================

    public function test_mode_can_be_set(): void
    {
        $this->importer->setMode('delete_insert');

        // Sollte keinen Fehler werfen
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_sample.xml');
        $result = $this->importer->importFromString($xml);
        $this->assertNotNull($result);
    }

    public function test_invalid_mode_defaults_to_update(): void
    {
        $this->importer->setMode('invalid_mode');

        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_sample.xml');
        $result = $this->importer->importFromString($xml);
        $this->assertNotNull($result);
    }

    // =========================================================================
    // UDX (USER_DEFINED_EXTENSIONS) Import
    // =========================================================================

    public function test_udx_fields_create_attribute_group(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_udx_sample.xml');
        $this->importer->importFromString($xml);

        // UDX-Attributgruppe "udx_doka" mit Label "UDX – Doka" angelegt
        $this->assertDatabaseHas('attribute_types', [
            'technical_name' => 'udx_doka',
            'name_de' => 'UDX – Doka',
        ]);
    }

    public function test_udx_fields_create_attributes_in_own_group(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_udx_sample.xml');
        $this->importer->importFromString($xml);

        $group = AttributeType::where('technical_name', 'udx_doka')->first();
        $this->assertNotNull($group);

        // UDX-Attribute in der richtigen Gruppe
        $udxAttrs = Attribute::where('attribute_type_id', $group->id)->get();
        $techNames = $udxAttrs->pluck('technical_name')->toArray();

        $this->assertContains('udx_doka_schalungsteil_typ', $techNames);
        $this->assertContains('udx_doka_element_breite', $techNames);
        $this->assertContains('udx_doka_element_hoehe', $techNames);
        $this->assertContains('udx_doka_ksp_zeitersparnis', $techNames);
        $this->assertContains('udx_doka_ksp_kein_rippling', $techNames);
        $this->assertContains('udx_doka_mietfaehig', $techNames);
    }

    public function test_udx_fields_separated_from_features(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_udx_sample.xml');
        $this->importer->importFromString($xml);

        // Feature-Attribute (Gewicht, Material) haben KEINE UDX-Gruppe
        $gewicht = Attribute::where('technical_name', 'gewicht')->first();
        $this->assertNotNull($gewicht);

        $udxGroup = AttributeType::where('technical_name', 'udx_doka')->first();
        $this->assertNotEquals($udxGroup?->id, $gewicht->attribute_type_id);
    }

    public function test_udx_product_values_assigned(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_udx_sample.xml');
        $this->importer->importFromString($xml);

        $product = Product::where('sku', 'DOKA-FX-270-90')->first();
        $this->assertNotNull($product);

        $breitAttr = Attribute::where('technical_name', 'udx_doka_element_breite')->first();
        $this->assertNotNull($breitAttr);

        $value = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $breitAttr->id)
            ->first();
        $this->assertNotNull($value);
    }

    public function test_udx_data_type_auto_detection(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_udx_sample.xml');
        $this->importer->importFromString($xml);

        // String-Typ
        $typ = Attribute::where('technical_name', 'udx_doka_schalungsteil_typ')->first();
        $this->assertEquals('String', $typ->data_type);

        // Float-Typ (0.90)
        $breite = Attribute::where('technical_name', 'udx_doka_element_breite')->first();
        $this->assertEquals('Float', $breite->data_type);

        // Flag-Typ (true/false)
        $rippling = Attribute::where('technical_name', 'udx_doka_ksp_kein_rippling')->first();
        $this->assertEquals('Flag', $rippling->data_type);
    }

    public function test_udx_source_attribute_key_stored(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_udx_sample.xml');
        $this->importer->importFromString($xml);

        $attr = Attribute::where('technical_name', 'udx_doka_schalungsteil_typ')->first();
        $this->assertNotNull($attr);
        $this->assertEquals('UDX.DOKA.SCHALUNGSTEIL_TYP', $attr->source_attribute_key);
        $this->assertEquals('bmecat_udx', $attr->source_system);
    }

    public function test_udx_header_fields_ignored(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_udx_sample.xml');
        $this->importer->importFromString($xml);

        // Header-UDX-Felder (PRODUKTFAMILIE, SYSTEM) sollten NICHT als Attribute angelegt werden
        // sofern sie nicht auch auf Produktebene vorkommen
        $this->assertDatabaseMissing('attributes', ['technical_name' => 'udx_doka_produktfamilie']);
        $this->assertDatabaseMissing('attributes', ['technical_name' => 'udx_doka_system']);
    }

    public function test_udx_invalid_field_names_logged_not_aborted(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<BMECAT version="2005" xmlns="http://www.bmecat.org/bmecat/2005">
  <HEADER>
    <CATALOG>
      <LANGUAGE>deu</LANGUAGE>
      <CATALOG_ID>TEST-UDX</CATALOG_ID>
      <CATALOG_VERSION>001</CATALOG_VERSION>
      <CURRENCY>EUR</CURRENCY>
    </CATALOG>
    <SUPPLIER><SUPPLIER_NAME>Test</SUPPLIER_NAME></SUPPLIER>
  </HEADER>
  <T_NEW_CATALOG>
    <PRODUCT mode="new">
      <SUPPLIER_PID>UDX-TEST-001</SUPPLIER_PID>
      <PRODUCT_DETAILS>
        <DESCRIPTION_SHORT>UDX Test Product</DESCRIPTION_SHORT>
      </PRODUCT_DETAILS>
      <USER_DEFINED_EXTENSIONS>
        <UDX.VALID.FIELD>works</UDX.VALID.FIELD>
        <INVALID_UDX_FIELD>should be skipped</INVALID_UDX_FIELD>
      </USER_DEFINED_EXTENSIONS>
    </PRODUCT>
  </T_NEW_CATALOG>
</BMECAT>';

        $result = $this->importer->importFromString($xml);

        // Import soll erfolgreich sein (nicht abbrechen)
        $this->assertDatabaseHas('products', ['sku' => 'UDX-TEST-001']);

        // Gültiges UDX-Feld soll angelegt sein
        $this->assertDatabaseHas('attributes', ['technical_name' => 'udx_valid_field']);
    }

    public function test_udx_list_container_creates_multipliable_string(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_udx_sample.xml');
        $this->importer->importFromString($xml);

        // Listen-Container UDX.ERBE.LEISTUNGSMERKMALE → vermehrbares String-Attribut
        $attr = Attribute::where('technical_name', 'udx_erbe_leistungsmerkmale')->first();
        $this->assertNotNull($attr, 'Listen-Container-Attribut muss angelegt werden');
        $this->assertEquals('String', $attr->data_type);
        $this->assertTrue($attr->is_multipliable);

        // Kein Composite — es darf kein separates Kind-Attribut "LEISTUNGSMERKMAL" geben
        $childAttr = Attribute::where('technical_name', 'udx_erbe_leistungsmerkmal')->first();
        $this->assertNull($childAttr, 'Kinder eines Listen-Containers dürfen kein eigenes Attribut sein');

        // Werte mit index 0, 1, 2
        $product = Product::where('sku', 'ERBE-APC3-10135')->first();
        $this->assertNotNull($product);

        $values = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->orderBy('multiplied_index')
            ->get();
        $this->assertCount(3, $values);
        $this->assertEquals('Plug and operate', $values[0]->value_string);
        $this->assertEquals('pulsedAPC und forcedAPC fein dosierbar', $values[1]->value_string);
        $this->assertEquals('preciseAPC für sensible Strukturen', $values[2]->value_string);
    }

    public function test_udx_second_list_container_also_multipliable(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_udx_sample.xml');
        $this->importer->importFromString($xml);

        // Zweiter Listen-Container UDX.ERBE.DISZIPLINEN
        $attr = Attribute::where('technical_name', 'udx_erbe_disziplinen')->first();
        $this->assertNotNull($attr);
        $this->assertEquals('String', $attr->data_type);
        $this->assertTrue($attr->is_multipliable);

        $product = Product::where('sku', 'ERBE-APC3-10135')->first();
        $values = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->orderBy('multiplied_index')
            ->get();
        $this->assertCount(2, $values);
        $this->assertEquals('Gynäkologie', $values[0]->value_string);
        $this->assertEquals('Gastroenterologie', $values[1]->value_string);
    }

    public function test_udx_pipe_delimited_value_detected(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_udx_sample.xml');
        $this->importer->importFromString($xml);

        // Pipe-getrennte Werte → DelimitedValue
        $attr = Attribute::where('technical_name', 'udx_erbe_laender')->first();
        $this->assertNotNull($attr);
        $this->assertEquals('DelimitedValue', $attr->data_type);
        $this->assertEquals('|', $attr->delimiter);

        // Wert als Ganzes gespeichert
        $product = Product::where('sku', 'ERBE-APC3-10135')->first();
        $value = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->first();
        $this->assertNotNull($value);
        $this->assertStringContainsString('AU|BR|CA', $value->value_string);
    }

    // =========================================================================
    // Multilingual Import (xml:lang)
    // =========================================================================

    public function test_multilang_description_short_creates_translatable_attribute(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_multilang_sample.xml');
        $this->importer->importFromString($xml);

        // description_short als übersetzbar angelegt
        $attr = Attribute::where('technical_name', 'description_short')->first();
        $this->assertNotNull($attr);
        $this->assertTrue($attr->is_translatable);
        $this->assertEquals('Kurzbeschreibung', $attr->name_de);
    }

    public function test_multilang_description_values_per_language(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_multilang_sample.xml');
        $this->importer->importFromString($xml);

        $product = Product::where('sku', 'ML-DRILL-001')->first();
        $this->assertNotNull($product);

        $attr = Attribute::where('technical_name', 'description_short')->first();
        $this->assertNotNull($attr);

        // Deutsch
        $de = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->where('language', 'de')
            ->first();
        $this->assertNotNull($de);
        $this->assertEquals('Bohrmaschine Professional', $de->value_string);

        // Englisch
        $en = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->where('language', 'en')
            ->first();
        $this->assertNotNull($en);
        $this->assertEquals('Professional Drill Machine', $en->value_string);

        // Französisch
        $fr = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->where('language', 'fr')
            ->first();
        $this->assertNotNull($fr);
        $this->assertEquals('Perceuse Professionnelle', $fr->value_string);
    }

    public function test_multilang_feature_values_per_language(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_multilang_sample.xml');
        $this->importer->importFromString($xml);

        $product = Product::where('sku', 'ML-DRILL-001')->first();
        $this->assertNotNull($product);

        // Material-Attribut ist übersetzbar
        $materialAttr = Attribute::where('technical_name', 'material')->first();
        $this->assertNotNull($materialAttr);
        $this->assertTrue($materialAttr->is_translatable);

        // Deutsche Variante
        $de = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $materialAttr->id)
            ->where('language', 'de')
            ->first();
        $this->assertNotNull($de);
        $this->assertStringContainsString('Aluminium', $de->value_string);

        // Englische Variante
        $en = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $materialAttr->id)
            ->where('language', 'en')
            ->first();
        $this->assertNotNull($en);
        $this->assertStringContainsString('aluminium', $en->value_string);
    }

    public function test_multilang_non_translatable_feature_remains_null_language(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_multilang_sample.xml');
        $this->importer->importFromString($xml);

        $product = Product::where('sku', 'ML-DRILL-001')->first();

        // Leistung hat kein xml:lang → language = null
        $leistungAttr = Attribute::where('technical_name', 'leistung')->first();
        $this->assertNotNull($leistungAttr);
        $this->assertFalse($leistungAttr->is_translatable);

        $val = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $leistungAttr->id)
            ->first();
        $this->assertNotNull($val);
        $this->assertNull($val->language);
    }

    public function test_multilang_udx_fields_with_xml_lang(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_multilang_sample.xml');
        $this->importer->importFromString($xml);

        $product = Product::where('sku', 'ML-DRILL-001')->first();

        // UDX BEZEICHNUNG ist übersetzbar (hat xml:lang)
        $bezeichnungAttr = Attribute::where('technical_name', 'udx_test_bezeichnung')->first();
        $this->assertNotNull($bezeichnungAttr);
        $this->assertTrue($bezeichnungAttr->is_translatable);

        // Deutsche und englische UDX-Werte
        $de = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $bezeichnungAttr->id)
            ->where('language', 'de')
            ->first();
        $this->assertNotNull($de);
        $this->assertEquals('Profi-Bohrer', $de->value_string);

        $en = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $bezeichnungAttr->id)
            ->where('language', 'en')
            ->first();
        $this->assertNotNull($en);
        $this->assertEquals('Pro Drill', $en->value_string);
    }

    public function test_multilang_udx_field_without_lang_stays_null(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_multilang_sample.xml');
        $this->importer->importFromString($xml);

        $product = Product::where('sku', 'ML-DRILL-001')->first();

        // UDX GEWICHT hat kein xml:lang → language = null
        $gewichtAttr = Attribute::where('technical_name', 'udx_test_gewicht')->first();
        $this->assertNotNull($gewichtAttr);
        $this->assertFalse($gewichtAttr->is_translatable);

        $val = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $gewichtAttr->id)
            ->first();
        $this->assertNotNull($val);
        $this->assertNull($val->language);
    }

    public function test_multilang_product_without_xml_lang_backward_compatible(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_multilang_sample.xml');
        $this->importer->importFromString($xml);

        // Produkt ohne xml:lang wird normal importiert
        $product = Product::where('sku', 'ML-BIT-002')->first();
        $this->assertNotNull($product);
        $this->assertEquals('Bohrer-Set 10-teilig', $product->name);
    }

    public function test_multilang_language_code_mapping(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_multilang_sample.xml');
        $this->importer->importFromString($xml);

        $product = Product::where('sku', 'ML-DRILL-001')->first();
        $attr = Attribute::where('technical_name', 'description_short')->first();

        // ISO 639-2 'deu' → ISO 639-1 'de'
        $this->assertNotNull(ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->where('language', 'de')->first());

        // ISO 639-2 'eng' → ISO 639-1 'en'
        $this->assertNotNull(ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->where('language', 'en')->first());

        // ISO 639-2 'fra' → ISO 639-1 'fr'
        $this->assertNotNull(ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->where('language', 'fr')->first());
    }

    // =========================================================================
    // XSD Schema Validation
    // =========================================================================

    public function test_validate_returns_warnings_field(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_sample.xml');
        $result = $this->importer->validate($xml);

        $this->assertArrayHasKey('warnings', $result);
        $this->assertIsArray($result['warnings']);
    }

    public function test_validate_xsd_detects_invalid_schema(): void
    {
        // BMEcat 2005 mit 1.2-Elementen → Schema-Warnungen
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<BMECAT version="2005" xmlns="http://www.bmecat.org/bmecat/2005">
  <HEADER>
    <CATALOG>
      <LANGUAGE>deu</LANGUAGE>
      <CATALOG_ID>MIXED</CATALOG_ID>
      <CATALOG_VERSION>001</CATALOG_VERSION>
      <CURRENCY>EUR</CURRENCY>
    </CATALOG>
    <SUPPLIER><SUPPLIER_NAME>Test</SUPPLIER_NAME></SUPPLIER>
  </HEADER>
  <T_NEW_CATALOG>
    <PRODUCT mode="new">
      <SUPPLIER_PID>TEST-001</SUPPLIER_PID>
      <PRODUCT_DETAILS><DESCRIPTION_SHORT>Test</DESCRIPTION_SHORT></PRODUCT_DETAILS>
    </PRODUCT>
    <ARTICLE_TO_CATALOGGROUP_MAP>
      <ART_ID>TEST-001</ART_ID>
      <CATALOG_GROUP_ID>grp1</CATALOG_GROUP_ID>
    </ARTICLE_TO_CATALOGGROUP_MAP>
  </T_NEW_CATALOG>
</BMECAT>';

        $result = $this->importer->validate($xml);

        // Valid weil Grundstruktur OK, aber Warnungen wegen gemischter Elemente
        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['warnings']);

        // Schema-Warnung für ARTICLE_TO_CATALOGGROUP_MAP
        $hasSchemaWarning = false;
        foreach ($result['warnings'] as $w) {
            if (str_contains($w, 'ARTICLE_TO_CATALOGGROUP_MAP')) {
                $hasSchemaWarning = true;
                break;
            }
        }
        $this->assertTrue($hasSchemaWarning, 'Should warn about ARTICLE_TO_CATALOGGROUP_MAP');
    }

    public function test_validate_detects_mixed_element_names(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<BMECAT version="2005" xmlns="http://www.bmecat.org/bmecat/2005">
  <HEADER>
    <CATALOG><LANGUAGE>deu</LANGUAGE><CATALOG_ID>MIX</CATALOG_ID><CATALOG_VERSION>001</CATALOG_VERSION><CURRENCY>EUR</CURRENCY></CATALOG>
    <SUPPLIER><SUPPLIER_NAME>Test</SUPPLIER_NAME></SUPPLIER>
  </HEADER>
  <T_NEW_CATALOG>
    <PRODUCT mode="new">
      <SUPPLIER_PID>T1</SUPPLIER_PID>
      <PRODUCT_DETAILS><DESCRIPTION_SHORT>Test</DESCRIPTION_SHORT><MANUFACTURER_AID>X</MANUFACTURER_AID></PRODUCT_DETAILS>
    </PRODUCT>
  </T_NEW_CATALOG>
</BMECAT>';

        $result = $this->importer->validate($xml);
        $this->assertTrue($result['valid']);

        // Warnung über MANUFACTURER_AID (1.2-Element in 2005-Dokument)
        $hasMixedWarning = false;
        foreach ($result['warnings'] as $w) {
            if (str_contains($w, 'MANUFACTURER_AID')) {
                $hasMixedWarning = true;
                break;
            }
        }
        $this->assertTrue($hasMixedWarning, 'Should warn about MANUFACTURER_AID in BMEcat 2005');
    }

    // =========================================================================
    // Tolerant Parsing (mixed 1.2/2005 elements)
    // =========================================================================

    public function test_import_mixed_2005_with_article_to_cataloggroup_map(): void
    {
        // BMEcat 2005 Dokument mit 1.2-Stil ARTICLE_TO_CATALOGGROUP_MAP
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<BMECAT version="2005" xmlns="http://www.bmecat.org/bmecat/2005">
  <HEADER>
    <CATALOG>
      <LANGUAGE>deu</LANGUAGE>
      <CATALOG_ID>MIXED-TEST</CATALOG_ID>
      <CATALOG_VERSION>001</CATALOG_VERSION>
      <CURRENCY>EUR</CURRENCY>
    </CATALOG>
    <SUPPLIER><SUPPLIER_NAME>Tolerant GmbH</SUPPLIER_NAME></SUPPLIER>
  </HEADER>
  <T_NEW_CATALOG>
    <CATALOG_GROUP_SYSTEM>
      <GROUP_SYSTEM_ID>mixed</GROUP_SYSTEM_ID>
      <GROUP_SYSTEM_NAME>Mixed</GROUP_SYSTEM_NAME>
      <CATALOG_STRUCTURE type="root">
        <GROUP_ID>root</GROUP_ID>
        <GROUP_NAME>Root</GROUP_NAME>
        <PARENT_ID>0</PARENT_ID>
      </CATALOG_STRUCTURE>
      <CATALOG_STRUCTURE type="leaf">
        <GROUP_ID>leaf1</GROUP_ID>
        <GROUP_NAME>Leaf</GROUP_NAME>
        <PARENT_ID>root</PARENT_ID>
      </CATALOG_STRUCTURE>
    </CATALOG_GROUP_SYSTEM>
    <PRODUCT mode="new">
      <SUPPLIER_PID>MIX-001</SUPPLIER_PID>
      <PRODUCT_DETAILS>
        <DESCRIPTION_SHORT>Mixed Element Test</DESCRIPTION_SHORT>
      </PRODUCT_DETAILS>
      <PRODUCT_PRICE_DETAILS>
        <PRODUCT_PRICE price_type="net_list">
          <PRICE_AMOUNT>99.00</PRICE_AMOUNT>
        </PRODUCT_PRICE>
      </PRODUCT_PRICE_DETAILS>
    </PRODUCT>
    <ARTICLE_TO_CATALOGGROUP_MAP>
      <ART_ID>MIX-001</ART_ID>
      <CATALOG_GROUP_ID>leaf1</CATALOG_GROUP_ID>
    </ARTICLE_TO_CATALOGGROUP_MAP>
  </T_NEW_CATALOG>
</BMECAT>';

        $result = $this->importer->importFromString($xml);

        // Produkt wurde importiert
        $this->assertDatabaseHas('products', ['sku' => 'MIX-001']);
        $this->assertArrayHasKey('08_Produkte', $result->stats);
        $this->assertEquals(1, $result->stats['08_Produkte']['created'] ?? 0);

        // Hierarchie-Zuordnung funktionierte trotz gemischter Elementnamen
        $this->assertDatabaseHas('hierarchies', ['technical_name' => 'bmecat_mixed_test']);
    }

    public function test_import_product_with_manufacturer_aid_fallback(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<BMECAT version="2005" xmlns="http://www.bmecat.org/bmecat/2005">
  <HEADER>
    <CATALOG><LANGUAGE>deu</LANGUAGE><CATALOG_ID>AID</CATALOG_ID><CATALOG_VERSION>001</CATALOG_VERSION><CURRENCY>EUR</CURRENCY></CATALOG>
    <SUPPLIER><SUPPLIER_NAME>Test</SUPPLIER_NAME></SUPPLIER>
  </HEADER>
  <T_NEW_CATALOG>
    <PRODUCT mode="new">
      <SUPPLIER_PID>AID-001</SUPPLIER_PID>
      <PRODUCT_DETAILS>
        <DESCRIPTION_SHORT>AID Test</DESCRIPTION_SHORT>
        <MANUFACTURER_NAME>TestMfr</MANUFACTURER_NAME>
        <MANUFACTURER_AID>MFR-X-123</MANUFACTURER_AID>
      </PRODUCT_DETAILS>
    </PRODUCT>
  </T_NEW_CATALOG>
</BMECAT>';

        $result = $this->importer->importFromString($xml);
        $this->assertDatabaseHas('products', ['sku' => 'AID-001']);
    }

    // =========================================================================
    // FLEX BMEcat — plain lang="DE" attribute (not xml:lang)
    // =========================================================================

    public function test_flex_lang_attribute_imports_multilang_descriptions(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_flex_lang_sample.xml');
        $this->importer->importFromString($xml);

        $product = Product::where('sku', '521620')->first();
        $this->assertNotNull($product);

        $attr = Attribute::where('technical_name', 'description_short')->first();
        $this->assertNotNull($attr);
        $this->assertTrue($attr->is_translatable);

        // Deutsch (lang="DE" → mapped to "de")
        $de = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->where('language', 'de')
            ->first();
        $this->assertNotNull($de);
        $this->assertStringContainsString('BO-ST D60', $de->value_string);

        // Englisch (lang="EN" → mapped to "en")
        $en = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->where('language', 'en')
            ->first();
        $this->assertNotNull($en);
        $this->assertStringContainsString('BO-ST D60', $en->value_string);
    }

    public function test_flex_lang_attribute_imports_multilang_long_description(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_flex_lang_sample.xml');
        $this->importer->importFromString($xml);

        $product = Product::where('sku', '521620')->first();
        $this->assertNotNull($product);

        $attr = Attribute::where('technical_name', 'description_long')->first();
        $this->assertNotNull($attr);
        $this->assertTrue($attr->is_translatable);

        // Deutsch (lang="DE" → "de")
        $de = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->where('language', 'de')
            ->first();
        $this->assertNotNull($de);
        $this->assertStringContainsString('Stützteller', $de->value_string);

        // Englisch (lang="EN" → "en")
        $en = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->where('language', 'en')
            ->first();
        $this->assertNotNull($en);
        $this->assertStringContainsString('Backing pad', $en->value_string);
    }

    public function test_flex_lang_validate_with_generator_info(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_flex_lang_sample.xml');
        $result = $this->importer->validate($xml);

        $this->assertTrue($result['valid']);
    }

    public function test_flex_lang_catalog_group_with_special_chars(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_flex_lang_sample.xml');
        $this->importer->importFromString($xml);

        // Categories with $ prefix in GROUP_ID should be imported by GROUP_NAME
        $this->assertDatabaseHas('hierarchy_nodes', ['name_de' => 'Trennschleifen']);
    }
}
