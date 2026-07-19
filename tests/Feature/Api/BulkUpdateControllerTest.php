<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductRelationType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature-Tests für die Massendatenpflege (BulkUpdateController).
 *
 * POST /products/common-attributes  → gemeinsame Attribute ermitteln
 * POST /products/bulk-update/preview → Dry-Run-Zusammenfassung
 * PUT  /products/bulk-update         → Änderungen ausführen
 */
class BulkUpdateControllerTest extends TestCase
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

        // Such-Index-Jobs und Composite-Events nicht synchron ausführen
        Queue::fake();
        Event::fake([\App\Events\AttributeValuesChanged::class]);
    }

    // ── POST /products/common-attributes ─────────────────────────────

    public function test_common_attributes_ohne_master_hierarchie_liefert_warnung(): void
    {
        $products = Product::factory()->count(2)->create(['master_hierarchy_node_id' => null]);

        $response = $this->postJson('/api/v1/products/common-attributes', [
            'product_ids' => $products->pluck('id')->all(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('warning', 'Eines oder mehrere Produkte haben keine Master-Hierarchie-Zuordnung.');
    }

    public function test_common_attributes_validiert_eingabe(): void
    {
        $this->postJson('/api/v1/products/common-attributes', [
            'product_ids' => 'keine-liste',
        ])->assertUnprocessable();
    }

    // ── POST /products/bulk-update/preview ───────────────────────────

    public function test_preview_liefert_zusammenfassung_ohne_aenderungen(): void
    {
        $products = Product::factory()->count(2)->create();
        $attribute = Attribute::factory()->create(['data_type' => 'String']);

        $response = $this->postJson('/api/v1/products/bulk-update/preview', [
            'product_ids' => $products->pluck('id')->all(),
            'operations' => [
                'attributes' => [
                    ['attribute_id' => $attribute->id, 'value' => 'Neu', 'mode' => 'overwrite'],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('summary.total_products', 2)
            ->assertJsonPath('summary.attributes.updated', 2)
            ->assertJsonPath('summary.attributes.skipped', 0);

        // Dry-Run: keine Werte geschrieben
        $this->assertDatabaseCount('product_attribute_values', 0);
    }

    public function test_preview_validiert_operationen(): void
    {
        $product = Product::factory()->create();

        // operations fehlt komplett → 422
        $this->postJson('/api/v1/products/bulk-update/preview', [
            'product_ids' => [$product->id],
        ])->assertUnprocessable();

        // Ungültiger Modus → 422
        $attribute = Attribute::factory()->create(['data_type' => 'String']);
        $this->postJson('/api/v1/products/bulk-update/preview', [
            'product_ids' => [$product->id],
            'operations' => [
                'attributes' => [
                    ['attribute_id' => $attribute->id, 'value' => 'x', 'mode' => 'ungueltig'],
                ],
            ],
        ])->assertUnprocessable();
    }

    public function test_preview_erfordert_product_ids_oder_filter(): void
    {
        $this->postJson('/api/v1/products/bulk-update/preview', [
            'operations' => ['status' => 'active'],
        ])->assertUnprocessable();
    }

    // ── PUT /products/bulk-update ────────────────────────────────────

    public function test_execute_overwrite_setzt_attributwert(): void
    {
        $attribute = Attribute::factory()->create(['data_type' => 'String']);
        $leeresProdukt = Product::factory()->create();
        $gefuelltesProdukt = Product::factory()->create();

        // Bestehender Wert, der überschrieben werden soll
        ProductAttributeValue::factory()->create([
            'product_id' => $gefuelltesProdukt->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Alt',
        ]);

        $response = $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => [$leeresProdukt->id, $gefuelltesProdukt->id],
            'operations' => [
                'attributes' => [
                    ['attribute_id' => $attribute->id, 'value' => 'Neu', 'mode' => 'overwrite'],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.attributes.updated', 2)
            ->assertJsonPath('results.attributes.skipped', 0);

        // Beide Produkte tragen jetzt den neuen Wert
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $leeresProdukt->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Neu',
        ]);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $gefuelltesProdukt->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Neu',
        ]);
    }

    public function test_execute_overwrite_loest_selection_wert_gegen_werteliste_auf(): void
    {
        $valueList = \App\Models\ValueList::factory()->create();
        $entry = \App\Models\ValueListEntry::factory()->create([
            'value_list_id' => $valueList->id,
            'technical_name' => 'rot',
            'display_value_de' => 'Rot',
        ]);
        $attribute = Attribute::factory()->create(['data_type' => 'Selection', 'value_list_id' => $valueList->id]);
        $product = Product::factory()->create();

        // Anzeigetext statt Entry-ID — muss aufgelöst werden statt in
        // value_selection_id zu landen (FK gegen value_list_entries).
        $response = $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => [$product->id],
            'operations' => [
                'attributes' => [
                    ['attribute_id' => $attribute->id, 'value' => 'Rot', 'mode' => 'overwrite'],
                ],
            ],
        ]);

        $response->assertOk()->assertJsonPath('results.attributes.updated', 1);

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_selection_id' => $entry->id,
        ]);
    }

    public function test_execute_overwrite_lehnt_ungueltigen_selection_wert_ab(): void
    {
        $valueList = \App\Models\ValueList::factory()->create();
        $attribute = Attribute::factory()->create(['data_type' => 'Selection', 'value_list_id' => $valueList->id]);
        $product = Product::factory()->create();

        $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => [$product->id],
            'operations' => [
                'attributes' => [
                    ['attribute_id' => $attribute->id, 'value' => 'Nicht-Existent', 'mode' => 'overwrite'],
                ],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('product_attribute_values', [
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
        ]);
    }

    public function test_execute_overwrite_ignoriert_inaktiven_werteliste_eintrag(): void
    {
        $valueList = \App\Models\ValueList::factory()->create();
        \App\Models\ValueListEntry::factory()->create([
            'value_list_id' => $valueList->id,
            'technical_name' => 'veraltet',
            'display_value_de' => 'Veraltet',
            'is_active' => false,
        ]);
        $attribute = Attribute::factory()->create(['data_type' => 'Selection', 'value_list_id' => $valueList->id]);
        $product = Product::factory()->create();

        $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => [$product->id],
            'operations' => [
                'attributes' => [
                    ['attribute_id' => $attribute->id, 'value' => 'Veraltet', 'mode' => 'overwrite'],
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_execute_fill_empty_fuellt_nur_leere_werte(): void
    {
        $attribute = Attribute::factory()->create(['data_type' => 'String']);
        $leeresProdukt = Product::factory()->create();
        $gefuelltesProdukt = Product::factory()->create();

        ProductAttributeValue::factory()->create([
            'product_id' => $gefuelltesProdukt->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Bestand',
        ]);

        $response = $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => [$leeresProdukt->id, $gefuelltesProdukt->id],
            'operations' => [
                'attributes' => [
                    ['attribute_id' => $attribute->id, 'value' => 'Neu', 'mode' => 'fill_empty'],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.attributes.updated', 1)
            ->assertJsonPath('results.attributes.skipped', 1);

        // Leeres Produkt wurde gefüllt …
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $leeresProdukt->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Neu',
        ]);

        // … bestehender Wert bleibt unangetastet
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $gefuelltesProdukt->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Bestand',
        ]);
        $this->assertDatabaseMissing('product_attribute_values', [
            'product_id' => $gefuelltesProdukt->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Neu',
        ]);
    }

    public function test_execute_clear_loescht_werte(): void
    {
        $attribute = Attribute::factory()->create(['data_type' => 'String']);
        $product = Product::factory()->create();

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Wegdamit',
        ]);

        $response = $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => [$product->id],
            'operations' => [
                'attributes' => [
                    ['attribute_id' => $attribute->id, 'value' => null, 'mode' => 'clear'],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.attributes.updated', 1);

        $this->assertDatabaseMissing('product_attribute_values', [
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
        ]);
    }

    public function test_execute_setzt_status(): void
    {
        $products = Product::factory()->count(2)->create(['status' => 'draft']);
        $alreadyActive = Product::factory()->active()->create();

        $ids = $products->pluck('id')->push($alreadyActive->id)->all();

        $response = $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => $ids,
            'operations' => ['status' => 'active'],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.status.would_change', 2)
            ->assertJsonPath('results.status.already_target', 1);

        foreach ($ids as $id) {
            $this->assertDatabaseHas('products', ['id' => $id, 'status' => 'active']);
        }
    }

    public function test_execute_legt_relationen_an(): void
    {
        $target = Product::factory()->create();
        $relationType = ProductRelationType::factory()->create();
        $sources = Product::factory()->count(2)->create();

        $response = $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => $sources->pluck('id')->all(),
            'operations' => [
                'relations' => [
                    [
                        'relation_type_id' => $relationType->id,
                        'target_product_id' => $target->id,
                        'action' => 'add',
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.relations.added', 2);

        foreach ($sources as $source) {
            $this->assertDatabaseHas('product_relations', [
                'source_product_id' => $source->id,
                'target_product_id' => $target->id,
                'relation_type_id' => $relationType->id,
            ]);
        }
        $this->assertDatabaseCount('product_relations', 2);
    }

    public function test_execute_respektiert_produkttyp_einschraenkung_bei_relationen(): void
    {
        $allowedType = \App\Models\ProductType::factory()->create();
        $disallowedType = \App\Models\ProductType::factory()->create();
        $target = Product::factory()->create();
        $relationType = ProductRelationType::factory()->create([
            'allowed_source_product_type_ids' => [$allowedType->id],
        ]);
        $allowedSource = Product::factory()->create(['product_type_id' => $allowedType->id]);
        $disallowedSource = Product::factory()->create(['product_type_id' => $disallowedType->id]);

        $response = $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => [$allowedSource->id, $disallowedSource->id],
            'operations' => [
                'relations' => [
                    [
                        'relation_type_id' => $relationType->id,
                        'target_product_id' => $target->id,
                        'action' => 'add',
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.relations.added', 1)
            ->assertJsonPath('results.relations.restricted', 1);

        $this->assertDatabaseHas('product_relations', [
            'source_product_id' => $allowedSource->id,
            'target_product_id' => $target->id,
        ]);
        $this->assertDatabaseMissing('product_relations', [
            'source_product_id' => $disallowedSource->id,
            'target_product_id' => $target->id,
        ]);
    }

    public function test_execute_entfernt_relationen(): void
    {
        $target = Product::factory()->create();
        $relationType = ProductRelationType::factory()->create();
        $sources = Product::factory()->count(2)->create();

        foreach ($sources as $source) {
            \App\Models\ProductRelation::factory()->create([
                'source_product_id' => $source->id,
                'target_product_id' => $target->id,
                'relation_type_id' => $relationType->id,
            ]);
        }

        $response = $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => $sources->pluck('id')->all(),
            'operations' => [
                'relations' => [
                    [
                        'relation_type_id' => $relationType->id,
                        'target_product_id' => $target->id,
                        'action' => 'remove',
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.relations.removed', 2);

        $this->assertDatabaseCount('product_relations', 0);
    }

    public function test_execute_weist_output_hierarchie_zu(): void
    {
        $products = Product::factory()->count(2)->create();
        $hierarchy = Hierarchy::factory()->output()->create();
        $node = HierarchyNode::factory()->create(['hierarchy_id' => $hierarchy->id]);

        $response = $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => $products->pluck('id')->all(),
            'operations' => [
                'output_hierarchy' => [
                    ['hierarchy_node_id' => $node->id, 'action' => 'assign'],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.output_hierarchy.assigned', 2);

        foreach ($products as $product) {
            $this->assertDatabaseHas('output_hierarchy_product_assignments', [
                'hierarchy_node_id' => $node->id,
                'product_id' => $product->id,
            ]);
        }
        $this->assertDatabaseCount('output_hierarchy_product_assignments', 2);
    }

    public function test_execute_entfernt_output_hierarchie_zuordnung(): void
    {
        $products = Product::factory()->count(2)->create();
        $hierarchy = Hierarchy::factory()->output()->create();
        $node = HierarchyNode::factory()->create(['hierarchy_id' => $hierarchy->id]);

        foreach ($products as $product) {
            \App\Models\OutputHierarchyProductAssignment::create([
                'hierarchy_node_id' => $node->id,
                'product_id' => $product->id,
                'sort_order' => 0,
            ]);
        }

        $response = $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => $products->pluck('id')->all(),
            'operations' => [
                'output_hierarchy' => [
                    ['hierarchy_node_id' => $node->id, 'action' => 'remove'],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.output_hierarchy.removed', 2);

        $this->assertDatabaseCount('output_hierarchy_product_assignments', 0);
    }

    public function test_execute_setzt_master_hierarchie(): void
    {
        $products = Product::factory()->count(2)->create(['master_hierarchy_node_id' => null]);
        $node = HierarchyNode::factory()->create();

        $response = $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => $products->pluck('id')->all(),
            'operations' => ['master_hierarchy_node_id' => $node->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.master_hierarchy.would_change', 2);

        foreach ($products as $product) {
            $this->assertDatabaseHas('products', [
                'id' => $product->id,
                'master_hierarchy_node_id' => $node->id,
            ]);
        }
    }

    public function test_execute_weist_medien_zu(): void
    {
        $products = Product::factory()->count(2)->create();
        $media = \App\Models\Media::factory()->create();

        $response = $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => $products->pluck('id')->all(),
            'operations' => [
                'media' => [
                    ['media_id' => $media->id, 'action' => 'assign'],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.media.assigned', 2);

        foreach ($products as $product) {
            $this->assertDatabaseHas('product_media_assignments', [
                'product_id' => $product->id,
                'media_id' => $media->id,
            ]);
        }
        $this->assertDatabaseCount('product_media_assignments', 2);
    }

    // ── Autorisierung ────────────────────────────────────────────────

    public function test_nicht_admin_ohne_permission_erhaelt_403(): void
    {
        // Nutzer ohne Admin-Rolle und ohne products.edit-Permission → 403 (nicht 500)
        $unberechtigt = User::factory()->create();
        $this->actingAs($unberechtigt);

        $product = Product::factory()->create();

        $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => [$product->id],
            'operations' => ['status' => 'active'],
        ])->assertForbidden();

        $this->postJson('/api/v1/products/bulk-update/preview', [
            'product_ids' => [$product->id],
            'operations' => ['status' => 'active'],
        ])->assertForbidden();

        $this->postJson('/api/v1/products/common-attributes', [
            'product_ids' => [$product->id],
        ])->assertForbidden();
    }

    public function test_unauthenticated_access_returns_401(): void
    {
        app('auth')->forgetGuards();

        $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => ['00000000-0000-0000-0000-000000000000'],
            'operations' => ['status' => 'active'],
        ])->assertUnauthorized();
    }
}
