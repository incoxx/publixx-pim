<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Role;
use App\Models\User;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests für die Merkmalsachsen-Endpunkte:
 *   GET  /api/v1/products/{product}/variant-axes
 *   PUT  /api/v1/products/{product}/variant-axes
 *   GET  /api/v1/products/{product}/variant-matrix
 * sowie die Eindeutigkeitsprüfung beim Anlegen/Bearbeiten von Varianten.
 */
class ProductVariantAxisControllerTest extends TestCase
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

    private function variantAttribute(): Attribute
    {
        // data_type explizit fixieren — AttributeFactory würfelt sonst einen
        // zufälligen Typ, was die value_string-Annahmen unten unterlaufen würde.
        return Attribute::factory()->create(['is_variant_attribute' => true, 'data_type' => 'String']);
    }

    public function test_axes_koennen_gesetzt_und_gelesen_werden(): void
    {
        $parent = Product::factory()->create();
        $attr = $this->variantAttribute();

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$attr->id],
        ])->assertOk()->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/products/{$parent->id}/variant-axes")
            ->assertOk()
            ->assertJsonPath('data.0.attribute_id', $attr->id);
    }

    public function test_axes_lehnt_attribut_ohne_is_variant_attribute_ab(): void
    {
        $parent = Product::factory()->create();
        $attr = Attribute::factory()->create(['is_variant_attribute' => false]);

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$attr->id],
        ])->assertStatus(422);
    }

    public function test_variante_mit_doppelter_merkmalskombination_wird_bei_erstellung_abgelehnt(): void
    {
        $parent = Product::factory()->create();
        $attr = $this->variantAttribute();

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$attr->id],
        ])->assertOk();

        $this->postJson("/api/v1/products/{$parent->id}/variants", [
            'sku' => 'VAR-1',
            'name' => 'Variante Rot',
            'axis_values' => [$attr->id => 'Rot'],
        ])->assertCreated();

        $this->postJson("/api/v1/products/{$parent->id}/variants", [
            'sku' => 'VAR-2',
            'name' => 'Variante Rot 2',
            'axis_values' => [$attr->id => 'Rot'],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('products', ['sku' => 'VAR-2']);
    }

    public function test_variante_mit_unterschiedlicher_kombination_wird_erstellt(): void
    {
        $parent = Product::factory()->create();
        $attr = $this->variantAttribute();

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$attr->id],
        ])->assertOk();

        $this->postJson("/api/v1/products/{$parent->id}/variants", [
            'sku' => 'VAR-1',
            'name' => 'Variante Rot',
            'axis_values' => [$attr->id => 'Rot'],
        ])->assertCreated();

        $this->postJson("/api/v1/products/{$parent->id}/variants", [
            'sku' => 'VAR-2',
            'name' => 'Variante Gruen',
            'axis_values' => [$attr->id => 'Gruen'],
        ])->assertCreated();

        $this->assertDatabaseHas('variant_inheritance_rules', [
            'product_id' => Product::where('sku', 'VAR-1')->value('id'),
            'attribute_id' => $attr->id,
            'inheritance_mode' => 'override',
        ]);
    }

    public function test_bulk_update_lehnt_doppelte_kombination_bei_bestehender_variante_ab(): void
    {
        $parent = Product::factory()->create();
        $attr = $this->variantAttribute();
        $v1 = Product::factory()->variant()->create(['parent_product_id' => $parent->id]);
        $v2 = Product::factory()->variant()->create(['parent_product_id' => $parent->id]);

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$attr->id],
        ])->assertOk();

        ProductAttributeValue::create([
            'product_id' => $v1->id,
            'attribute_id' => $attr->id,
            'value_string' => 'Rot',
            'multiplied_index' => 0,
            'is_inherited' => false,
        ]);

        $this->putJson("/api/v1/products/{$v2->id}/attribute-values", [
            'values' => [
                ['attribute_id' => $attr->id, 'value' => 'Rot'],
            ],
        ])->assertStatus(422);
    }

    // -----------------------------------------------------------------------
    // Selection-Achsen (Werteliste): Entry-ID vs. Anzeigetext, ungültiger Wert
    // -----------------------------------------------------------------------

    private function colorAttribute(): array
    {
        $valueList = ValueList::factory()->create();
        $black = ValueListEntry::factory()->create([
            'value_list_id' => $valueList->id,
            'technical_name' => 'schwarz',
            'display_value_de' => 'Schwarz',
        ]);
        $attr = Attribute::factory()->create([
            'is_variant_attribute' => true,
            'data_type' => 'Selection',
            'value_list_id' => $valueList->id,
        ]);

        return [$attr, $black];
    }

    public function test_variante_mit_selection_achse_per_entry_id(): void
    {
        $parent = Product::factory()->create();
        [$attr, $black] = $this->colorAttribute();

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$attr->id],
        ])->assertOk();

        // Manuelles Formular sendet die Entry-ID (so wie die Select-Option es tut).
        $this->postJson("/api/v1/products/{$parent->id}/variants", [
            'sku' => 'VAR-SCHWARZ',
            'name' => 'Variante Schwarz',
            'axis_values' => [$attr->id => $black->id],
        ])->assertCreated();

        $this->assertDatabaseHas('product_attribute_values', [
            'attribute_id' => $attr->id,
            'value_selection_id' => $black->id,
        ]);
    }

    public function test_variante_mit_selection_achse_per_anzeigetext(): void
    {
        $parent = Product::factory()->create();
        [$attr, $black] = $this->colorAttribute();

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$attr->id],
        ])->assertOk();

        // Variantengenerator sendet historisch den Anzeigetext statt der ID —
        // muss trotzdem auf den passenden Werteliste-Eintrag aufgelöst werden.
        $this->postJson("/api/v1/products/{$parent->id}/variants/generate", [
            'dimensions' => [
                ['attribute_id' => $attr->id, 'values' => ['Schwarz']],
            ],
        ])->assertOk()->assertJsonPath('created', 1);

        $this->assertDatabaseHas('product_attribute_values', [
            'attribute_id' => $attr->id,
            'value_selection_id' => $black->id,
        ]);
    }

    public function test_generate_ueberspringt_ungueltigen_selection_wert_statt_abzustuerzen(): void
    {
        $parent = Product::factory()->create();
        [$attr] = $this->colorAttribute();

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$attr->id],
        ])->assertOk();

        $this->postJson("/api/v1/products/{$parent->id}/variants/generate", [
            'dimensions' => [
                ['attribute_id' => $attr->id, 'values' => ['Nicht-Existent']],
            ],
        ])->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('skipped', 1);
    }

    public function test_matrix_liefert_spalten_und_zeilen(): void
    {
        $parent = Product::factory()->create();
        $attr = $this->variantAttribute();
        $variant = Product::factory()->variant()->create(['parent_product_id' => $parent->id]);

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$attr->id],
        ])->assertOk();

        ProductAttributeValue::create([
            'product_id' => $variant->id,
            'attribute_id' => $attr->id,
            'value_string' => 'Rot',
            'multiplied_index' => 0,
            'is_inherited' => false,
        ]);

        $this->getJson("/api/v1/products/{$parent->id}/variant-matrix")
            ->assertOk()
            ->assertJsonPath('columns.0.attribute_id', $attr->id)
            ->assertJsonPath('rows.0.axis_values.' . $attr->id, 'Rot');
    }

    // -----------------------------------------------------------------------
    // Number/Flag-Achsen: axis_values darf nicht auf "string" beschränkt sein
    // -----------------------------------------------------------------------

    public function test_variante_mit_number_achse_akzeptiert_numerischen_wert(): void
    {
        $parent = Product::factory()->create();
        $attr = Attribute::factory()->create(['is_variant_attribute' => true, 'data_type' => 'Number']);

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$attr->id],
        ])->assertOk();

        // Das Frontend sendet für Number-Achsen einen JSON-Zahlenwert, keinen String.
        $this->postJson("/api/v1/products/{$parent->id}/variants", [
            'sku' => 'VAR-5MM',
            'name' => 'Variante 5mm',
            'axis_values' => [$attr->id => 5],
        ])->assertCreated();

        $this->assertDatabaseHas('product_attribute_values', [
            'attribute_id' => $attr->id,
            'value_number' => 5,
        ]);
    }

    public function test_variante_mit_flag_achse_akzeptiert_boolean_wert(): void
    {
        $parent = Product::factory()->create();
        $attr = Attribute::factory()->create(['is_variant_attribute' => true, 'data_type' => 'Flag']);

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$attr->id],
        ])->assertOk();

        $this->postJson("/api/v1/products/{$parent->id}/variants", [
            'sku' => 'VAR-FLAG',
            'name' => 'Variante Flag',
            'axis_values' => [$attr->id => true],
        ])->assertCreated();

        $this->assertDatabaseHas('product_attribute_values', [
            'attribute_id' => $attr->id,
            'value_flag' => true,
        ]);
    }

    // -----------------------------------------------------------------------
    // updateRules() darf den Achsen-Guard nicht umgehen
    // -----------------------------------------------------------------------

    public function test_variant_rules_endpoint_lehnt_inherit_fuer_achsen_attribut_ab(): void
    {
        $parent = Product::factory()->create();
        $variant = Product::factory()->variant()->create(['parent_product_id' => $parent->id]);
        $attr = Attribute::factory()->create(['is_variant_attribute' => true, 'data_type' => 'String']);

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$attr->id],
        ])->assertOk();

        $this->putJson("/api/v1/products/{$variant->id}/variant-rules", [
            'rules' => [
                ['attribute_id' => $attr->id, 'inheritance_mode' => 'inherit'],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseHas('variant_inheritance_rules', [
            'product_id' => $variant->id,
            'attribute_id' => $attr->id,
            'inheritance_mode' => 'override',
        ]);
    }

    public function test_variant_rules_endpoint_lehnt_produkt_ohne_elternprodukt_mit_422_statt_serverfehler_ab(): void
    {
        $parent = Product::factory()->create();

        $this->putJson("/api/v1/products/{$parent->id}/variant-rules", [
            'rules' => [
                ['attribute_id' => Attribute::factory()->create()->id, 'inheritance_mode' => 'override'],
            ],
        ])->assertStatus(422);
    }

    public function test_variant_rules_endpoint_erzwingt_achsen_override_auch_wenn_nicht_mitgeschickt(): void
    {
        $parent = Product::factory()->create();
        $variant = Product::factory()->variant()->create(['parent_product_id' => $parent->id]);
        $axisAttr = Attribute::factory()->create(['is_variant_attribute' => true, 'data_type' => 'String']);
        $otherAttr = Attribute::factory()->create(['data_type' => 'String']);

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$axisAttr->id],
        ])->assertOk();

        $this->assertDatabaseHas('variant_inheritance_rules', [
            'product_id' => $variant->id,
            'attribute_id' => $axisAttr->id,
            'inheritance_mode' => 'override',
        ]);

        // Regel-Liste schickt das Achsen-Attribut absichtlich nicht mit — die
        // erzwungene override-Regel darf trotzdem nicht verloren gehen.
        $this->putJson("/api/v1/products/{$variant->id}/variant-rules", [
            'rules' => [
                ['attribute_id' => $otherAttr->id, 'inheritance_mode' => 'inherit'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('variant_inheritance_rules', [
            'product_id' => $variant->id,
            'attribute_id' => $axisAttr->id,
            'inheritance_mode' => 'override',
        ]);
    }

    // -----------------------------------------------------------------------
    // bulkUpdate (allgemeiner Attribut-Editor) muss override-Regel nachziehen
    // -----------------------------------------------------------------------

    public function test_bulk_update_setzt_override_regel_fuer_geaenderten_achsen_wert(): void
    {
        $parent = Product::factory()->create();
        $variant = Product::factory()->variant()->create(['parent_product_id' => $parent->id]);
        $attr = Attribute::factory()->create(['is_variant_attribute' => true, 'data_type' => 'String']);

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$attr->id],
        ])->assertOk();

        $this->putJson("/api/v1/products/{$variant->id}/attribute-values", [
            'values' => [
                ['attribute_id' => $attr->id, 'value' => 'Rot'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('variant_inheritance_rules', [
            'product_id' => $variant->id,
            'attribute_id' => $attr->id,
            'inheritance_mode' => 'override',
        ]);
    }
}
