<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductRelation;
use App\Models\ProductRelationType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests für RelationTypeController (ProductRelationType).
 *
 * Routen:
 *   GET/POST            /api/v1/relation-types
 *   GET/PUT/DELETE      /api/v1/relation-types/{relation_type}
 *   GET                 /api/v1/relation-types/{relation_type}/dependencies
 *   PUT                 /api/v1/relation-types/{relationType}/default-attributes
 */
class RelationTypeControllerTest extends TestCase
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

    public function test_index_gibt_paginierte_relation_types_zurueck(): void
    {
        ProductRelationType::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/relation-types');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_store_erstellt_relation_type(): void
    {
        $response = $this->postJson('/api/v1/relation-types', [
            'technical_name'   => 'zubehoer',
            'name_de'          => 'Zubehör',
            'name_en'          => 'Accessories',
            'is_bidirectional' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'zubehoer');

        $this->assertDatabaseHas('product_relation_types', ['technical_name' => 'zubehoer']);
    }

    public function test_store_validiert_duplikaten_technical_name(): void
    {
        ProductRelationType::factory()->create(['technical_name' => 'zubehoer']);

        $response = $this->postJson('/api/v1/relation-types', [
            'technical_name'   => 'zubehoer',
            'name_de'          => 'Anderer',
            'is_bidirectional' => false,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name']);
    }

    public function test_store_validiert_pflichtfelder(): void
    {
        $response = $this->postJson('/api/v1/relation-types', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name', 'name_de', 'is_bidirectional']);
    }

    public function test_show_gibt_relation_type_zurueck(): void
    {
        $type = ProductRelationType::factory()->create();

        $response = $this->getJson("/api/v1/relation-types/{$type->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $type->id);
    }

    public function test_update_aendert_relation_type(): void
    {
        $type = ProductRelationType::factory()->create(['name_de' => 'Alt']);

        $response = $this->putJson("/api/v1/relation-types/{$type->id}", [
            'name_de' => 'Neu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_de', 'Neu');
    }

    public function test_destroy_loescht_relation_type(): void
    {
        $type = ProductRelationType::factory()->create();

        $response = $this->deleteJson("/api/v1/relation-types/{$type->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('product_relation_types', ['id' => $type->id]);
    }

    public function test_destroy_mit_dependencies_gibt_409(): void
    {
        $type = ProductRelationType::factory()->create();

        // Zwei Produkte erstellen und eine Relation anlegen
        $products = Product::factory()->count(2)->create();
        ProductRelation::factory()->create([
            'relation_type_id' => $type->id,
            'source_product_id' => $products[0]->id,
            'target_product_id' => $products[1]->id,
        ]);

        $response = $this->deleteJson("/api/v1/relation-types/{$type->id}");

        $response->assertStatus(409)
            ->assertJsonPath('type', 'deletion_constraint');

        $this->assertDatabaseHas('product_relation_types', ['id' => $type->id]);
    }

    public function test_destroy_force_loescht_trotz_dependencies(): void
    {
        $type = ProductRelationType::factory()->create();

        $products = Product::factory()->count(2)->create();
        ProductRelation::factory()->create([
            'relation_type_id'  => $type->id,
            'source_product_id' => $products[0]->id,
            'target_product_id' => $products[1]->id,
        ]);

        $response = $this->deleteJson("/api/v1/relation-types/{$type->id}?force=true");

        $response->assertNoContent();
        $this->assertDatabaseMissing('product_relation_types', ['id' => $type->id]);
    }

    public function test_dependencies_gibt_constraint_info_zurueck(): void
    {
        $type = ProductRelationType::factory()->create();

        $response = $this->getJson("/api/v1/relation-types/{$type->id}/dependencies");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['has_dependencies', 'total_count', 'dependencies']]);
    }

    public function test_update_default_attributes_synchronisiert_attribute(): void
    {
        $type      = ProductRelationType::factory()->create();
        $attribute = Attribute::factory()->create();

        $response = $this->putJson("/api/v1/relation-types/{$type->id}/default-attributes", [
            'attributes' => [
                ['attribute_id' => $attribute->id, 'sort_order' => 1],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('relation_type_default_attributes', [
            'relation_type_id' => $type->id,
            'attribute_id'     => $attribute->id,
        ]);
    }

    public function test_update_default_attributes_leert_liste_bei_leerem_array(): void
    {
        $type      = ProductRelationType::factory()->create();
        $attribute = Attribute::factory()->create();
        $type->defaultAttributes()->attach($attribute->id, ['sort_order' => 0]);

        $response = $this->putJson("/api/v1/relation-types/{$type->id}/default-attributes", [
            'attributes' => [],
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('relation_type_default_attributes', [
            'relation_type_id' => $type->id,
        ]);
    }

    public function test_update_default_attributes_validiert_ungueltige_attribute_id(): void
    {
        $type = ProductRelationType::factory()->create();

        $response = $this->putJson("/api/v1/relation-types/{$type->id}/default-attributes", [
            'attributes' => [
                ['attribute_id' => 'kein-gueltiger-uuid', 'sort_order' => 0],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['attributes.0.attribute_id']);
    }

    // ─── Freie Attribute (allows_free_attributes) ────────────────

    public function test_store_default_erlaubt_freie_attribute(): void
    {
        $response = $this->postJson('/api/v1/relation-types', [
            'technical_name'   => 'zubehoer',
            'name_de'          => 'Zubehör',
            'is_bidirectional' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.allows_free_attributes', true);
    }

    public function test_store_kann_freie_attribute_deaktivieren(): void
    {
        $response = $this->postJson('/api/v1/relation-types', [
            'technical_name'         => 'strikt',
            'name_de'                => 'Strikt',
            'is_bidirectional'       => false,
            'allows_free_attributes' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.allows_free_attributes', false);

        $this->assertDatabaseHas('product_relation_types', [
            'technical_name'         => 'strikt',
            'allows_free_attributes' => false,
        ]);
    }

    public function test_update_schaltet_freie_attribute_um(): void
    {
        $type = ProductRelationType::factory()->create(['allows_free_attributes' => true]);

        $response = $this->putJson("/api/v1/relation-types/{$type->id}", [
            'allows_free_attributes' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.allows_free_attributes', false);
    }
}
