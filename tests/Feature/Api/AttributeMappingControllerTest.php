<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeMapping;
use App\Models\AttributeMappingRule;
use App\Models\Hierarchy;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Export\AttributeMappingSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AttributeMappingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->user = User::factory()->create();
        $role = Role::findOrCreate('Admin', 'sanctum');
        $this->user->assignRole($role);
        $this->actingAs($this->user);
    }

    // ─── Index ────────────────────────────────────────────────

    public function test_index_gibt_paginierte_mappings_zurueck(): void
    {
        AttributeMapping::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/attribute-mappings');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filtert_nach_quell_hierarchie(): void
    {
        $sourceHierarchy = Hierarchy::factory()->create();
        AttributeMapping::factory()->create(['source_hierarchy_id' => $sourceHierarchy->id]);
        AttributeMapping::factory()->create(); // anderes Mapping ohne diesen Filter

        $response = $this->getJson("/api/v1/attribute-mappings?source_hierarchy_id={$sourceHierarchy->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filtert_nach_ziel_hierarchie(): void
    {
        $targetHierarchy = Hierarchy::factory()->create();
        AttributeMapping::factory()->create(['target_hierarchy_id' => $targetHierarchy->id]);
        AttributeMapping::factory()->create();

        $response = $this->getJson("/api/v1/attribute-mappings?target_hierarchy_id={$targetHierarchy->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ─── Store ────────────────────────────────────────────────

    public function test_store_erstellt_neues_mapping(): void
    {
        $sourceHierarchy = Hierarchy::factory()->create();
        $targetHierarchy = Hierarchy::factory()->create();
        $sourceAttr = Attribute::factory()->create();
        $targetAttr = Attribute::factory()->create();

        $response = $this->postJson('/api/v1/attribute-mappings', [
            'source_hierarchy_id' => $sourceHierarchy->id,
            'source_attribute_id' => $sourceAttr->id,
            'target_hierarchy_id' => $targetHierarchy->id,
            'target_attribute_id' => $targetAttr->id,
            'transform_type' => 'direct',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.transform_type', 'direct');

        $this->assertDatabaseHas('attribute_mappings', [
            'source_hierarchy_id' => $sourceHierarchy->id,
            'target_attribute_id' => $targetAttr->id,
        ]);
    }

    public function test_store_schlaegt_fehl_bei_ungueltigem_transform_type(): void
    {
        $sourceHierarchy = Hierarchy::factory()->create();
        $targetHierarchy = Hierarchy::factory()->create();
        $sourceAttr = Attribute::factory()->create();
        $targetAttr = Attribute::factory()->create();

        $response = $this->postJson('/api/v1/attribute-mappings', [
            'source_hierarchy_id' => $sourceHierarchy->id,
            'source_attribute_id' => $sourceAttr->id,
            'target_hierarchy_id' => $targetHierarchy->id,
            'target_attribute_id' => $targetAttr->id,
            'transform_type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['transform_type']);
    }

    // ─── Show ─────────────────────────────────────────────────

    public function test_show_gibt_einzelnes_mapping_zurueck(): void
    {
        $mapping = AttributeMapping::factory()->create();

        $response = $this->getJson("/api/v1/attribute-mappings/{$mapping->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $mapping->id);
    }

    // ─── Update ───────────────────────────────────────────────

    public function test_update_aktualisiert_mapping(): void
    {
        $mapping = AttributeMapping::factory()->create(['transform_type' => 'direct']);

        $response = $this->putJson("/api/v1/attribute-mappings/{$mapping->id}", [
            'transform_type' => 'unit_convert',
            'transform_config' => ['factor' => 0.1, 'from_unit' => 'mm', 'to_unit' => 'cm'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.transform_type', 'unit_convert');
    }

    // ─── Destroy ──────────────────────────────────────────────

    public function test_destroy_loescht_mapping(): void
    {
        $mapping = AttributeMapping::factory()->create();

        $response = $this->deleteJson("/api/v1/attribute-mappings/{$mapping->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('attribute_mappings', ['id' => $mapping->id]);
    }

    // ─── bulkStore ────────────────────────────────────────────

    public function test_bulk_store_erstellt_mehrere_mappings(): void
    {
        $src1 = Hierarchy::factory()->create();
        $tgt1 = Hierarchy::factory()->create();
        $a1 = Attribute::factory()->create();
        $a2 = Attribute::factory()->create();
        $a3 = Attribute::factory()->create();
        $a4 = Attribute::factory()->create();

        $response = $this->postJson('/api/v1/attribute-mappings/bulk', [
            'mappings' => [
                [
                    'source_hierarchy_id' => $src1->id,
                    'source_attribute_id' => $a1->id,
                    'target_hierarchy_id' => $tgt1->id,
                    'target_attribute_id' => $a2->id,
                    'transform_type' => 'direct',
                ],
                [
                    'source_hierarchy_id' => $src1->id,
                    'source_attribute_id' => $a3->id,
                    'target_hierarchy_id' => $tgt1->id,
                    'target_attribute_id' => $a4->id,
                    'transform_type' => 'direct',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('created', 2)
            ->assertJsonPath('updated', 0);
    }

    public function test_bulk_store_aktualisiert_vorhandene_mappings(): void
    {
        $srcH = Hierarchy::factory()->create();
        $tgtH = Hierarchy::factory()->create();
        $a1 = Attribute::factory()->create();
        $a2 = Attribute::factory()->create();

        // Mapping schon vorhanden
        AttributeMapping::factory()->create([
            'source_hierarchy_id' => $srcH->id,
            'source_attribute_id' => $a1->id,
            'target_hierarchy_id' => $tgtH->id,
            'target_attribute_id' => $a2->id,
            'transform_type' => 'direct',
        ]);

        $response = $this->postJson('/api/v1/attribute-mappings/bulk', [
            'mappings' => [
                [
                    'source_hierarchy_id' => $srcH->id,
                    'source_attribute_id' => $a1->id,
                    'target_hierarchy_id' => $tgtH->id,
                    'target_attribute_id' => $a2->id,
                    'transform_type' => 'unit_convert',
                    'transform_config' => ['factor' => 10.0, 'from_unit' => 'cm', 'to_unit' => 'mm'],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('updated', 1);
    }

    // ─── Rules ────────────────────────────────────────────────

    public function test_rules_gibt_alle_regeln_zurueck(): void
    {
        AttributeMappingRule::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/attribute-mapping-rules');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_rule_erstellt_neue_regel(): void
    {
        $srcH = Hierarchy::factory()->create();
        $tgtH = Hierarchy::factory()->create();
        $condAttr = Attribute::factory()->create();
        $tgtAttr = Attribute::factory()->create();

        $response = $this->postJson('/api/v1/attribute-mapping-rules', [
            'source_hierarchy_id' => $srcH->id,
            'target_hierarchy_id' => $tgtH->id,
            'name' => 'Test-Regel',
            'condition_attribute_id' => $condAttr->id,
            'condition_operator' => '=',
            'condition_value' => 'aktiv',
            'actions' => [
                [
                    'target_attribute_id' => $tgtAttr->id,
                    'value' => 'ja',
                    'value_type' => 'static',
                ],
            ],
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Test-Regel');

        $this->assertDatabaseHas('attribute_mapping_rules', ['name' => 'Test-Regel']);
    }

    public function test_update_rule_aktualisiert_regel(): void
    {
        $rule = AttributeMappingRule::factory()->create(['is_active' => true]);

        $response = $this->putJson("/api/v1/attribute-mapping-rules/{$rule->id}", [
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_destroy_rule_loescht_regel(): void
    {
        $rule = AttributeMappingRule::factory()->create();

        $response = $this->deleteJson("/api/v1/attribute-mapping-rules/{$rule->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('attribute_mapping_rules', ['id' => $rule->id]);
    }

    // ─── syncProduct ──────────────────────────────────────────

    public function test_sync_product_gibt_statistik_zurueck(): void
    {
        $product = Product::factory()->create();

        // SyncService mocken, da keine echten Mappings benötigt werden
        $mockService = $this->mock(AttributeMappingSyncService::class);
        $mockService->shouldReceive('syncProduct')
            ->once()
            ->andReturn(['created' => 2, 'updated' => 1, 'skipped' => 0]);

        $response = $this->postJson("/api/v1/attribute-mappings/sync/product/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('stats.created', 2)
            ->assertJsonPath('stats.updated', 1);
    }

    // ─── syncBatch ────────────────────────────────────────────

    public function test_sync_batch_dispatcht_job(): void
    {
        Queue::fake();

        $srcH = Hierarchy::factory()->create();
        $tgtH = Hierarchy::factory()->create();

        $response = $this->postJson('/api/v1/attribute-mappings/sync/batch', [
            'source_hierarchy_id' => $srcH->id,
            'target_hierarchy_id' => $tgtH->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Sync-Job gestartet');

        Queue::assertPushed(\App\Jobs\SyncAttributeMappings::class);
    }
}
