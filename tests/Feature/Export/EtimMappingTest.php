<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Models\Attribute;
use App\Models\Etim\EtimClass;
use App\Models\Etim\EtimClassFeature;
use App\Models\Etim\EtimClassMapping;
use App\Models\Etim\EtimFeature;
use App\Models\Etim\EtimFeatureMapping;
use App\Models\Etim\EtimGroup;
use App\Models\Etim\EtimUnit;
use App\Models\Etim\EtimValue;
use App\Models\Etim\EtimVersion;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Services\Etim\EtimMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EtimMappingTest extends TestCase
{
    use RefreshDatabase;

    private EtimMappingService $service;
    private EtimVersion $etimVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EtimMappingService();
        $this->etimVersion = EtimVersion::create([
            'version' => '9.0',
            'name' => 'ETIM 9.0',
            'is_active' => true,
        ]);
    }

    // =========================================================================
    // Klassen-Mapping
    // =========================================================================

    public function test_create_class_mapping(): void
    {
        $hierarchy = Hierarchy::create([
            'technical_name' => 'test_hierarchy',
            'name_de' => 'Test',
            'hierarchy_type' => 'master',
        ]);
        $node = HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Kabel und Leitungen',
            'is_active' => true,
            'depth' => 0,
            'sort_order' => 0,
        ]);
        $group = EtimGroup::create([
            'etim_version_id' => $this->etimVersion->id,
            'code' => 'EG000001',
            'name_de' => 'Elektroinstallation',
        ]);
        $class = EtimClass::create([
            'etim_version_id' => $this->etimVersion->id,
            'etim_group_id' => $group->id,
            'code' => 'EC000001',
            'name_de' => 'Kabel und Leitungen',
        ]);

        $mapping = $this->service->createClassMapping(
            $node->id,
            $class->id,
            $this->etimVersion->id,
        );

        $this->assertNotNull($mapping->id);
        $this->assertEquals($node->id, $mapping->hierarchy_node_id);
        $this->assertEquals($class->id, $mapping->etim_class_id);
        $this->assertEquals('manual', $mapping->mapping_source);
        $this->assertEquals(1.0, $mapping->confidence);
    }

    public function test_suggest_class_mapping_by_name(): void
    {
        $hierarchy = Hierarchy::create([
            'technical_name' => 'test_hierarchy',
            'name_de' => 'Test',
            'hierarchy_type' => 'master',
        ]);
        $node = HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Steckdosen',
            'is_active' => true,
            'depth' => 0,
            'sort_order' => 0,
        ]);

        EtimClass::create([
            'etim_version_id' => $this->etimVersion->id,
            'code' => 'EC000010',
            'name_de' => 'Steckdosen',
        ]);
        EtimClass::create([
            'etim_version_id' => $this->etimVersion->id,
            'code' => 'EC000020',
            'name_de' => 'Schalter',
        ]);
        EtimClass::create([
            'etim_version_id' => $this->etimVersion->id,
            'code' => 'EC000030',
            'name_de' => 'Steckdosenleiste',
        ]);

        $suggestions = $this->service->suggestClassMapping($node, $this->etimVersion->id);

        $this->assertNotEmpty($suggestions);
        // Exakter Name-Match sollte an erster Stelle stehen
        $this->assertEquals('EC000010', $suggestions[0]['etim_class']->code);
        $this->assertGreaterThanOrEqual(0.8, $suggestions[0]['confidence']);
    }

    // =========================================================================
    // Feature-Mapping
    // =========================================================================

    public function test_create_feature_mapping(): void
    {
        $attribute = Attribute::create([
            'technical_name' => 'bildschirmgroesse',
            'name_de' => 'Bildschirmgröße',
            'data_type' => 'Float',
        ]);
        $feature = EtimFeature::create([
            'etim_version_id' => $this->etimVersion->id,
            'code' => 'EF000001',
            'name_de' => 'Bildschirmdiagonale',
            'data_type' => 'numeric',
        ]);

        $mapping = $this->service->createFeatureMapping(
            $attribute->id,
            $feature->id,
            $this->etimVersion->id,
        );

        $this->assertNotNull($mapping->id);
        $this->assertEquals($attribute->id, $mapping->attribute_id);
        $this->assertEquals($feature->id, $mapping->etim_feature_id);
    }

    public function test_suggest_feature_mappings_with_name_similarity(): void
    {
        $class = EtimClass::create([
            'etim_version_id' => $this->etimVersion->id,
            'code' => 'EC000001',
            'name_de' => 'Testklasse',
        ]);

        $feature1 = EtimFeature::create([
            'etim_version_id' => $this->etimVersion->id,
            'code' => 'EF000001',
            'name_de' => 'Nennspannung',
            'data_type' => 'numeric',
        ]);
        $feature2 = EtimFeature::create([
            'etim_version_id' => $this->etimVersion->id,
            'code' => 'EF000002',
            'name_de' => 'Farbe',
            'data_type' => 'alphanumeric',
        ]);

        EtimClassFeature::create([
            'etim_class_id' => $class->id,
            'etim_feature_id' => $feature1->id,
            'sort_order' => 1,
        ]);
        EtimClassFeature::create([
            'etim_class_id' => $class->id,
            'etim_feature_id' => $feature2->id,
            'sort_order' => 2,
        ]);

        $attribute = Attribute::create([
            'technical_name' => 'nennspannung',
            'name_de' => 'Nennspannung',
            'data_type' => 'Float',
        ]);

        $suggestions = $this->service->suggestFeatureMappings(
            $class,
            collect([$attribute]),
            $this->etimVersion->id,
        );

        $this->assertArrayHasKey($attribute->id, $suggestions);
        $this->assertEquals('EF000001', $suggestions[$attribute->id]['etim_feature']->code);
    }

    public function test_suggest_feature_mappings_direct_match_via_source_key(): void
    {
        $class = EtimClass::create([
            'etim_version_id' => $this->etimVersion->id,
            'code' => 'EC000001',
            'name_de' => 'Testklasse',
        ]);

        $feature = EtimFeature::create([
            'etim_version_id' => $this->etimVersion->id,
            'code' => 'EF000099',
            'name_de' => 'Schutzart',
            'data_type' => 'alphanumeric',
        ]);

        EtimClassFeature::create([
            'etim_class_id' => $class->id,
            'etim_feature_id' => $feature->id,
            'sort_order' => 1,
        ]);

        $attribute = Attribute::create([
            'technical_name' => 'ip_schutzart',
            'name_de' => 'IP Schutzart',
            'data_type' => 'String',
            'source_system' => 'etim',
            'source_attribute_key' => 'EF000099',
        ]);

        $suggestions = $this->service->suggestFeatureMappings(
            $class,
            collect([$attribute]),
            $this->etimVersion->id,
        );

        $this->assertArrayHasKey($attribute->id, $suggestions);
        $this->assertEquals(1.0, $suggestions[$attribute->id]['confidence']);
        $this->assertEquals('EF000099', $suggestions[$attribute->id]['etim_feature']->code);
    }

    // =========================================================================
    // Resolve für Export
    // =========================================================================

    public function test_resolve_class_mapping_for_product(): void
    {
        $hierarchy = Hierarchy::create([
            'technical_name' => 'test_hierarchy',
            'name_de' => 'Test',
            'hierarchy_type' => 'master',
        ]);
        $node = HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Testkategorie',
            'is_active' => true,
            'depth' => 0,
            'sort_order' => 0,
        ]);
        $class = EtimClass::create([
            'etim_version_id' => $this->etimVersion->id,
            'code' => 'EC000042',
            'name_de' => 'Testklasse',
        ]);

        EtimClassMapping::create([
            'hierarchy_node_id' => $node->id,
            'etim_class_id' => $class->id,
            'etim_version_id' => $this->etimVersion->id,
            'confidence' => 1.0,
            'mapping_source' => 'manual',
        ]);

        $resolved = $this->service->resolveClassMappingForProduct($node->id, $this->etimVersion->id);

        $this->assertNotNull($resolved);
        $this->assertEquals('EC000042', $resolved->etimClass->code);
    }

    public function test_resolve_class_mapping_inherits_from_parent(): void
    {
        $hierarchy = Hierarchy::create([
            'technical_name' => 'test_hierarchy',
            'name_de' => 'Test',
            'hierarchy_type' => 'master',
        ]);
        $parentNode = HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Elternkategorie',
            'is_active' => true,
            'depth' => 0,
            'sort_order' => 0,
        ]);
        $childNode = HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'parent_node_id' => $parentNode->id,
            'name_de' => 'Kindkategorie',
            'is_active' => true,
            'depth' => 1,
            'sort_order' => 0,
        ]);
        $class = EtimClass::create([
            'etim_version_id' => $this->etimVersion->id,
            'code' => 'EC000055',
            'name_de' => 'Elternklasse',
        ]);

        // Mapping nur am Elternknoten
        EtimClassMapping::create([
            'hierarchy_node_id' => $parentNode->id,
            'etim_class_id' => $class->id,
            'etim_version_id' => $this->etimVersion->id,
            'confidence' => 1.0,
            'mapping_source' => 'manual',
        ]);

        $resolved = $this->service->resolveClassMappingForProduct($childNode->id, $this->etimVersion->id);

        $this->assertNotNull($resolved);
        $this->assertEquals('EC000055', $resolved->etimClass->code);
    }

    public function test_auto_map_from_source_system(): void
    {
        $feature = EtimFeature::create([
            'etim_version_id' => $this->etimVersion->id,
            'code' => 'EF001234',
            'name_de' => 'Nennstrom',
            'data_type' => 'numeric',
        ]);

        Attribute::create([
            'technical_name' => 'nennstrom',
            'name_de' => 'Nennstrom',
            'data_type' => 'Float',
            'source_system' => 'etim',
            'source_attribute_key' => 'EF001234',
        ]);

        $stats = $this->service->autoMapFromSourceSystem($this->etimVersion->id);

        $this->assertEquals(1, $stats['feature_mappings']);
    }
}
