<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Role;
use App\Models\User;
use App\Models\VirtualProductInheritanceRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests für Attribut-Vererbung virtueller Produkte (Phase 1).
 *
 * Routen:
 *   GET  /api/v1/products/{product}/virtual-inheritance-rules
 *   PUT  /api/v1/products/{product}/virtual-inheritance-rules
 *   POST /api/v1/products/{product}/virtual-definition/sync
 */
class VirtualProductInheritanceTest extends TestCase
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

    private function virtualProduct(): Product
    {
        return Product::factory()->create(['product_type_ref' => 'virtual']);
    }

    private function defineManualCluster(Product $virtual, array $memberIds): void
    {
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-definition", [
            'source_type' => 'manual',
            'manual_product_ids' => $memberIds,
        ])->assertOk();
    }

    public function test_regeln_koennen_gespeichert_und_gelesen_werden(): void
    {
        $virtual = $this->virtualProduct();
        $attribute = Attribute::factory()->create();

        $this->putJson("/api/v1/products/{$virtual->id}/virtual-inheritance-rules", [
            'rules' => [
                ['attribute_id' => $attribute->id, 'conflict_mode' => 'keep_local'],
            ],
        ])->assertOk()->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/products/{$virtual->id}/virtual-inheritance-rules")
            ->assertOk()
            ->assertJsonPath('data.0.attribute_id', $attribute->id)
            ->assertJsonPath('data.0.conflict_mode', 'keep_local');
    }

    public function test_regeln_ersetzen_entfernt_nicht_mehr_enthaltene_attribute(): void
    {
        $virtual = $this->virtualProduct();
        $a = Attribute::factory()->create();
        $b = Attribute::factory()->create();

        VirtualProductInheritanceRule::create([
            'virtual_product_id' => $virtual->id, 'attribute_id' => $a->id, 'conflict_mode' => 'keep_local',
        ]);

        $this->putJson("/api/v1/products/{$virtual->id}/virtual-inheritance-rules", [
            'rules' => [['attribute_id' => $b->id, 'conflict_mode' => 'force_override']],
        ])->assertOk()->assertJsonCount(1, 'data');

        $this->assertDatabaseMissing('virtual_product_inheritance_rules', ['attribute_id' => $a->id]);
        $this->assertDatabaseHas('virtual_product_inheritance_rules', ['attribute_id' => $b->id]);
    }

    public function test_sync_erstellt_fehlende_werte_bei_mitgliedern(): void
    {
        $virtual = $this->virtualProduct();
        $member = Product::factory()->create();
        $attribute = Attribute::factory()->create();

        ProductAttributeValue::factory()->create([
            'product_id' => $virtual->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'EN 62841-2-1',
        ]);

        $this->defineManualCluster($virtual, [$member->id]);
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-inheritance-rules", [
            'rules' => [['attribute_id' => $attribute->id, 'conflict_mode' => 'keep_local']],
        ])->assertOk();

        $response = $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.values_created', 1)
            ->assertJsonPath('data.member_count', 1);

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $member->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'EN 62841-2-1',
            'is_inherited' => true,
            'inherited_from_virtual_product_id' => $virtual->id,
        ]);
    }

    public function test_keep_local_laesst_lokalen_wert_unangetastet(): void
    {
        $virtual = $this->virtualProduct();
        $member = Product::factory()->create();
        $attribute = Attribute::factory()->create();

        ProductAttributeValue::factory()->create([
            'product_id' => $virtual->id, 'attribute_id' => $attribute->id, 'value_string' => 'Klammer-Wert',
        ]);
        ProductAttributeValue::factory()->create([
            'product_id' => $member->id, 'attribute_id' => $attribute->id, 'value_string' => 'Lokaler Wert',
        ]);

        $this->defineManualCluster($virtual, [$member->id]);
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-inheritance-rules", [
            'rules' => [['attribute_id' => $attribute->id, 'conflict_mode' => 'keep_local']],
        ])->assertOk();

        $response = $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.values_kept_local', 1);

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $member->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Lokaler Wert',
        ]);
    }

    public function test_force_override_ueberschreibt_lokalen_wert(): void
    {
        $virtual = $this->virtualProduct();
        $member = Product::factory()->create();
        $attribute = Attribute::factory()->create();

        ProductAttributeValue::factory()->create([
            'product_id' => $virtual->id, 'attribute_id' => $attribute->id, 'value_string' => 'Klammer-Wert',
        ]);
        ProductAttributeValue::factory()->create([
            'product_id' => $member->id, 'attribute_id' => $attribute->id, 'value_string' => 'Lokaler Wert',
        ]);

        $this->defineManualCluster($virtual, [$member->id]);
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-inheritance-rules", [
            'rules' => [['attribute_id' => $attribute->id, 'conflict_mode' => 'force_override']],
        ])->assertOk();

        $response = $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.values_overridden', 1);

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $member->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Klammer-Wert',
            'inherited_from_virtual_product_id' => $virtual->id,
        ]);
    }

    public function test_mitglied_eines_anderen_clusters_wird_uebersprungen(): void
    {
        $virtualA = $this->virtualProduct();
        $virtualB = $this->virtualProduct();
        $member = Product::factory()->create();
        $attribute = Attribute::factory()->create();

        // member bereits Mitglied von virtualA (simuliert durch vorhandenen vererbten Wert)
        ProductAttributeValue::factory()->create([
            'product_id' => $member->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'von A',
            'is_inherited' => true,
            'inherited_from_virtual_product_id' => $virtualA->id,
        ]);

        ProductAttributeValue::factory()->create([
            'product_id' => $virtualB->id, 'attribute_id' => $attribute->id, 'value_string' => 'von B',
        ]);

        $this->defineManualCluster($virtualB, [$member->id]);
        $this->putJson("/api/v1/products/{$virtualB->id}/virtual-inheritance-rules", [
            'rules' => [['attribute_id' => $attribute->id, 'conflict_mode' => 'force_override']],
        ])->assertOk();

        $response = $this->postJson("/api/v1/products/{$virtualB->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.member_count', 0)
            ->assertJsonCount(1, 'data.skipped_members');

        // Wert von A bleibt unangetastet
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $member->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'von A',
            'inherited_from_virtual_product_id' => $virtualA->id,
        ]);
    }

    public function test_entfernte_regel_entfernt_zuvor_vererbten_wert(): void
    {
        $virtual = $this->virtualProduct();
        $member = Product::factory()->create();
        $attribute = Attribute::factory()->create();

        ProductAttributeValue::factory()->create([
            'product_id' => $virtual->id, 'attribute_id' => $attribute->id, 'value_string' => 'Norm',
        ]);
        $this->defineManualCluster($virtual, [$member->id]);
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-inheritance-rules", [
            'rules' => [['attribute_id' => $attribute->id, 'conflict_mode' => 'keep_local']],
        ])->assertOk();
        $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();

        $this->assertDatabaseHas('product_attribute_values', ['product_id' => $member->id, 'attribute_id' => $attribute->id]);

        // Regel entfernen (leerer Regelsatz)
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-inheritance-rules", ['rules' => []])->assertOk();
        $response = $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.values_removed', 1);

        $this->assertDatabaseMissing('product_attribute_values', ['product_id' => $member->id, 'attribute_id' => $attribute->id]);
    }

    public function test_ausgeschiedenes_mitglied_verliert_vererbte_werte(): void
    {
        $virtual = $this->virtualProduct();
        $member = Product::factory()->create();
        $attribute = Attribute::factory()->create();

        ProductAttributeValue::factory()->create([
            'product_id' => $virtual->id, 'attribute_id' => $attribute->id, 'value_string' => 'Norm',
        ]);
        $this->defineManualCluster($virtual, [$member->id]);
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-inheritance-rules", [
            'rules' => [['attribute_id' => $attribute->id, 'conflict_mode' => 'keep_local']],
        ])->assertOk();
        $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();

        // Mitglied aus dem Cluster entfernen
        $this->defineManualCluster($virtual, []);
        $response = $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.values_removed', 1);

        $this->assertDatabaseMissing('product_attribute_values', ['product_id' => $member->id, 'attribute_id' => $attribute->id]);
    }

    public function test_erneuter_sync_aktualisiert_eigene_zuvor_vererbte_zeile_ohne_konflikt(): void
    {
        $virtual = $this->virtualProduct();
        $member = Product::factory()->create();
        $attribute = Attribute::factory()->create();

        ProductAttributeValue::factory()->create([
            'product_id' => $virtual->id, 'attribute_id' => $attribute->id, 'value_string' => 'Version 1',
        ]);
        $this->defineManualCluster($virtual, [$member->id]);
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-inheritance-rules", [
            'rules' => [['attribute_id' => $attribute->id, 'conflict_mode' => 'keep_local']],
        ])->assertOk();
        $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();

        // Wert auf der Klammer ändern und erneut syncen
        ProductAttributeValue::where('product_id', $virtual->id)->update(['value_string' => 'Version 2']);
        $response = $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.values_updated', 1)
            ->assertJsonPath('data.values_kept_local', 0);

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $member->id, 'attribute_id' => $attribute->id, 'value_string' => 'Version 2',
        ]);
    }
}
