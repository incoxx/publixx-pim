<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductRelationType;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests für die Einschränkung von Beziehungstypen auf bestimmte Produkttypen
 * (allowed_source_product_type_ids / allowed_target_product_type_ids auf
 * ProductRelationType). Leer/nicht gesetzt = alle Produkttypen erlaubt (Default).
 */
class ProductRelationTypeRestrictionTest extends TestCase
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

    public function test_ohne_einschraenkung_ist_jeder_produkttyp_erlaubt(): void
    {
        $relationType = ProductRelationType::factory()->create();
        $source = Product::factory()->create();
        $target = Product::factory()->create();

        $this->postJson("/api/v1/products/{$source->id}/relations", [
            'relation_type_id' => $relationType->id,
            'target_product_id' => $target->id,
        ])->assertCreated();
    }

    public function test_beziehung_wird_abgelehnt_wenn_quell_produkttyp_nicht_erlaubt_ist(): void
    {
        $allowedType = ProductType::factory()->create();
        $downloadType = ProductType::factory()->create();
        $relationType = ProductRelationType::factory()->create([
            'allowed_source_product_type_ids' => [$allowedType->id],
        ]);

        $source = Product::factory()->create(['product_type_id' => $downloadType->id]);
        $target = Product::factory()->create();

        $this->postJson("/api/v1/products/{$source->id}/relations", [
            'relation_type_id' => $relationType->id,
            'target_product_id' => $target->id,
        ])->assertStatus(422)->assertJsonValidationErrors('relation_type_id');

        $this->assertDatabaseMissing('product_relations', ['source_product_id' => $source->id]);
    }

    public function test_beziehung_wird_erlaubt_wenn_quell_produkttyp_in_liste_ist(): void
    {
        $allowedType = ProductType::factory()->create();
        $relationType = ProductRelationType::factory()->create([
            'allowed_source_product_type_ids' => [$allowedType->id],
        ]);

        $source = Product::factory()->create(['product_type_id' => $allowedType->id]);
        $target = Product::factory()->create();

        $this->postJson("/api/v1/products/{$source->id}/relations", [
            'relation_type_id' => $relationType->id,
            'target_product_id' => $target->id,
        ])->assertCreated();
    }

    public function test_beziehung_wird_abgelehnt_wenn_ziel_produkttyp_nicht_erlaubt_ist(): void
    {
        $allowedTargetType = ProductType::factory()->create();
        $otherType = ProductType::factory()->create();
        $relationType = ProductRelationType::factory()->create([
            'allowed_target_product_type_ids' => [$allowedTargetType->id],
        ]);

        $source = Product::factory()->create();
        $target = Product::factory()->create(['product_type_id' => $otherType->id]);

        $this->postJson("/api/v1/products/{$source->id}/relations", [
            'relation_type_id' => $relationType->id,
            'target_product_id' => $target->id,
        ])->assertStatus(422)->assertJsonValidationErrors('target_product_id');
    }

    public function test_relation_type_kann_mit_erlaubten_produkttypen_angelegt_werden(): void
    {
        $type = ProductType::factory()->create();

        $this->postJson('/api/v1/relation-types', [
            'technical_name' => 'has_spare_part',
            'name_de' => 'hat Ersatzteil',
            'is_bidirectional' => false,
            'allowed_source_product_type_ids' => [$type->id],
        ])->assertCreated()
            ->assertJsonPath('data.allowed_source_product_type_ids', [$type->id]);
    }

    // -----------------------------------------------------------------------
    // Bidirektionale Beziehungstypen: Quell- und Ziel-Produkttypen müssen
    // synchron bleiben, sonst wäre A→B erlaubt, B→A aber nicht.
    // -----------------------------------------------------------------------

    public function test_bidirektionaler_typ_synchronisiert_ziel_mit_quell_produkttypen_bei_erstellung(): void
    {
        $type = ProductType::factory()->create();
        $other = ProductType::factory()->create();

        $response = $this->postJson('/api/v1/relation-types', [
            'technical_name' => 'zubehoer',
            'name_de' => 'Zubehör',
            'is_bidirectional' => true,
            'allowed_source_product_type_ids' => [$type->id],
            'allowed_target_product_type_ids' => [$other->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.allowed_source_product_type_ids', [$type->id])
            ->assertJsonPath('data.allowed_target_product_type_ids', [$type->id]);
    }

    public function test_bidirektionaler_typ_synchronisiert_ziel_mit_quell_produkttypen_bei_aktualisierung(): void
    {
        $type = ProductType::factory()->create();
        $mismatched = ProductType::factory()->create();
        $relationType = ProductRelationType::factory()->create([
            'is_bidirectional' => false,
            'allowed_source_product_type_ids' => [$type->id],
            'allowed_target_product_type_ids' => [$mismatched->id],
        ]);

        $response = $this->putJson("/api/v1/relation-types/{$relationType->id}", [
            'is_bidirectional' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.allowed_target_product_type_ids', [$type->id]);
    }

    public function test_nicht_bidirektionaler_typ_behaelt_unterschiedliche_produkttypen(): void
    {
        $sourceType = ProductType::factory()->create();
        $targetType = ProductType::factory()->create();

        $response = $this->postJson('/api/v1/relation-types', [
            'technical_name' => 'zeigt_auf',
            'name_de' => 'Zeigt auf',
            'is_bidirectional' => false,
            'allowed_source_product_type_ids' => [$sourceType->id],
            'allowed_target_product_type_ids' => [$targetType->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.allowed_source_product_type_ids', [$sourceType->id])
            ->assertJsonPath('data.allowed_target_product_type_ids', [$targetType->id]);
    }
}
