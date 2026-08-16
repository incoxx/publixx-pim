<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeMetadataDefinition;
use App\Models\AttributeMetadataValue;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeMetadataDefinitionControllerTest extends TestCase
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

    public function test_index_gibt_paginierte_definitionen_zurueck(): void
    {
        AttributeMetadataDefinition::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/attribute-metadata-definitions');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_store_legt_definition_an(): void
    {
        $response = $this->postJson('/api/v1/attribute-metadata-definitions', [
            'technical_name' => 'dateneigentuemer',
            'name_de' => 'Dateneigentümer',
            'name_en' => 'Data Owner',
            'value_type' => 'text',
            'sort_order' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'dateneigentuemer')
            ->assertJsonPath('data.value_type', 'text');

        $this->assertDatabaseHas('attribute_metadata_definitions', [
            'technical_name' => 'dateneigentuemer',
        ]);
    }

    public function test_store_legt_auswahlfeld_mit_optionen_an(): void
    {
        $response = $this->postJson('/api/v1/attribute-metadata-definitions', [
            'technical_name' => 'datenherkunft',
            'name_de' => 'Datenherkunft',
            'value_type' => 'select',
            'options' => ['ERP', 'Agentur', 'Marketing'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.options', ['ERP', 'Agentur', 'Marketing']);
    }

    public function test_store_validierung_schlaegt_bei_fehlendem_name_de_fehl(): void
    {
        $response = $this->postJson('/api/v1/attribute-metadata-definitions', [
            'technical_name' => 'ohne-name',
            'value_type' => 'text',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name_de']);
    }

    public function test_store_validierung_verhindert_doppelten_technical_name(): void
    {
        AttributeMetadataDefinition::factory()->create(['technical_name' => 'datenherkunft']);

        $response = $this->postJson('/api/v1/attribute-metadata-definitions', [
            'technical_name' => 'datenherkunft',
            'name_de' => 'Datenherkunft',
            'value_type' => 'text',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name']);
    }

    public function test_store_lehnt_ungueltigen_value_type_ab(): void
    {
        $response = $this->postJson('/api/v1/attribute-metadata-definitions', [
            'technical_name' => 'irgendwas',
            'name_de' => 'Irgendwas',
            'value_type' => 'quantenfeld',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['value_type']);
    }

    public function test_store_verlangt_optionen_bei_auswahlfeld(): void
    {
        $response = $this->postJson('/api/v1/attribute-metadata-definitions', [
            'technical_name' => 'datenherkunft',
            'name_de' => 'Datenherkunft',
            'value_type' => 'select',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['options']);
    }

    public function test_show_gibt_definition_mit_wertezahl_zurueck(): void
    {
        $definition = AttributeMetadataDefinition::factory()->create();
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $definition->id,
            'value' => 'ERP',
        ]);

        $response = $this->getJson("/api/v1/attribute-metadata-definitions/{$definition->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $definition->id)
            ->assertJsonPath('data.values_count', 1);
    }

    public function test_update_aendert_namen(): void
    {
        $definition = AttributeMetadataDefinition::factory()->create(['name_de' => 'Alt']);

        $response = $this->putJson("/api/v1/attribute-metadata-definitions/{$definition->id}", [
            'name_de' => 'Neu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_de', 'Neu');
    }

    public function test_update_aendert_technical_name_nicht(): void
    {
        $definition = AttributeMetadataDefinition::factory()->create(['technical_name' => 'datenherkunft']);

        $this->putJson("/api/v1/attribute-metadata-definitions/{$definition->id}", [
            'technical_name' => 'neuer-name',
            'name_de' => 'Datenherkunft',
        ])->assertOk();

        $this->assertSame('datenherkunft', $definition->fresh()->technical_name);
    }

    public function test_update_lehnt_entfernen_einer_benutzten_option_ab(): void
    {
        $definition = AttributeMetadataDefinition::factory()->select()->create();
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $definition->id,
            'value' => 'Agentur',
        ]);

        $response = $this->putJson("/api/v1/attribute-metadata-definitions/{$definition->id}", [
            'options' => ['ERP', 'Marketing'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['options']);
    }

    public function test_dependencies_meldet_anzahl_der_werte(): void
    {
        $definition = AttributeMetadataDefinition::factory()->create();
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $definition->id,
            'value' => 'ERP',
        ]);

        $response = $this->getJson("/api/v1/attribute-metadata-definitions/{$definition->id}/dependencies");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['has_dependencies', 'total_count', 'dependencies']])
            ->assertJsonPath('data.has_dependencies', true)
            ->assertJsonPath('data.total_count', 1);
    }

    public function test_destroy_loescht_definition_ohne_werte(): void
    {
        $definition = AttributeMetadataDefinition::factory()->create();

        $this->deleteJson("/api/v1/attribute-metadata-definitions/{$definition->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('attribute_metadata_definitions', ['id' => $definition->id]);
    }

    public function test_destroy_gibt_409_bei_vorhandenen_werten(): void
    {
        $definition = AttributeMetadataDefinition::factory()->create();
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $definition->id,
            'value' => 'ERP',
        ]);

        $this->deleteJson("/api/v1/attribute-metadata-definitions/{$definition->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('attribute_metadata_definitions', ['id' => $definition->id]);
    }

    public function test_destroy_mit_force_loescht_werte_mit(): void
    {
        $definition = AttributeMetadataDefinition::factory()->create();
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $definition->id,
            'value' => 'ERP',
        ]);

        $this->deleteJson("/api/v1/attribute-metadata-definitions/{$definition->id}?force=true")
            ->assertNoContent();

        $this->assertDatabaseMissing('attribute_metadata_definitions', ['id' => $definition->id]);
        $this->assertDatabaseMissing('attribute_metadata_values', ['definition_id' => $definition->id]);
        // Das Attribut selbst bleibt unversehrt
        $this->assertDatabaseHas('attributes', ['id' => $attribute->id]);
    }

    public function test_unauthentifizierter_request_wird_abgelehnt(): void
    {
        app('auth')->forgetGuards();

        $this->getJson('/api/v1/attribute-metadata-definitions')
            ->assertUnauthorized();
    }
}
