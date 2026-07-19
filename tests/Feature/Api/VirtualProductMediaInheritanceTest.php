<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\MediaUsageType;
use App\Models\Product;
use App\Models\ProductMediaAssignment;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\User;
use App\Models\VirtualProductMediaInheritanceRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests für Medien-Vererbung virtueller Produkte (Phase 2).
 *
 * Routen:
 *   GET  /api/v1/products/{product}/virtual-media-inheritance-rules
 *   PUT  /api/v1/products/{product}/virtual-media-inheritance-rules
 *   POST /api/v1/products/{product}/virtual-definition/sync
 */
class VirtualProductMediaInheritanceTest extends TestCase
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

    /**
     * Produkt eines Produkttyps mit aktivierter Cluster-Vererbung
     * (has_dynamic_cluster=true) — die Freischaltung des Features hängt
     * seit der Umstellung am Produkttyp, nicht mehr an product_type_ref.
     */
    private function virtualProduct(): Product
    {
        $type = ProductType::factory()->dynamicCluster()->create();

        return Product::factory()->create(['product_type_id' => $type->id]);
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
        $usageType = MediaUsageType::factory()->create();

        $this->putJson("/api/v1/products/{$virtual->id}/virtual-media-inheritance-rules", [
            'rules' => [
                ['usage_type_id' => $usageType->id, 'conflict_mode' => 'keep_local'],
            ],
        ])->assertOk()->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/products/{$virtual->id}/virtual-media-inheritance-rules")
            ->assertOk()
            ->assertJsonPath('data.0.usage_type_id', $usageType->id)
            ->assertJsonPath('data.0.conflict_mode', 'keep_local');
    }

    public function test_regeln_ersetzen_entfernt_nicht_mehr_enthaltene_usage_types(): void
    {
        $virtual = $this->virtualProduct();
        $a = MediaUsageType::factory()->create();
        $b = MediaUsageType::factory()->create();

        VirtualProductMediaInheritanceRule::create([
            'virtual_product_id' => $virtual->id, 'usage_type_id' => $a->id, 'conflict_mode' => 'keep_local',
        ]);

        $this->putJson("/api/v1/products/{$virtual->id}/virtual-media-inheritance-rules", [
            'rules' => [['usage_type_id' => $b->id, 'conflict_mode' => 'force_override']],
        ])->assertOk()->assertJsonCount(1, 'data');

        $this->assertDatabaseMissing('virtual_product_media_inheritance_rules', ['usage_type_id' => $a->id]);
        $this->assertDatabaseHas('virtual_product_media_inheritance_rules', ['usage_type_id' => $b->id]);
    }

    public function test_sync_erstellt_fehlende_zuordnungen_bei_mitgliedern(): void
    {
        $virtual = $this->virtualProduct();
        $member = Product::factory()->create();
        $usageType = MediaUsageType::factory()->create();
        $media = Media::factory()->create();

        ProductMediaAssignment::factory()->create([
            'product_id' => $virtual->id,
            'media_id' => $media->id,
            'usage_type_id' => $usageType->id,
        ]);

        $this->defineManualCluster($virtual, [$member->id]);
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-media-inheritance-rules", [
            'rules' => [['usage_type_id' => $usageType->id, 'conflict_mode' => 'keep_local']],
        ])->assertOk();

        $response = $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.media.assignments_created', 1)
            ->assertJsonPath('data.media.member_count', 1);

        $this->assertDatabaseHas('product_media_assignments', [
            'product_id' => $member->id,
            'media_id' => $media->id,
            'usage_type_id' => $usageType->id,
            'inherited_from_virtual_product_id' => $virtual->id,
        ]);
    }

    public function test_keep_local_laesst_lokale_zuordnung_unangetastet(): void
    {
        $virtual = $this->virtualProduct();
        $member = Product::factory()->create();
        $usageType = MediaUsageType::factory()->create();
        $klammerMedia = Media::factory()->create();
        $lokalMedia = Media::factory()->create();

        ProductMediaAssignment::factory()->create([
            'product_id' => $virtual->id, 'media_id' => $klammerMedia->id, 'usage_type_id' => $usageType->id,
        ]);
        ProductMediaAssignment::factory()->create([
            'product_id' => $member->id, 'media_id' => $lokalMedia->id, 'usage_type_id' => $usageType->id,
        ]);

        $this->defineManualCluster($virtual, [$member->id]);
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-media-inheritance-rules", [
            'rules' => [['usage_type_id' => $usageType->id, 'conflict_mode' => 'keep_local']],
        ])->assertOk();

        $response = $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.media.assignments_kept_local', 1);

        $this->assertDatabaseHas('product_media_assignments', [
            'product_id' => $member->id,
            'media_id' => $lokalMedia->id,
        ]);
        $this->assertDatabaseMissing('product_media_assignments', [
            'product_id' => $member->id,
            'media_id' => $klammerMedia->id,
        ]);
    }

    public function test_force_override_ersetzt_lokale_zuordnung(): void
    {
        $virtual = $this->virtualProduct();
        $member = Product::factory()->create();
        $usageType = MediaUsageType::factory()->create();
        $klammerMedia = Media::factory()->create();
        $lokalMedia = Media::factory()->create();

        ProductMediaAssignment::factory()->create([
            'product_id' => $virtual->id, 'media_id' => $klammerMedia->id, 'usage_type_id' => $usageType->id,
        ]);
        ProductMediaAssignment::factory()->create([
            'product_id' => $member->id, 'media_id' => $lokalMedia->id, 'usage_type_id' => $usageType->id,
        ]);

        $this->defineManualCluster($virtual, [$member->id]);
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-media-inheritance-rules", [
            'rules' => [['usage_type_id' => $usageType->id, 'conflict_mode' => 'force_override']],
        ])->assertOk();

        $response = $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.media.assignments_overridden', 1)
            ->assertJsonPath('data.media.assignments_created', 1);

        $this->assertDatabaseMissing('product_media_assignments', [
            'product_id' => $member->id,
            'media_id' => $lokalMedia->id,
        ]);
        $this->assertDatabaseHas('product_media_assignments', [
            'product_id' => $member->id,
            'media_id' => $klammerMedia->id,
            'inherited_from_virtual_product_id' => $virtual->id,
        ]);
    }

    public function test_mitglied_eines_anderen_medien_clusters_wird_uebersprungen(): void
    {
        $virtualA = $this->virtualProduct();
        $virtualB = $this->virtualProduct();
        $member = Product::factory()->create();
        $usageType = MediaUsageType::factory()->create();
        $mediaA = Media::factory()->create();
        $mediaB = Media::factory()->create();

        // member bereits Mitglied von virtualA (simuliert durch vorhandene vererbte Zuordnung)
        ProductMediaAssignment::factory()->create([
            'product_id' => $member->id,
            'media_id' => $mediaA->id,
            'usage_type_id' => $usageType->id,
            'inherited_from_virtual_product_id' => $virtualA->id,
        ]);

        ProductMediaAssignment::factory()->create([
            'product_id' => $virtualB->id, 'media_id' => $mediaB->id, 'usage_type_id' => $usageType->id,
        ]);

        $this->defineManualCluster($virtualB, [$member->id]);
        $this->putJson("/api/v1/products/{$virtualB->id}/virtual-media-inheritance-rules", [
            'rules' => [['usage_type_id' => $usageType->id, 'conflict_mode' => 'force_override']],
        ])->assertOk();

        $response = $this->postJson("/api/v1/products/{$virtualB->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.media.member_count', 0)
            ->assertJsonCount(1, 'data.media.skipped_members');

        $this->assertDatabaseHas('product_media_assignments', [
            'product_id' => $member->id,
            'media_id' => $mediaA->id,
            'inherited_from_virtual_product_id' => $virtualA->id,
        ]);
    }

    public function test_entfernte_regel_entfernt_zuvor_vererbte_zuordnung(): void
    {
        $virtual = $this->virtualProduct();
        $member = Product::factory()->create();
        $usageType = MediaUsageType::factory()->create();
        $media = Media::factory()->create();

        ProductMediaAssignment::factory()->create([
            'product_id' => $virtual->id, 'media_id' => $media->id, 'usage_type_id' => $usageType->id,
        ]);
        $this->defineManualCluster($virtual, [$member->id]);
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-media-inheritance-rules", [
            'rules' => [['usage_type_id' => $usageType->id, 'conflict_mode' => 'keep_local']],
        ])->assertOk();
        $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();

        $this->assertDatabaseHas('product_media_assignments', ['product_id' => $member->id, 'usage_type_id' => $usageType->id]);

        $this->putJson("/api/v1/products/{$virtual->id}/virtual-media-inheritance-rules", ['rules' => []])->assertOk();
        $response = $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.media.assignments_removed', 1);

        $this->assertDatabaseMissing('product_media_assignments', ['product_id' => $member->id, 'usage_type_id' => $usageType->id]);
    }

    public function test_ausgeschiedenes_mitglied_verliert_vererbte_zuordnungen(): void
    {
        $virtual = $this->virtualProduct();
        $member = Product::factory()->create();
        $usageType = MediaUsageType::factory()->create();
        $media = Media::factory()->create();

        ProductMediaAssignment::factory()->create([
            'product_id' => $virtual->id, 'media_id' => $media->id, 'usage_type_id' => $usageType->id,
        ]);
        $this->defineManualCluster($virtual, [$member->id]);
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-media-inheritance-rules", [
            'rules' => [['usage_type_id' => $usageType->id, 'conflict_mode' => 'keep_local']],
        ])->assertOk();
        $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();

        $this->defineManualCluster($virtual, []);
        $response = $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.media.assignments_removed', 1);

        $this->assertDatabaseMissing('product_media_assignments', ['product_id' => $member->id, 'usage_type_id' => $usageType->id]);
    }

    public function test_mehrere_medien_pro_usage_type_werden_alle_vererbt(): void
    {
        $virtual = $this->virtualProduct();
        $member = Product::factory()->create();
        $usageType = MediaUsageType::factory()->create();
        $mediaA = Media::factory()->create();
        $mediaB = Media::factory()->create();

        ProductMediaAssignment::factory()->create([
            'product_id' => $virtual->id, 'media_id' => $mediaA->id, 'usage_type_id' => $usageType->id, 'sort_order' => 0,
        ]);
        ProductMediaAssignment::factory()->create([
            'product_id' => $virtual->id, 'media_id' => $mediaB->id, 'usage_type_id' => $usageType->id, 'sort_order' => 1,
        ]);

        $this->defineManualCluster($virtual, [$member->id]);
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-media-inheritance-rules", [
            'rules' => [['usage_type_id' => $usageType->id, 'conflict_mode' => 'keep_local']],
        ])->assertOk();

        $response = $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();
        $response->assertJsonPath('data.media.assignments_created', 2);

        $this->assertDatabaseHas('product_media_assignments', ['product_id' => $member->id, 'media_id' => $mediaA->id]);
        $this->assertDatabaseHas('product_media_assignments', ['product_id' => $member->id, 'media_id' => $mediaB->id]);
    }

    public function test_is_primary_wird_nicht_vererbt(): void
    {
        // "Hauptbild" ist eine produktindividuelle Redaktionsentscheidung —
        // würde is_primary 1:1 mitkopiert, könnte ein Mitglied, das über
        // mehrere Usage-Types Medien erbt, mehrere is_primary=true-Zeilen
        // bekommen (verletzt die Annahme "höchstens ein Hauptbild pro Produkt").
        $virtual = $this->virtualProduct();
        $member = Product::factory()->create();
        $usageType = MediaUsageType::factory()->create();
        $media = Media::factory()->create();

        ProductMediaAssignment::factory()->create([
            'product_id' => $virtual->id, 'media_id' => $media->id, 'usage_type_id' => $usageType->id, 'is_primary' => true,
        ]);

        $this->defineManualCluster($virtual, [$member->id]);
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-media-inheritance-rules", [
            'rules' => [['usage_type_id' => $usageType->id, 'conflict_mode' => 'keep_local']],
        ])->assertOk();
        $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/sync")->assertOk();

        $this->assertDatabaseHas('product_media_assignments', [
            'product_id' => $member->id, 'media_id' => $media->id, 'is_primary' => false,
        ]);
    }
}
