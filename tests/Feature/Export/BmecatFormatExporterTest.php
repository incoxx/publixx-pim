<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Media;
use App\Models\MediaUsageType;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use App\Models\ProductRelation;
use App\Models\ProductRelationType;
use App\Models\ProductType;
use App\Models\Unit;
use App\Services\Export\BmecatFormatExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-Tests für den BMEcat-Export (Versionen 1.2 und 2005).
 */
class BmecatFormatExporterTest extends TestCase
{
    use RefreshDatabase;

    protected BmecatFormatExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = new BmecatFormatExporter();
    }

    /** XML parsen und Validität sicherstellen. */
    private function parse(string $xml): \DOMDocument
    {
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'Export muss valides XML liefern');

        return $dom;
    }

    // ─── Grundstruktur ──────────────────────────────────────────

    public function test_export_erzeugt_valides_bmecat_2005_xml(): void
    {
        Product::factory()->active()->create(['sku' => 'BM-001']);

        $dom = $this->parse($this->exporter->export());

        $root = $dom->documentElement;
        $this->assertSame('BMECAT', $root->nodeName);
        $this->assertSame('2005', $root->getAttribute('version'));
        $this->assertSame('http://www.bmecat.org/bmecat/2005', $root->getAttribute('xmlns'));

        // Header mit Katalog-Stammdaten
        $this->assertSame(1, $dom->getElementsByTagName('HEADER')->length);
        $this->assertSame('deu', $dom->getElementsByTagName('LANGUAGE')->item(0)->textContent);
        $this->assertSame('EUR', $dom->getElementsByTagName('CURRENCY')->item(0)->textContent);

        // T_NEW_CATALOG mit einem PRODUCT
        $this->assertSame(1, $dom->getElementsByTagName('T_NEW_CATALOG')->length);
        $this->assertSame(1, $dom->getElementsByTagName('PRODUCT')->length);
        $this->assertSame('BM-001', $dom->getElementsByTagName('SUPPLIER_PID')->item(0)->textContent);
    }

    public function test_version_1_2_nutzt_article_elemente(): void
    {
        Product::factory()->active()->create(['sku' => 'BM-V12']);

        $this->exporter->setVersion('1.2');
        $dom = $this->parse($this->exporter->export());

        $this->assertSame('1.2', $dom->documentElement->getAttribute('version'));
        $this->assertSame('', $dom->documentElement->getAttribute('xmlns'));
        $this->assertSame(1, $dom->getElementsByTagName('ARTICLE')->length);
        $this->assertSame(0, $dom->getElementsByTagName('PRODUCT')->length);
        $this->assertSame('BM-V12', $dom->getElementsByTagName('SUPPLIER_AID')->item(0)->textContent);
    }

    public function test_ungueltige_version_faellt_auf_2005_zurueck(): void
    {
        Product::factory()->active()->create();

        $this->exporter->setVersion('3.0');
        $dom = $this->parse($this->exporter->export());

        $this->assertSame('2005', $dom->documentElement->getAttribute('version'));
    }

    // ─── Produktdaten ───────────────────────────────────────────

    public function test_attribute_werden_als_features_exportiert(): void
    {
        $product = Product::factory()->active()->create();
        $attribute = Attribute::factory()->create([
            'technical_name' => 'gewicht-kg',
            'name_de' => 'Gewicht',
        ]);
        $unit = Unit::factory()->create(['technical_name' => 'kilogramm', 'abbreviation' => 'kg']);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => null,
            'value_number' => 1.8,
            'unit_id' => $unit->id,
        ]);

        $dom = $this->parse($this->exporter->export());

        $this->assertSame(1, $dom->getElementsByTagName('PRODUCT_FEATURES')->length);
        $this->assertSame('Gewicht', $dom->getElementsByTagName('FNAME')->item(0)->textContent);
        $this->assertSame('1.8', $dom->getElementsByTagName('FVALUE')->item(0)->textContent);
        // Unit hat kein symbol → Fallback auf technical_name
        $this->assertSame('kilogramm', $dom->getElementsByTagName('FUNIT')->item(0)->textContent);
        $this->assertSame('1', $dom->getElementsByTagName('FORDER')->item(0)->textContent);
    }

    public function test_detail_attribute_werden_in_product_details_gemappt(): void
    {
        $product = Product::factory()->active()->create(['name' => 'Akkubohrer', 'ean' => '4012345678901']);

        $descAttr = Attribute::factory()->create(['technical_name' => 'description_long']);
        $mfrAttr = Attribute::factory()->create(['technical_name' => 'manufacturer_name']);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $descAttr->id,
            'value_string' => 'Sehr ausführliche Beschreibung',
        ]);
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $mfrAttr->id,
            'value_string' => 'ProTools GmbH',
        ]);

        $dom = $this->parse($this->exporter->export());

        $this->assertSame('Akkubohrer', $dom->getElementsByTagName('DESCRIPTION_SHORT')->item(0)->textContent);
        $this->assertSame('Sehr ausführliche Beschreibung', $dom->getElementsByTagName('DESCRIPTION_LONG')->item(0)->textContent);
        $this->assertSame('ProTools GmbH', $dom->getElementsByTagName('MANUFACTURER_NAME')->item(0)->textContent);
        $this->assertSame('4012345678901', $dom->getElementsByTagName('EAN')->item(0)->textContent);

        // Detail-Attribute dürfen NICHT zusätzlich als FEATURE erscheinen
        $this->assertSame(0, $dom->getElementsByTagName('FEATURE')->length);
    }

    public function test_preise_werden_mit_betrag_und_waehrung_exportiert(): void
    {
        $product = Product::factory()->active()->create();
        $priceType = PriceType::factory()->create(['technical_name' => 'list_price']);

        ProductPrice::factory()->create([
            'product_id' => $product->id,
            'price_type_id' => $priceType->id,
            'amount' => 189.9,
            'currency' => 'EUR',
        ]);

        $dom = $this->parse($this->exporter->export());

        $priceEl = $dom->getElementsByTagName('PRODUCT_PRICE')->item(0);
        $this->assertNotNull($priceEl);
        $this->assertSame('list_price', $priceEl->getAttribute('price_type'));
        $this->assertSame('189.90', $dom->getElementsByTagName('PRICE_AMOUNT')->item(0)->textContent);
        $this->assertSame('EUR', $dom->getElementsByTagName('PRICE_CURRENCY')->item(0)->textContent);
        $this->assertSame('0.19', $dom->getElementsByTagName('TAX')->item(0)->textContent);
    }

    public function test_medien_werden_als_mime_info_exportiert(): void
    {
        $product = Product::factory()->active()->create();
        $media = Media::factory()->create(['file_name' => 'produktbild.jpg', 'mime_type' => 'image/jpeg']);
        // 'teaser' wird ggf. bereits per Migration angelegt → wiederverwenden
        $usageType = MediaUsageType::firstOrCreate(
            ['technical_name' => 'teaser'],
            ['name_de' => 'Teaser', 'name_en' => 'Teaser', 'sort_order' => 0],
        );

        ProductMediaAssignment::factory()->create([
            'product_id' => $product->id,
            'media_id' => $media->id,
            'usage_type_id' => $usageType->id,
        ]);

        $dom = $this->parse($this->exporter->export());

        $this->assertSame(1, $dom->getElementsByTagName('MIME_INFO')->length);
        $this->assertSame('image/jpeg', $dom->getElementsByTagName('MIME_TYPE')->item(0)->textContent);
        $this->assertSame('produktbild.jpg', $dom->getElementsByTagName('MIME_SOURCE')->item(0)->textContent);
        // usage_type 'teaser' wird auf MIME_PURPOSE 'thumbnail' gemappt
        $this->assertSame('thumbnail', $dom->getElementsByTagName('MIME_PURPOSE')->item(0)->textContent);
    }

    public function test_beziehungen_werden_als_product_reference_exportiert(): void
    {
        $source = Product::factory()->active()->create(['sku' => 'HAUPT-001']);
        $target = Product::factory()->active()->create(['sku' => 'ZUB-001']);
        $relationType = ProductRelationType::factory()->create(['technical_name' => 'accessories']);

        ProductRelation::factory()->create([
            'source_product_id' => $source->id,
            'target_product_id' => $target->id,
            'relation_type_id' => $relationType->id,
        ]);

        $dom = $this->parse($this->exporter->export());

        $refs = $dom->getElementsByTagName('PRODUCT_REFERENCE');
        $this->assertSame(1, $refs->length);
        $this->assertSame('accessories', $refs->item(0)->getAttribute('type'));
        $this->assertSame('ZUB-001', $refs->item(0)->getElementsByTagName('PROD_ID_TO')->item(0)->textContent);
    }

    // ─── Hierarchie ─────────────────────────────────────────────

    public function test_hierarchie_wird_als_catalog_group_system_exportiert(): void
    {
        $hierarchy = Hierarchy::factory()->create(['technical_name' => 'master', 'name_de' => 'Master-Hierarchie']);
        $root = HierarchyNode::factory()->create([
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Wurzel',
            'depth' => 0,
        ]);
        $leaf = HierarchyNode::factory()->create([
            'hierarchy_id' => $hierarchy->id,
            'parent_node_id' => $root->id,
            'name_de' => 'Werkzeuge',
            'depth' => 1,
        ]);

        $product = Product::factory()->active()->create([
            'sku' => 'HIER-001',
            'master_hierarchy_node_id' => $leaf->id,
        ]);

        $this->exporter->setHierarchyId($hierarchy->id);
        $dom = $this->parse($this->exporter->export());

        // CATALOG_GROUP_SYSTEM mit beiden Knoten
        $this->assertSame(1, $dom->getElementsByTagName('CATALOG_GROUP_SYSTEM')->length);
        $this->assertSame('master', $dom->getElementsByTagName('GROUP_SYSTEM_ID')->item(0)->textContent);

        $structures = $dom->getElementsByTagName('CATALOG_STRUCTURE');
        $this->assertSame(2, $structures->length);
        $this->assertSame('root', $structures->item(0)->getAttribute('type'));
        $this->assertSame('leaf', $structures->item(1)->getAttribute('type'));
        $this->assertSame('Werkzeuge', $structures->item(1)->getElementsByTagName('GROUP_NAME')->item(0)->textContent);
        $this->assertSame($root->id, $structures->item(1)->getElementsByTagName('PARENT_ID')->item(0)->textContent);

        // Produkt-Zuordnung über master_hierarchy_node_id (Fallback)
        $maps = $dom->getElementsByTagName('PRODUCT_TO_CATALOGGROUP_MAP');
        $this->assertSame(1, $maps->length);
        $this->assertSame('HIER-001', $maps->item(0)->getElementsByTagName('PROD_ID')->item(0)->textContent);
        $this->assertSame($leaf->id, $maps->item(0)->getElementsByTagName('CATALOG_GROUP_ID')->item(0)->textContent);
    }

    // ─── Filter ─────────────────────────────────────────────────

    public function test_produkttyp_filter_schraenkt_produkte_ein(): void
    {
        $typeA = ProductType::factory()->create();
        $typeB = ProductType::factory()->create();

        Product::factory()->active()->create(['sku' => 'TYP-A', 'product_type_id' => $typeA->id]);
        Product::factory()->active()->create(['sku' => 'TYP-B', 'product_type_id' => $typeB->id]);

        $this->exporter->setProductTypeIds([$typeA->id]);
        $output = $this->exporter->export();
        $dom = $this->parse($output);

        $this->assertSame(1, $dom->getElementsByTagName('PRODUCT')->length);
        $this->assertStringContainsString('TYP-A', $output);
        $this->assertStringNotContainsString('TYP-B', $output);
    }

    public function test_inaktive_produkte_und_varianten_werden_ausgeschlossen(): void
    {
        $parent = Product::factory()->active()->create(['sku' => 'AKTIV-001']);
        Product::factory()->create(['sku' => 'INAKTIV-001', 'status' => 'inactive']);
        Product::factory()->variant()->create([
            'sku' => 'VARIANTE-001',
            'status' => 'active',
            'parent_product_id' => $parent->id,
        ]);

        $output = $this->exporter->export();
        $dom = $this->parse($output);

        $this->assertSame(1, $dom->getElementsByTagName('PRODUCT')->length);
        $this->assertStringContainsString('AKTIV-001', $output);
        $this->assertStringNotContainsString('INAKTIV-001', $output);
        $this->assertStringNotContainsString('VARIANTE-001', $output);
    }

    public function test_attribut_filter_schraenkt_features_ein(): void
    {
        $product = Product::factory()->active()->create();
        $attrA = Attribute::factory()->create(['technical_name' => 'farbe', 'name_de' => 'Farbe']);
        $attrB = Attribute::factory()->create(['technical_name' => 'material', 'name_de' => 'Material']);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attrA->id,
            'value_string' => 'Rot',
        ]);
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attrB->id,
            'value_string' => 'Stahl',
        ]);

        $this->exporter->setAttributeIds([$attrA->id]);
        $dom = $this->parse($this->exporter->export());

        $this->assertSame(1, $dom->getElementsByTagName('FEATURE')->length);
        $this->assertSame('Farbe', $dom->getElementsByTagName('FNAME')->item(0)->textContent);
    }

    // ─── Leerer Export & Escaping ───────────────────────────────

    public function test_leerer_export_ist_valides_xml_ohne_produkte(): void
    {
        $dom = $this->parse($this->exporter->export());

        $this->assertSame('BMECAT', $dom->documentElement->nodeName);
        $this->assertSame(0, $dom->getElementsByTagName('PRODUCT')->length);
        $this->assertSame(1, $dom->getElementsByTagName('T_NEW_CATALOG')->length);
    }

    public function test_sonderzeichen_werden_korrekt_escaped(): void
    {
        $product = Product::factory()->active()->create([
            'sku' => 'SPECIAL-001',
            'name' => 'Bohrer & Schrauber <5mm> "Größe" ä',
        ]);
        $attribute = Attribute::factory()->create(['technical_name' => 'hinweis', 'name_de' => 'Hinweis']);
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Achtung: <nicht> für Kinder & Tiere',
        ]);

        $output = $this->exporter->export();

        // Rohtext muss escaped sein
        $this->assertStringContainsString('Bohrer &amp; Schrauber &lt;5mm&gt;', $output);

        // Round-trip über DOM liefert die Originalwerte
        $dom = $this->parse($output);
        $this->assertSame(
            'Bohrer & Schrauber <5mm> "Größe" ä',
            $dom->getElementsByTagName('DESCRIPTION_SHORT')->item(0)->textContent
        );
        $this->assertSame(
            'Achtung: <nicht> für Kinder & Tiere',
            $dom->getElementsByTagName('FVALUE')->item(0)->textContent
        );
    }
}
