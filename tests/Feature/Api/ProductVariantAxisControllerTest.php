<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Role;
use App\Models\User;
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
}
