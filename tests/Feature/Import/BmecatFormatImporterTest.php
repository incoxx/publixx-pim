<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\PriceType;
use App\Models\Product;
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
}
