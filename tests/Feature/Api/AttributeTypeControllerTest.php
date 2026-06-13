<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeTypeControllerTest extends TestCase
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

    public function test_index_gibt_paginierte_attributtypen_zurueck(): void
    {
        AttributeType::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/attribute-types');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_store_legt_attributtyp_an(): void
    {
        $response = $this->postJson('/api/v1/attribute-types', [
            'technical_name' => 'text-type',
            'name_de'        => 'Texttyp',
            'name_en'        => 'Text Type',
            'sort_order'     => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'text-type');

        $this->assertDatabaseHas('attribute_types', ['technical_name' => 'text-type']);
    }

    public function test_store_validierung_schlaegt_bei_fehlendem_name_de_fehl(): void
    {
        $response = $this->postJson('/api/v1/attribute-types', [
            'technical_name' => 'no-name',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name_de']);
    }

    public function test_store_validierung_verhindert_doppelten_technical_name(): void
    {
        AttributeType::factory()->create(['technical_name' => 'existing-type']);

        $response = $this->postJson('/api/v1/attribute-types', [
            'technical_name' => 'existing-type',
            'name_de'        => 'Nochmal',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name']);
    }

    public function test_show_gibt_attributtyp_zurueck(): void
    {
        $type = AttributeType::factory()->create();

        $response = $this->getJson("/api/v1/attribute-types/{$type->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $type->id);
    }

    public function test_update_aendert_attributtyp(): void
    {
        $type = AttributeType::factory()->create(['name_de' => 'Alt']);

        $response = $this->patchJson("/api/v1/attribute-types/{$type->id}", [
            'name_de' => 'Neu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_de', 'Neu');

        $this->assertDatabaseHas('attribute_types', ['id' => $type->id, 'name_de' => 'Neu']);
    }

    public function test_destroy_loescht_attributtyp_ohne_abhaengigkeiten(): void
    {
        $type = AttributeType::factory()->create();

        $response = $this->deleteJson("/api/v1/attribute-types/{$type->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('attribute_types', ['id' => $type->id]);
    }

    public function test_dependencies_meldet_keine_abhaengigkeiten_wenn_typ_frei(): void
    {
        $type = AttributeType::factory()->create();

        $response = $this->getJson("/api/v1/attribute-types/{$type->id}/dependencies");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['has_dependencies', 'total_count', 'dependencies']]);

        $response->assertJsonPath('data.has_dependencies', false);
    }

    public function test_dependencies_meldet_abhaengigkeiten_wenn_attribut_zugewiesen(): void
    {
        $type      = AttributeType::factory()->create();
        Attribute::factory()->create(['attribute_type_id' => $type->id]);

        $response = $this->getJson("/api/v1/attribute-types/{$type->id}/dependencies");

        $response->assertOk()
            ->assertJsonPath('data.has_dependencies', true);
    }

    public function test_unauthentifizierter_request_wird_abgelehnt(): void
    {
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/v1/attribute-types');

        $response->assertStatus(401);
    }
}
