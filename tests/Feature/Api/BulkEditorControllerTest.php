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
}
