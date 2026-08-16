<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeMetadataDefinition;
use App\Models\AttributeMetadataValue;
use App\Models\Role;
use App\Models\User;
use App\Services\Attributes\AttributeMetadataService;
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

    public function test_array_bei_auswahlfeld_gibt_422_statt_500(): void
    {
        $attribute = Attribute::factory()->create();

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'metadata' => ['datenherkunft' => ['ERP', 'Agentur']],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata.datenherkunft']);
    }

    /**
     * Ohne Formprüfung passiert ein Array die Validierung für text/textarea/boolean,
     * normalize() liefert dann null und die gespeicherte Zeile verschwindet stillschweigend.
     */
    public function test_array_bei_textfeld_gibt_422_und_loescht_nichts(): void
    {
        $attribute = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $attribute->id,
            'definition_id' => $this->eigentuemer->id,
            'value' => 'Produktmanagement',
        ]);

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'metadata' => ['dateneigentuemer' => ['a', 'b']],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata.dateneigentuemer']);

        $this->assertDatabaseHas('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
            'definition_id' => $this->eigentuemer->id,
            'value' => 'Produktmanagement',
        ]);
    }

    public function test_verschachteltes_array_in_mehrfachauswahl_gibt_422(): void
    {
        AttributeMetadataDefinition::factory()->create([
            'technical_name' => 'datenverbindung',
            'name_de' => 'Datenverbindung',
            'value_type' => 'multiselect',
            'options' => ['SAP', 'Manuell'],
        ]);

        $attribute = Attribute::factory()->create();

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'metadata' => ['datenverbindung' => [['SAP']]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata.datenverbindung']);
    }

    public function test_skalar_bei_mehrfachauswahl_gibt_422(): void
    {
        AttributeMetadataDefinition::factory()->create([
            'technical_name' => 'datenverbindung',
            'name_de' => 'Datenverbindung',
            'value_type' => 'multiselect',
            'options' => ['SAP', 'Manuell'],
        ]);

        $attribute = Attribute::factory()->create();

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'metadata' => ['datenverbindung' => 'SAP'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata.datenverbindung']);
    }

    public function test_option_im_label_wert_format_wird_als_wert_gespeichert(): void
    {
        AttributeMetadataDefinition::factory()->create([
            'technical_name' => 'ampel',
            'name_de' => 'Ampel',
            'value_type' => 'select',
            'options' => ['Rot::#FF0000', 'Grün::#00FF00'],
        ]);

        $attribute = Attribute::factory()->create();

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'metadata' => ['ampel' => '#FF0000'],
        ])->assertOk();

        $this->getJson("/api/v1/attributes/{$attribute->id}?include=metadataValues")
            ->assertOk()
            ->assertJsonPath('data.metadata.ampel', '#FF0000');
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

    // ─── Filtern ──────────────────────────────────────────────────────

    /** Legt ein Attribut mit einem Metadatenwert an. */
    private function attributeWith(AttributeMetadataDefinition $definition, ?string $value, ?array $json = null): Attribute
    {
        $attribute = Attribute::factory()->create();

        if ($value !== null || $json !== null) {
            AttributeMetadataValue::create([
                'attribute_id' => $attribute->id,
                'definition_id' => $definition->id,
                'value' => $value,
                'value_json' => $json,
            ]);
        }

        return $attribute;
    }

    public function test_filter_nach_exaktem_metadatenwert(): void
    {
        $treffer = $this->attributeWith($this->herkunft, 'ERP');
        $this->attributeWith($this->herkunft, 'Agentur');
        $this->attributeWith($this->herkunft, null);

        $this->getJson('/api/v1/attributes?filter[meta:datenherkunft]=ERP')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $treffer->id);
    }

    public function test_filter_ohne_wert_findet_luecken(): void
    {
        $this->attributeWith($this->herkunft, 'ERP');
        $ohneWert = $this->attributeWith($this->herkunft, null);

        $this->getJson('/api/v1/attributes?filter[meta:datenherkunft]=__none__')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ohneWert->id);
    }

    public function test_filter_mit_beliebigem_wert(): void
    {
        $this->attributeWith($this->herkunft, 'ERP');
        $this->attributeWith($this->herkunft, 'Agentur');
        $this->attributeWith($this->herkunft, null);

        $this->getJson('/api/v1/attributes?filter[meta:datenherkunft]=__any__')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_filter_auf_mehrfachauswahl(): void
    {
        $mehrfach = AttributeMetadataDefinition::factory()->create([
            'technical_name' => 'datenverbindung',
            'name_de' => 'Datenverbindung',
            'value_type' => 'multiselect',
            'options' => ['SAP', 'PIM-Sync', 'Manuell'],
        ]);

        $treffer = $this->attributeWith($mehrfach, null, ['SAP', 'Manuell']);
        $this->attributeWith($mehrfach, null, ['PIM-Sync']);

        $this->getJson('/api/v1/attributes?filter[meta:datenverbindung]=SAP')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $treffer->id);
    }

    public function test_mehrere_metadaten_filter_werden_kombiniert(): void
    {
        $treffer = Attribute::factory()->create();
        AttributeMetadataValue::create([
            'attribute_id' => $treffer->id, 'definition_id' => $this->herkunft->id, 'value' => 'ERP',
        ]);
        AttributeMetadataValue::create([
            'attribute_id' => $treffer->id, 'definition_id' => $this->eigentuemer->id, 'value' => 'Marketing',
        ]);

        // Nur eine der beiden Bedingungen erfuellt
        $this->attributeWith($this->herkunft, 'ERP');

        $this->getJson('/api/v1/attributes?filter[meta:datenherkunft]=ERP&filter[meta:dateneigentuemer]=Marketing')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $treffer->id);
    }

    public function test_unbekannter_metadaten_filter_wird_ignoriert(): void
    {
        Attribute::factory()->count(2)->create();

        $this->getJson('/api/v1/attributes?filter[meta:gibtesnicht]=x')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * "Alle N auswählen" muss dieselbe Treffermenge liefern wie die Liste —
     * sonst trifft die anschliessende Massenbearbeitung zu viele Attribute.
     */
    public function test_all_ids_beruecksichtigt_metadaten_filter(): void
    {
        $treffer = $this->attributeWith($this->herkunft, 'ERP');
        $this->attributeWith($this->herkunft, 'Agentur');
        $this->attributeWith($this->herkunft, null);

        $response = $this->postJson('/api/v1/attributes/all-ids', [
            'filter' => ['meta:datenherkunft' => 'ERP'],
        ])->assertOk();

        $this->assertSame([$treffer->id], $response->json('ids'));
    }

    public function test_all_ids_beruecksichtigt_ohne_wert_filter(): void
    {
        $this->attributeWith($this->herkunft, 'ERP');
        $ohneWert = $this->attributeWith($this->herkunft, null);

        $response = $this->postJson('/api/v1/attributes/all-ids', [
            'filter' => ['meta:datenherkunft' => '__none__'],
        ])->assertOk();

        $this->assertSame([$ohneWert->id], $response->json('ids'));
    }

    public function test_filter_auf_freitext_metadatum_trifft_per_praefix(): void
    {
        $treffer = $this->attributeWith($this->eigentuemer, 'Marketing Team');
        $this->attributeWith($this->eigentuemer, 'Produktmanagement');

        $this->getJson('/api/v1/attributes?filter[meta:dateneigentuemer]=Marke')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $treffer->id);
    }

    public function test_filter_auf_auswahl_metadatum_bleibt_exakt(): void
    {
        $this->attributeWith($this->herkunft, 'Agentur');

        // "Agent" ist ein Praefix von "Agentur", darf bei gepflegten
        // Auswahlwerten aber nicht treffen.
        $this->getJson('/api/v1/attributes?filter[meta:datenherkunft]=Agent')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Regressionsschutz gegen auseinanderlaufende Filter: was die Liste zeigt,
     * muss "Alle N auswaehlen" auch markieren.
     *
     * @dataProvider filterProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('filterProvider')]
    public function test_index_und_all_ids_liefern_dieselbe_menge(string $key, string $value): void
    {
        $this->attributeWith($this->herkunft, 'ERP');
        $this->attributeWith($this->herkunft, null);
        Attribute::factory()->create(['is_unique' => true, 'description_de' => 'Enthaelt Suchwort mittendrin']);
        Attribute::factory()->create(['is_multipliable' => true]);

        $listIds = collect($this->getJson("/api/v1/attributes?filter[{$key}]={$value}")->json('data'))
            ->pluck('id')->sort()->values()->all();

        $allIds = collect($this->postJson('/api/v1/attributes/all-ids', [
            'filter' => [$key => $value],
        ])->json('ids'))->sort()->values()->all();

        $this->assertSame($listIds, $allIds, "Filter {$key}={$value} liefert unterschiedliche Mengen.");
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function filterProvider(): array
    {
        return [
            'Metadatum exakt' => ['meta:datenherkunft', 'ERP'],
            'Metadatum ohne Wert' => ['meta:datenherkunft', '__none__'],
            'Einzigartig' => ['is_unique', '1'],
            'Multipliziert' => ['is_multipliable', '1'],
            'Beschreibung enthaelt' => ['description_de', 'Suchwort'],
            'Kind-Attribute' => ['has_children', '0'],
        ];
    }

    // ─── Massenbearbeitung ────────────────────────────────────────────

    public function test_bulk_update_setzt_metadaten(): void
    {
        $a = Attribute::factory()->create();
        $b = Attribute::factory()->create();

        $this->putJson('/api/v1/attributes/bulk-update', [
            'ids' => [$a->id, $b->id],
            'fields' => ['metadata' => ['datenherkunft' => 'Agentur']],
        ])->assertOk()
            ->assertJsonPath('updated', 2);

        foreach ([$a, $b] as $attribute) {
            $this->assertDatabaseHas('attribute_metadata_values', [
                'attribute_id' => $attribute->id,
                'definition_id' => $this->herkunft->id,
                'value' => 'Agentur',
            ]);
        }
    }

    public function test_bulk_update_ueberschreibt_bestehenden_wert(): void
    {
        $attribute = $this->attributeWith($this->herkunft, 'ERP');

        $this->putJson('/api/v1/attributes/bulk-update', [
            'ids' => [$attribute->id],
            'fields' => ['metadata' => ['datenherkunft' => 'Marketing']],
        ])->assertOk();

        $this->assertSame(
            1,
            AttributeMetadataValue::where('attribute_id', $attribute->id)->count()
        );
        $this->assertDatabaseHas('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
            'value' => 'Marketing',
        ]);
    }

    public function test_bulk_update_mit_leerem_wert_loescht_metadaten(): void
    {
        $attribute = $this->attributeWith($this->eigentuemer, 'Produktmanagement');

        $this->putJson('/api/v1/attributes/bulk-update', [
            'ids' => [$attribute->id],
            'fields' => ['metadata' => ['dateneigentuemer' => '']],
        ])->assertOk();

        $this->assertDatabaseMissing('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
        ]);
    }

    public function test_bulk_update_kombiniert_spalten_und_metadaten(): void
    {
        $attribute = Attribute::factory()->create(['is_searchable' => false]);

        $this->putJson('/api/v1/attributes/bulk-update', [
            'ids' => [$attribute->id],
            'fields' => [
                'is_searchable' => true,
                'metadata' => ['datenherkunft' => 'ERP'],
            ],
        ])->assertOk();

        $this->assertTrue($attribute->fresh()->is_searchable);
        $this->assertDatabaseHas('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
            'value' => 'ERP',
        ]);
    }

    public function test_bulk_update_lehnt_ungueltigen_metadatenwert_ab(): void
    {
        $attribute = $this->attributeWith($this->herkunft, 'ERP');

        $this->putJson('/api/v1/attributes/bulk-update', [
            'ids' => [$attribute->id],
            'fields' => ['metadata' => ['datenherkunft' => 'Zauberei']],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['fields.metadata.datenherkunft']);

        // Bestehender Wert bleibt unangetastet
        $this->assertDatabaseHas('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
            'value' => 'ERP',
        ]);
    }

    /**
     * `false` ist eine echte Angabe und bleibt erhalten; nur der leere String
     * entfernt ein Ja/Nein-Metadatum ("— Wert entfernen —" im Bulk-Dialog).
     */
    public function test_boolean_false_wird_gespeichert(): void
    {
        AttributeMetadataDefinition::factory()->create([
            'technical_name' => 'geprueft',
            'name_de' => 'Geprüft',
            'value_type' => 'boolean',
        ]);

        $attribute = Attribute::factory()->create();

        $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'metadata' => ['geprueft' => false],
        ])->assertOk();

        $this->getJson("/api/v1/attributes/{$attribute->id}?include=metadataValues")
            ->assertOk()
            ->assertJsonPath('data.metadata.geprueft', false);
    }

    /**
     * Direkt am Service, nicht über HTTP: Laravels globales
     * ConvertEmptyStringsToNull macht aus '' im Request ohnehin null. Ein
     * Aufrufer ohne diese Middleware (Importer, Konsolenbefehl) muss aber
     * dieselbe Semantik bekommen — leer heisst löschen, auch bei Ja/Nein.
     */
    public function test_service_loescht_boolean_bei_leerem_string(): void
    {
        $definition = AttributeMetadataDefinition::factory()->create([
            'technical_name' => 'geprueft',
            'name_de' => 'Geprüft',
            'value_type' => 'boolean',
        ]);

        $attribute = $this->attributeWith($definition, '1');

        app(AttributeMetadataService::class)->sync($attribute, ['geprueft' => '']);

        $this->assertDatabaseMissing('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
            'definition_id' => $definition->id,
        ]);
    }

    public function test_service_speichert_boolean_false_statt_zu_loeschen(): void
    {
        $definition = AttributeMetadataDefinition::factory()->create([
            'technical_name' => 'geprueft',
            'name_de' => 'Geprüft',
            'value_type' => 'boolean',
        ]);

        $attribute = Attribute::factory()->create();

        app(AttributeMetadataService::class)->sync($attribute, ['geprueft' => false]);

        $this->assertDatabaseHas('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
            'definition_id' => $definition->id,
            'value' => '0',
        ]);
    }

    public function test_bulk_update_entfernt_boolean_metadatum(): void
    {
        $definition = AttributeMetadataDefinition::factory()->create([
            'technical_name' => 'geprueft',
            'name_de' => 'Geprüft',
            'value_type' => 'boolean',
        ]);

        $attribute = $this->attributeWith($definition, '1');

        $this->putJson('/api/v1/attributes/bulk-update', [
            'ids' => [$attribute->id],
            'fields' => ['metadata' => ['geprueft' => '']],
        ])->assertOk();

        $this->assertDatabaseMissing('attribute_metadata_values', [
            'attribute_id' => $attribute->id,
        ]);
    }

    public function test_bulk_update_ohne_felder_gibt_422(): void
    {
        $attribute = Attribute::factory()->create();

        $this->putJson('/api/v1/attributes/bulk-update', [
            'ids' => [$attribute->id],
            'fields' => ['nicht_erlaubt' => 'x'],
        ])->assertUnprocessable();
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
