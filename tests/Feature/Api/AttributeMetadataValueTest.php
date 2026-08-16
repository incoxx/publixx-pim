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

/**
 * Lesen und Schreiben von Metadatenwerten am Attribut-Endpoint.
 */
class AttributeMetadataValueTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected AttributeMetadataDefinition $herkunft;

    protected AttributeMetadataDefinition $eigentuemer;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->user = User::factory()->create();
        $role = Role::findOrCreate('Admin', 'sanctum');
        $this->user->assignRole($role);
        $this->actingAs($this->user);

        $this->herkunft = AttributeMetadataDefinition::factory()->select()->create([
            'technical_name' => 'datenherkunft',
            'name_de' => 'Datenherkunft',
        ]);

        $this->eigentuemer = AttributeMetadataDefinition::factory()->create([
            'technical_name' => 'dateneigentuemer',
            'name_de' => 'Dateneigentümer',
            'value_type' => 'text',
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function attributePayload(array $overrides = []): array
    {
        return array_merge([
            'technical_name' => 'gewicht',
            'name_de' => 'Gewicht',
            'data_type' => 'String',
            'is_translatable' => false,
            'is_multipliable' => false,
        ], $overrides);
    }

    // ─── Schreiben ────────────────────────────────────────────────────

    public function test_store_legt_attribut_mit_metadaten_an(): void
    {
        $response = $this->postJson('/api/v1/attributes', $this->attributePayload([
            'metadata' => [
                'datenherkunft' => 'ERP',
                'dateneigentuemer' => 'Produktmanagement',
            ],
        ]));

        $response->assertCreated();

        $attribute = Attribute::where('technical_name', 'gewicht')->firstOrFail();

        $this->assertDatabaseHas('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
            'definition_id' => $this->herkunft->id,
            'value' => 'ERP',
        ]);
        $this->assertDatabaseHas('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
            'definition_id' => $this->eigentuemer->id,
            'value' => 'Produktmanagement',
        ]);
    }

    public function test_update_aktualisiert_metadatenwert(): void
    {
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $this->herkunft->id,
            'value' => 'ERP',
        ]);

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'metadata' => ['datenherkunft' => 'Agentur'],
        ])->assertOk();

        $this->assertDatabaseHas('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
            'definition_id' => $this->herkunft->id,
            'value' => 'Agentur',
        ]);
        $this->assertSame(1, AttributeMetadataValue::where('attribute_id', $attribute->id)->count());
    }

    public function test_update_mit_leerem_wert_loescht_die_zeile(): void
    {
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $this->eigentuemer->id,
            'value' => 'Produktmanagement',
        ]);

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'metadata' => ['dateneigentuemer' => ''],
        ])->assertOk();

        $this->assertDatabaseMissing('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
            'definition_id' => $this->eigentuemer->id,
        ]);
    }

    /**
     * Regressionsschutz: PUT /attributes/{id} wird auch mit Teil-Payloads
     * aufgerufen (z.B. nur parent_attribute_id beim Composite-Handling).
     * Ohne den `metadata`-Key dürfen die Werte nicht angefasst werden.
     */
    public function test_update_ohne_metadata_key_laesst_werte_unangetastet(): void
    {
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $this->herkunft->id,
            'value' => 'ERP',
        ]);

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'name_de' => 'Neuer Name',
        ])->assertOk();

        $this->assertDatabaseHas('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
            'definition_id' => $this->herkunft->id,
            'value' => 'ERP',
        ]);
    }

    public function test_metadaten_teilupdate_laesst_andere_definitionen_unberuehrt(): void
    {
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $this->herkunft->id,
            'value' => 'ERP',
        ]);

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'metadata' => ['dateneigentuemer' => 'Marketing'],
        ])->assertOk();

        $this->assertDatabaseHas('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
            'definition_id' => $this->herkunft->id,
            'value' => 'ERP',
        ]);
    }

    public function test_mehrfachauswahl_wird_als_json_gespeichert(): void
    {
        $mehrfach = AttributeMetadataDefinition::factory()->create([
            'technical_name' => 'datenverbindung',
            'name_de' => 'Datenverbindung',
            'value_type' => 'multiselect',
            'options' => ['SAP', 'PIM-Sync', 'Manuell'],
        ]);

        $attribute = Attribute::factory()->create();

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'metadata' => ['datenverbindung' => ['SAP', 'Manuell']],
        ])->assertOk();

        $value = AttributeMetadataValue::where('attribute_id', $attribute->id)
            ->where('definition_id', $mehrfach->id)
            ->firstOrFail();

        $this->assertSame(['SAP', 'Manuell'], $value->value_json);
        $this->assertNull($value->value);
    }

    // ─── Validierung ──────────────────────────────────────────────────

    public function test_unbekannte_definition_gibt_422(): void
    {
        $attribute = Attribute::factory()->create();

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'metadata' => ['gibtesnicht' => 'x'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata.gibtesnicht']);
    }

    public function test_wert_ausserhalb_der_optionen_gibt_422(): void
    {
        $attribute = Attribute::factory()->create();

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'metadata' => ['datenherkunft' => 'Zauberei'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata.datenherkunft']);
    }

    public function test_leeres_pflichtfeld_gibt_422(): void
    {
        $pflicht = AttributeMetadataDefinition::factory()->create([
            'technical_name' => 'pflichtfeld',
            'name_de' => 'Pflichtfeld',
            'value_type' => 'text',
            'is_required' => true,
        ]);

        $attribute = Attribute::factory()->create();

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'metadata' => ['pflichtfeld' => ''],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata.pflichtfeld']);

        $this->assertSame(0, $pflicht->values()->count());
    }

    // ─── Lesen ────────────────────────────────────────────────────────

    public function test_show_ohne_include_liefert_keine_metadaten(): void
    {
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $this->herkunft->id,
            'value' => 'ERP',
        ]);

        $this->getJson("/api/v1/attributes/{$attribute->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.metadata');
    }

    public function test_show_mit_include_liefert_metadaten_als_map(): void
    {
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $this->herkunft->id,
            'value' => 'ERP',
        ]);
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $this->eigentuemer->id,
            'value' => 'Produktmanagement',
        ]);

        $this->getJson("/api/v1/attributes/{$attribute->id}?include=metadataValues")
            ->assertOk()
            ->assertJsonPath('data.metadata.datenherkunft', 'ERP')
            ->assertJsonPath('data.metadata.dateneigentuemer', 'Produktmanagement');
    }

    public function test_index_mit_include_liefert_metadaten(): void
    {
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $this->herkunft->id,
            'value' => 'Marketing',
        ]);

        $this->getJson('/api/v1/attributes?include=metadataValues')
            ->assertOk()
            ->assertJsonPath('data.0.metadata.datenherkunft', 'Marketing');
    }

    public function test_gespeicherte_metadaten_sind_direkt_wieder_lesbar(): void
    {
        $response = $this->postJson('/api/v1/attributes', $this->attributePayload([
            'metadata' => ['datenherkunft' => 'Agentur'],
        ]));
        $response->assertCreated();

        $attributeId = $response->json('data.id');

        $this->getJson("/api/v1/attributes/{$attributeId}?include=metadataValues")
            ->assertOk()
            ->assertJsonPath('data.metadata.datenherkunft', 'Agentur');
    }

    // ─── Lebenszyklus ─────────────────────────────────────────────────

    public function test_copy_uebernimmt_metadatenwerte(): void
    {
        $attribute = Attribute::factory()->create(['technical_name' => 'original']);
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $this->herkunft->id,
            'value' => 'ERP',
        ]);

        $response = $this->postJson("/api/v1/attributes/{$attribute->id}/copy");
        $response->assertCreated();

        $copyId = $response->json('data.id');

        $this->assertDatabaseHas('attribute_metadata_values', [
            'attribute_id' => $copyId,
            'definition_id' => $this->herkunft->id,
            'value' => 'ERP',
        ]);
    }

    public function test_attribut_loeschen_raeumt_metadatenwerte_ab(): void
    {
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $this->herkunft->id,
            'value' => 'ERP',
        ]);

        $this->deleteJson("/api/v1/attributes/{$attribute->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('attribute_metadata_values', ['attribute_id' => $attribute->id]);
    }

    public function test_bulk_delete_raeumt_metadatenwerte_ab(): void
    {
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $this->herkunft->id,
            'value' => 'ERP',
        ]);

        $this->postJson('/api/v1/attributes/bulk-delete', [
            'attribute_ids' => [$attribute->id],
        ])->assertOk();

        $this->assertDatabaseMissing('attribute_metadata_values', ['attribute_id' => $attribute->id]);
    }
}
