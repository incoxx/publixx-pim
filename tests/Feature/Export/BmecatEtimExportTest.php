<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Models\Attribute;
use App\Models\Etim\EtimClass;
use App\Models\Etim\EtimClassMapping;
use App\Models\Etim\EtimVersion;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Services\Export\BmecatFormatExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BmecatEtimExportTest extends TestCase
{
    use RefreshDatabase;

    private BmecatFormatExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = new BmecatFormatExporter();
    }

    public function test_export_includes_etim_classification_reference(): void
    {
        // ETIM-Stammdaten erstellen
        $etimVersion = EtimVersion::create([
            'version' => '9.0',
            'name' => 'ETIM 9.0',
            'is_active' => true,
        ]);

        $etimClass = EtimClass::create([
            'etim_version_id' => $etimVersion->id,
            'code' => 'EC001234',
            'name_de' => 'Steckdosen',
        ]);

        // Hierarchie + Node erstellen
        $hierarchy = Hierarchy::create([
            'technical_name' => 'export_test',
            'name_de' => 'Export Test',
            'hierarchy_type' => 'master',
        ]);

        $node = HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Steckdosen',
            'is_active' => true,
            'depth' => 0,
            'sort_order' => 0,
        ]);

        // ETIM-Klassen-Mapping
        EtimClassMapping::create([
            'hierarchy_node_id' => $node->id,
            'etim_class_id' => $etimClass->id,
            'etim_version_id' => $etimVersion->id,
            'confidence' => 1.0,
            'mapping_source' => 'manual',
        ]);

        // Produkttyp + Produkt mit Attributen erstellen
        $productType = ProductType::create([
            'technical_name' => 'test_product',
            'name_de' => 'Testprodukt',
            'is_active' => true,
        ]);

        $attribute = Attribute::create([
            'technical_name' => 'nennspannung',
            'name_de' => 'Nennspannung',
            'data_type' => 'Float',
        ]);

        $product = Product::create([
            'sku' => 'TEST-001',
            'name' => 'Test-Steckdose',
            'status' => 'active',
            'product_type_ref' => 'product',
            'product_type_id' => $productType->id,
            'master_hierarchy_node_id' => $node->id,
        ]);

        ProductAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_number' => 230.0,
            'is_inherited' => false,
            'multiplied_index' => 0,
        ]);

        // Export mit ETIM-Version
        $this->exporter->setVersion('2005');
        $this->exporter->setEtimVersionId($etimVersion->id);

        $xml = $this->exporter->export();

        // Prüfen dass ETIM-Referenz vorhanden ist
        $this->assertStringContainsString('REFERENCE_FEATURE_SYSTEM_NAME', $xml);
        $this->assertStringContainsString('ETIM-9.0', $xml);
        $this->assertStringContainsString('REFERENCE_FEATURE_GROUP_ID', $xml);
        $this->assertStringContainsString('EC001234', $xml);

        // Prüfen dass Feature auch vorhanden ist
        $this->assertStringContainsString('<FNAME>Nennspannung</FNAME>', $xml);
        $this->assertStringContainsString('<FVALUE>230</FVALUE>', $xml);
    }

    public function test_export_without_etim_has_no_classification_reference(): void
    {
        $productType = ProductType::create([
            'technical_name' => 'test_product',
            'name_de' => 'Testprodukt',
            'is_active' => true,
        ]);

        $attribute = Attribute::create([
            'technical_name' => 'farbe',
            'name_de' => 'Farbe',
            'data_type' => 'String',
        ]);

        $product = Product::create([
            'sku' => 'TEST-002',
            'name' => 'Test ohne ETIM',
            'status' => 'active',
            'product_type_ref' => 'product',
            'product_type_id' => $productType->id,
        ]);

        ProductAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'rot',
            'is_inherited' => false,
            'multiplied_index' => 0,
        ]);

        // Export OHNE ETIM-Version
        $this->exporter->setVersion('2005');

        $xml = $this->exporter->export();

        // Keine ETIM-Referenz
        $this->assertStringNotContainsString('REFERENCE_FEATURE_SYSTEM_NAME', $xml);
        $this->assertStringNotContainsString('REFERENCE_FEATURE_GROUP_ID', $xml);

        // Features sind aber vorhanden
        $this->assertStringContainsString('<FNAME>Farbe</FNAME>', $xml);
        $this->assertStringContainsString('<FVALUE>rot</FVALUE>', $xml);
    }
}
