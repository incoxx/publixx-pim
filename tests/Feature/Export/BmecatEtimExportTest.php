<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Models\Attribute;
use App\Models\Etim\EtimClass;
use App\Models\Etim\EtimClassMapping;
use App\Models\Etim\EtimFeature;
use App\Models\Etim\EtimFeatureMapping;
use App\Models\Etim\EtimUnit;
use App\Models\Etim\EtimUnitMapping;
use App\Models\Etim\EtimValue;
use App\Models\Etim\EtimValueMapping;
use App\Models\Etim\EtimVersion;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Models\Unit;
use App\Models\ValueList;
use App\Models\ValueListEntry;
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

    public function test_export_uses_etim_feature_value_unit_codes_via_mapping_resolver(): void
    {
        // ETIM-Stammdaten
        $etimVersion = EtimVersion::create([
            'version' => '9.0',
            'name' => 'ETIM 9.0',
            'is_active' => true,
        ]);
        $etimClass = EtimClass::create([
            'etim_version_id' => $etimVersion->id,
            'code' => 'EC002000',
            'name_de' => 'Schalter',
        ]);
        $etimFeature = EtimFeature::create([
            'etim_version_id' => $etimVersion->id,
            'code' => 'EF000007',
            'name_de' => 'Farbe',
            'data_type' => 'alphanumeric',
        ]);
        $etimValue = EtimValue::create([
            'etim_version_id' => $etimVersion->id,
            'code' => 'EV000028',
            'name_de' => 'Weiß',
        ]);
        $etimUnit = EtimUnit::create([
            'etim_version_id' => $etimVersion->id,
            'code' => 'EU000001',
            'name_de' => 'Millimeter',
            'abbreviation' => 'mm',
        ]);

        // PIM-Daten
        $hierarchy = Hierarchy::create([
            'technical_name' => 'test_h',
            'name_de' => 'Test',
            'hierarchy_type' => 'master',
        ]);
        $node = HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Schalter',
            'is_active' => true,
            'depth' => 0,
            'sort_order' => 0,
        ]);
        $productType = ProductType::create([
            'technical_name' => 'test_product',
            'name_de' => 'Testprodukt',
            'is_active' => true,
        ]);

        // Attribut mit ValueList (Selection)
        $valueList = ValueList::create([
            'technical_name' => 'farben',
            'name_de' => 'Farben',
        ]);
        $valueListEntry = ValueListEntry::create([
            'value_list_id' => $valueList->id,
            'technical_name' => 'weiss',
            'display_value_de' => 'Weiß',
            'is_active' => true,
        ]);
        $colorAttr = Attribute::create([
            'technical_name' => 'farbe',
            'name_de' => 'Farbe',
            'data_type' => 'Selection',
            'value_list_id' => $valueList->id,
        ]);

        // Attribut mit Unit
        $pimUnit = Unit::create([
            'technical_name' => 'millimeter',
            'name_de' => 'Millimeter',
            'symbol' => 'mm',
        ]);
        $widthAttr = Attribute::create([
            'technical_name' => 'breite',
            'name_de' => 'Breite',
            'data_type' => 'Float',
            'default_unit_id' => $pimUnit->id,
        ]);

        // ETIM-Mappings erstellen
        EtimClassMapping::create([
            'hierarchy_node_id' => $node->id,
            'etim_class_id' => $etimClass->id,
            'etim_version_id' => $etimVersion->id,
            'confidence' => 1.0,
            'mapping_source' => 'manual',
        ]);
        EtimFeatureMapping::create([
            'attribute_id' => $colorAttr->id,
            'etim_feature_id' => $etimFeature->id,
            'etim_version_id' => $etimVersion->id,
            'confidence' => 1.0,
            'mapping_source' => 'manual',
        ]);
        EtimValueMapping::create([
            'value_list_entry_id' => $valueListEntry->id,
            'etim_value_id' => $etimValue->id,
            'etim_version_id' => $etimVersion->id,
            'confidence' => 1.0,
            'mapping_source' => 'manual',
        ]);
        EtimUnitMapping::create([
            'unit_id' => $pimUnit->id,
            'etim_unit_id' => $etimUnit->id,
            'etim_version_id' => $etimVersion->id,
            'confidence' => 1.0,
            'mapping_source' => 'manual',
        ]);

        // Produkt mit Attributwerten
        $product = Product::create([
            'sku' => 'TEST-ETIM-003',
            'name' => 'Schalter weiß',
            'status' => 'active',
            'product_type_ref' => 'product',
            'product_type_id' => $productType->id,
            'master_hierarchy_node_id' => $node->id,
        ]);
        ProductAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $colorAttr->id,
            'value_selection_id' => $valueListEntry->id,
            'is_inherited' => false,
            'multiplied_index' => 0,
        ]);
        ProductAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $widthAttr->id,
            'value_number' => 80.5,
            'unit_id' => $pimUnit->id,
            'is_inherited' => false,
            'multiplied_index' => 0,
        ]);

        // Export mit ETIM
        $this->exporter->setVersion('2005');
        $this->exporter->setEtimVersionId($etimVersion->id);
        $xml = $this->exporter->export();

        // ETIM-Klassifikation
        $this->assertStringContainsString('ETIM-9.0', $xml);
        $this->assertStringContainsString('EC002000', $xml);

        // Feature-Codes statt Rohwerte
        $this->assertStringContainsString('<FNAME>EF000007</FNAME>', $xml, 'ETIM feature code als FNAME');
        $this->assertStringContainsString('<FVALUE>EV000028</FVALUE>', $xml, 'ETIM value code als FVALUE');
        $this->assertStringContainsString('<FUNIT>EU000001</FUNIT>', $xml, 'ETIM unit code als FUNIT');
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
