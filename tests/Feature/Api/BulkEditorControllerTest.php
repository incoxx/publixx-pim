<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-Tests für den Bulk-Attribut-Editor (BulkEditorController).
 *
 * PUT /products/bulk-edit → Änderungen über mehrere Produkte × Attribute speichern
 */
class BulkEditorControllerTest extends TestCase
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

    public function test_save_loest_selection_wert_gegen_werteliste_auf(): void
    {
        $valueList = ValueList::factory()->create();
        $entry = ValueListEntry::factory()->create([
            'value_list_id' => $valueList->id,
            'technical_name' => 'rot',
            'display_value_de' => 'Rot',
        ]);
        $attribute = Attribute::factory()->create(['data_type' => 'Selection', 'value_list_id' => $valueList->id]);
        $product = Product::factory()->create();

        $response = $this->putJson('/api/v1/products/bulk-edit', [
            'changes' => [
                ['product_id' => $product->id, 'attribute_id' => $attribute->id, 'value' => 'Rot'],
            ],
        ]);

        $response->assertOk()->assertJsonPath('updated', 1)->assertJsonPath('errors', []);

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_selection_id' => $entry->id,
        ]);
    }

    public function test_save_sammelt_fehler_fuer_ungueltigen_selection_wert_statt_abzustuerzen(): void
    {
        $valueList = ValueList::factory()->create();
        $attribute = Attribute::factory()->create(['data_type' => 'Selection', 'value_list_id' => $valueList->id]);
        $ok = Product::factory()->create();
        $bad = Product::factory()->create();
        $okAttribute = Attribute::factory()->create(['data_type' => 'String']);

        $response = $this->putJson('/api/v1/products/bulk-edit', [
            'changes' => [
                ['product_id' => $bad->id, 'attribute_id' => $attribute->id, 'value' => 'Nicht-Existent'],
                ['product_id' => $ok->id, 'attribute_id' => $okAttribute->id, 'value' => 'Text'],
            ],
        ]);

        // Ein ungültiger Selection-Wert bricht nicht den gesamten Bulk-Edit ab —
        // andere Änderungen werden trotzdem gespeichert, der Fehler gesammelt.
        $response->assertOk()->assertJsonPath('updated', 1);
        $this->assertNotEmpty($response->json('errors'));

        $this->assertDatabaseMissing('product_attribute_values', [
            'product_id' => $bad->id,
            'attribute_id' => $attribute->id,
        ]);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $ok->id,
            'attribute_id' => $okAttribute->id,
            'value_string' => 'Text',
        ]);
    }

    public function test_save_lehnt_doppelte_achsen_kombination_zwischen_geschwistern_ab(): void
    {
        $parent = Product::factory()->create();
        $axisAttr = Attribute::factory()->create(['is_variant_attribute' => true, 'data_type' => 'String']);
        $variantA = Product::factory()->variant()->create(['parent_product_id' => $parent->id]);
        $variantB = Product::factory()->variant()->create(['parent_product_id' => $parent->id]);

        $this->putJson("/api/v1/products/{$parent->id}/variant-axes", [
            'attribute_ids' => [$axisAttr->id],
        ])->assertOk();

        $this->putJson("/api/v1/products/{$variantA->id}/attribute-values", [
            'values' => [['attribute_id' => $axisAttr->id, 'value' => 'Rot']],
        ])->assertOk();

        // Der Bulk-Grid-Editor darf hier keine identische Achsen-Kombination
        // wie bei $variantA erzeugen können — genau das soll die Prüfung
        // verhindern, die dieser Test gegen den Bulk-Editor-Pfad absichert.
        $response = $this->putJson('/api/v1/products/bulk-edit', [
            'changes' => [
                ['product_id' => $variantB->id, 'attribute_id' => $axisAttr->id, 'value' => 'Rot'],
            ],
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('errors'));

        $this->assertDatabaseMissing('product_attribute_values', [
            'product_id' => $variantB->id,
            'attribute_id' => $axisAttr->id,
            'value_string' => 'Rot',
        ]);
    }
}
