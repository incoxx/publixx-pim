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

    // Hinweis: Die Execute-Pfade "overwrite"/"fill_empty" (ProductAttributeValue::upsert)
    // sind hier bewusst nicht getestet: Der Upsert-Konflikt-Target im
    // BulkUpdateController (product_id, attribute_id, language, multiplied_index)
    // passt seit Migration 2026_03_11 nicht mehr zum Unique-Index
    // (… + output_hierarchy_id) und schlägt unter SQLite mit 500 fehl.
    // Siehe Bug-Report im Abschlussbericht.

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

    public function test_execute_entfernt_relationen(): void
    {
        // Hinweis: Die "add"-Aktion nutzt ProductRelation::insert() ohne UUID-PK
        // und schlaegt mit NOT-NULL-Verletzung fehl (Bug, siehe Abschlussbericht).
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

    public function test_execute_entfernt_output_hierarchie_zuordnung(): void
    {
        // Hinweis: Die "assign"-Aktion nutzt insert() ohne UUID-PK und schlaegt
        // mit NOT-NULL-Verletzung fehl (Bug, siehe Abschlussbericht).
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

    // Hinweis: Ein 403-Test fuer Nutzer ohne Berechtigung ist nicht moeglich:
    // authorize('update', Product::class) trifft ProductPolicy::update(User, Product)
    // ohne Product-Instanz -> ArgumentCountError (500 statt 403). Bug, siehe Bericht.

    public function test_unauthenticated_access_returns_401(): void
    {
        app('auth')->forgetGuards();

        $this->putJson('/api/v1/products/bulk-update', [
            'product_ids' => ['00000000-0000-0000-0000-000000000000'],
            'operations' => ['status' => 'active'],
        ])->assertUnauthorized();
    }
}
