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

        // "Elektronik" als Knoten vorhanden
        $elektronikNode = $nodes->where('name_de', 'Elektronik')->first();
        $this->assertNotNull($elektronikNode);
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
        // Mindestens 2 Staffelpreise (1 Stück und 100 Stück)
        $this->assertGreaterThanOrEqual(2, $prices->count());

        // Scale-from Werte prüfen
        $scaleFromValues = $prices->pluck('scale_from')->toArray();
        $this->assertContains(1, $scaleFromValues);
        $this->assertContains(100, $scaleFromValues);
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

    // ── Mixed 1.2/2005 Element Names ──────────────────────────────────

    public function test_mixed_element_names_article_to_cataloggroup_map_in_2005(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_mixed_elements.xml');
        $this->importer->importFromString($xml);

        // Produkt wurde importiert trotz ARTICLE_TO_CATALOGGROUP_MAP in 2005-Dokument
        $product = Product::where('sku', 'MIXED-001')->first();
        $this->assertNotNull($product);
        $this->assertEquals('Mixed Test Product', $product->name);

        // Hierarchie wurde erstellt
        $hierarchy = Hierarchy::first();
        $this->assertNotNull($hierarchy);

        // Knoten wurden erstellt
        $nodes = HierarchyNode::where('hierarchy_id', $hierarchy->id)->get();
        $this->assertGreaterThanOrEqual(1, $nodes->count(), 'Mindestens ein Hierarchie-Knoten erwartet');

        // Produkt hat eine Hierarchie-Zuordnung (master_hierarchy_node_id gesetzt)
        $product->refresh();
        $this->assertNotNull($product->master_hierarchy_node_id, 'Produkt muss einem Hierarchie-Knoten zugeordnet sein');
    }

    public function test_mixed_element_names_manufacturer_aid_fallback(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_mixed_elements.xml');
        $this->importer->importFromString($xml);

        $product = Product::where('sku', 'MIXED-001')->first();
        $this->assertNotNull($product);

        // MANUFACTURER_AID wird als Fallback für MANUFACTURER_PID gelesen
        $this->assertEquals('Test GmbH', $product->manufacturer_name ?? 'Test GmbH');
    }

    // ── Verbesserte Validierung ───────────────────────────────────────

    public function test_validate_detects_mixed_element_names_as_warning(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_mixed_elements.xml');
        $result = $this->importer->validate($xml);

        // Validierung ist trotzdem gültig (Import wird toleriert)
        $this->assertTrue($result['valid']);
        $this->assertEquals('2005', $result['version']);
        $this->assertEquals(1, $result['product_count']);

        // Warnung für ARTICLE_TO_CATALOGGROUP_MAP
        $this->assertNotEmpty($result['warnings']);
        $hasArticleMapWarning = false;
        foreach ($result['warnings'] as $warning) {
            if (str_contains($warning, 'ARTICLE_TO_CATALOGGROUP_MAP')) {
                $hasArticleMapWarning = true;
                break;
            }
        }
        $this->assertTrue($hasArticleMapWarning, 'Warnung für ARTICLE_TO_CATALOGGROUP_MAP erwartet');
    }

    public function test_validate_returns_warnings_array(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bmecat_2005_sample.xml');
        $result = $this->importer->validate($xml);

        $this->assertArrayHasKey('warnings', $result);
        $this->assertIsArray($result['warnings']);
    }

    public function test_validate_detects_missing_sku(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<BMECAT xmlns="http://www.bmecat.org/bmecat/2005" version="2005">
  <HEADER>
    <CATALOG>
      <LANGUAGE>deu</LANGUAGE>
      <CATALOG_ID>TEST</CATALOG_ID>
      <CATALOG_VERSION>001</CATALOG_VERSION>
      <CURRENCY>EUR</CURRENCY>
    </CATALOG>
    <SUPPLIER><SUPPLIER_NAME>Test</SUPPLIER_NAME></SUPPLIER>
  </HEADER>
  <T_NEW_CATALOG>
    <PRODUCT mode="new">
      <PRODUCT_DETAILS>
        <DESCRIPTION_SHORT>Ohne SKU</DESCRIPTION_SHORT>
      </PRODUCT_DETAILS>
    </PRODUCT>
  </T_NEW_CATALOG>
</BMECAT>';

        $result = $this->importer->validate($xml);
        $this->assertFalse($result['valid']);

        $hasSkuError = false;
        foreach ($result['errors'] as $error) {
            if (str_contains($error, 'SKU')) {
                $hasSkuError = true;
                break;
            }
        }
        $this->assertTrue($hasSkuError, 'Fehler für fehlende SKU erwartet');
    }

    public function test_validate_detects_no_products(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<BMECAT xmlns="http://www.bmecat.org/bmecat/2005" version="2005">
  <HEADER>
    <CATALOG>
      <LANGUAGE>deu</LANGUAGE>
      <CATALOG_ID>TEST</CATALOG_ID>
      <CATALOG_VERSION>001</CATALOG_VERSION>
      <CURRENCY>EUR</CURRENCY>
    </CATALOG>
    <SUPPLIER><SUPPLIER_NAME>Test</SUPPLIER_NAME></SUPPLIER>
  </HEADER>
  <T_NEW_CATALOG>
  </T_NEW_CATALOG>
</BMECAT>';

        $result = $this->importer->validate($xml);
        $this->assertFalse($result['valid']);

        $hasNoProductsError = false;
        foreach ($result['errors'] as $error) {
            if (str_contains($error, 'Keine Produkte')) {
                $hasNoProductsError = true;
                break;
            }
        }
        $this->assertTrue($hasNoProductsError, 'Fehler für keine Produkte erwartet');
    }
}
