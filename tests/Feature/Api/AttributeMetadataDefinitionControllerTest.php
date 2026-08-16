<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeMetadataDefinition;
use App\Models\AttributeMetadataValue;
use App\Models\Permission;
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

    /**
     * Optionen werden als `Label::Wert` gepflegt, gespeichert wird nur der Wert-Anteil.
     * Ein Diff über die Rohstrings würde hier nie greifen.
     */
    public function test_update_lehnt_entfernen_einer_benutzten_option_im_label_wert_format_ab(): void
    {
        $definition = AttributeMetadataDefinition::factory()->create([
            'value_type' => 'select',
            'options' => ['Rot::#FF0000', 'Grün::#00FF00'],
        ]);
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $definition->id,
            'value' => '#FF0000',
        ]);

        $this->putJson("/api/v1/attribute-metadata-definitions/{$definition->id}", [
            'options' => ['Grün::#00FF00'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['options']);
    }

    public function test_update_erlaubt_umbenennen_eines_labels_bei_gleichem_wert(): void
    {
        $definition = AttributeMetadataDefinition::factory()->create([
            'value_type' => 'select',
            'options' => ['Rot::#FF0000'],
        ]);
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $definition->id,
            'value' => '#FF0000',
        ]);

        $this->putJson("/api/v1/attribute-metadata-definitions/{$definition->id}", [
            'options' => ['Signalrot::#FF0000'],
        ])->assertOk();
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

    // ─── Berechtigungen ───────────────────────────────────────────────

    /**
     * Meldet den aktuellen Benutzer mit einer frisch gebauten Rolle an.
     *
     * @param array<int, string> $permissions
     */
    private function actingAsRole(string $roleName, array $permissions): void
    {
        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        $role = Role::findOrCreate($roleName, 'sanctum');
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->syncRoles([$role]);
        $this->actingAs($user);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_ohne_berechtigung_kein_zugriff(): void
    {
        $this->actingAsRole('Ohne Rechte', ['products.view']);

        $definition = AttributeMetadataDefinition::factory()->create();

        $this->getJson('/api/v1/attribute-metadata-definitions')->assertForbidden();
        $this->postJson('/api/v1/attribute-metadata-definitions', [
            'technical_name' => 'x', 'name_de' => 'X', 'value_type' => 'text',
        ])->assertForbidden();
        $this->putJson("/api/v1/attribute-metadata-definitions/{$definition->id}", ['name_de' => 'Y'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/attribute-metadata-definitions/{$definition->id}")
            ->assertForbidden();
    }

    public function test_lesende_rolle_darf_nur_lesen(): void
    {
        $this->actingAsRole('Nur Lesen', ['attribute-metadata.view']);

        $definition = AttributeMetadataDefinition::factory()->create();

        $this->getJson('/api/v1/attribute-metadata-definitions')->assertOk();
        $this->getJson("/api/v1/attribute-metadata-definitions/{$definition->id}")->assertOk();

        $this->postJson('/api/v1/attribute-metadata-definitions', [
            'technical_name' => 'x', 'name_de' => 'X', 'value_type' => 'text',
        ])->assertForbidden();
        $this->putJson("/api/v1/attribute-metadata-definitions/{$definition->id}", ['name_de' => 'Y'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/attribute-metadata-definitions/{$definition->id}")
            ->assertForbidden();
    }

    public function test_pflegende_rolle_darf_anlegen_und_aendern_aber_nicht_loeschen(): void
    {
        $this->actingAsRole('Pflege', [
            'attribute-metadata.view', 'attribute-metadata.create', 'attribute-metadata.edit',
        ]);

        $this->postJson('/api/v1/attribute-metadata-definitions', [
            'technical_name' => 'datenherkunft', 'name_de' => 'Datenherkunft', 'value_type' => 'text',
        ])->assertCreated();

        $definition = AttributeMetadataDefinition::where('technical_name', 'datenherkunft')->firstOrFail();

        $this->putJson("/api/v1/attribute-metadata-definitions/{$definition->id}", ['name_de' => 'Neu'])
            ->assertOk();
        $this->deleteJson("/api/v1/attribute-metadata-definitions/{$definition->id}")
            ->assertForbidden();
    }

    /**
     * Die Rollen-UI liest Labels und Gruppen aus GET /permissions. Fehlt der
     * Eintrag dort, landen die Rechte unter "Sonstige" mit rohem Schluessel.
     */
    public function test_berechtigungskatalog_kennt_attribut_metadaten(): void
    {
        $response = $this->getJson('/api/v1/permissions')->assertOk();

        $this->assertSame('Attribut-Metadaten', $response->json('labels.attribute-metadata'));

        $konfiguration = collect($response->json('groups'))
            ->firstWhere('label', 'Konfiguration');

        $this->assertNotNull($konfiguration, 'Gruppe "Konfiguration" fehlt im Katalog.');
        $this->assertContains('attribute-metadata', $konfiguration['entities']);
    }
}
