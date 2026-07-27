<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeView;
use App\Models\AttributeViewAssignment;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeViewControllerTest extends TestCase
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

    // ─── Index ────────────────────────────────────────────────

    public function test_index_gibt_paginierte_views_zurueck(): void
    {
        AttributeView::factory()->count(4)->create();

        $response = $this->getJson('/api/v1/attribute-views');

        $response->assertOk()
            ->assertJsonCount(4, 'data');
    }

    // ─── Store ────────────────────────────────────────────────

    public function test_store_erstellt_neuen_view(): void
    {
        $response = $this->postJson('/api/v1/attribute-views', [
            'technical_name' => 'produkt-standard',
            'name_de' => 'Produktstandard',
            'name_en' => 'Product Standard',
            'sort_order' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'produkt-standard');

        $this->assertDatabaseHas('attribute_views', ['technical_name' => 'produkt-standard']);
    }

    public function test_store_validiert_eindeutigen_technical_name(): void
    {
        AttributeView::factory()->create(['technical_name' => 'existiert-bereits']);

        $response = $this->postJson('/api/v1/attribute-views', [
            'technical_name' => 'existiert-bereits',
            'name_de' => 'Dupliziert',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name']);
    }

    // ─── Show ─────────────────────────────────────────────────

    public function test_show_gibt_einzelnen_view_zurueck(): void
    {
        $view = AttributeView::factory()->create();

        $response = $this->getJson("/api/v1/attribute-views/{$view->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $view->id);
    }

    // ─── Update ───────────────────────────────────────────────

    public function test_update_aktualisiert_view(): void
    {
        $view = AttributeView::factory()->create(['name_de' => 'Alt']);

        $response = $this->putJson("/api/v1/attribute-views/{$view->id}", [
            'name_de' => 'Neu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_de', 'Neu');
    }

    // ─── Destroy ──────────────────────────────────────────────

    public function test_destroy_loescht_leeren_view(): void
    {
        $view = AttributeView::factory()->create();

        $response = $this->deleteJson("/api/v1/attribute-views/{$view->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('attribute_views', ['id' => $view->id]);
    }

    // ─── Dependencies ─────────────────────────────────────────

    public function test_dependencies_gibt_constraint_info_zurueck(): void
    {
        $view = AttributeView::factory()->create();

        $response = $this->getJson("/api/v1/attribute-views/{$view->id}/dependencies");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['has_dependencies', 'total_count', 'dependencies']]);
    }

    // ─── assignAttribute ──────────────────────────────────────

    public function test_assign_attribute_ordnet_attribut_zu(): void
    {
        $view = AttributeView::factory()->create();
        $attribute = Attribute::factory()->create();

        $response = $this->postJson("/api/v1/attribute-views/{$view->id}/attributes", [
            'attribute_id' => $attribute->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('attribute_id', $attribute->id);

        $this->assertDatabaseHas('attribute_view_assignments', [
            'attribute_view_id' => $view->id,
            'attribute_id' => $attribute->id,
        ]);
    }

    public function test_assign_attribute_schlaegt_fehl_bei_nicht_existierendem_attribut(): void
    {
        $view = AttributeView::factory()->create();

        $response = $this->postJson("/api/v1/attribute-views/{$view->id}/attributes", [
            'attribute_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['attribute_id']);
    }

    // ─── removeAttribute ──────────────────────────────────────

    public function test_remove_attribute_entfernt_zuordnung(): void
    {
        $view = AttributeView::factory()->create();
        $attribute = Attribute::factory()->create();

        // Zuordnung anlegen
        AttributeViewAssignment::create([
            'attribute_view_id' => $view->id,
            'attribute_id' => $attribute->id,
        ]);

        $response = $this->deleteJson("/api/v1/attribute-views/{$view->id}/attributes/{$attribute->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('attribute_view_assignments', [
            'attribute_view_id' => $view->id,
            'attribute_id' => $attribute->id,
        ]);
    }

    public function test_remove_attribute_ist_idempotent_bei_nicht_vorhandener_zuordnung(): void
    {
        $view = AttributeView::factory()->create();
        $attribute = Attribute::factory()->create();

        // Keine Zuordnung vorhanden – trotzdem 204 zurückgeben
        $response = $this->deleteJson("/api/v1/attribute-views/{$view->id}/attributes/{$attribute->id}");

        $response->assertNoContent();
    }

    // ─── Produkttyp-Einschränkung (allowed_product_type_ids) ──────

    public function test_store_persistiert_erlaubte_produkttypen(): void
    {
        $typeA = ProductType::factory()->create();
        $typeB = ProductType::factory()->create();

        $response = $this->postJson('/api/v1/attribute-views', [
            'technical_name' => 'nur-fuer-typen',
            'name_de' => 'Nur für Typen',
            'allowed_product_type_ids' => [$typeA->id, $typeB->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.allowed_product_type_ids', [$typeA->id, $typeB->id]);

        $view = AttributeView::firstWhere('technical_name', 'nur-fuer-typen');
        $this->assertSame([$typeA->id, $typeB->id], $view->allowed_product_type_ids);
    }

    public function test_store_ohne_einschraenkung_liefert_leeres_array(): void
    {
        $response = $this->postJson('/api/v1/attribute-views', [
            'technical_name' => 'generisch',
            'name_de' => 'Generisch',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.allowed_product_type_ids', []);
    }

    public function test_update_aktualisiert_erlaubte_produkttypen(): void
    {
        $type = ProductType::factory()->create();
        $view = AttributeView::factory()->create();

        $response = $this->putJson("/api/v1/attribute-views/{$view->id}", [
            'allowed_product_type_ids' => [$type->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.allowed_product_type_ids', [$type->id]);
    }

    public function test_store_validiert_existierende_produkttyp_ids(): void
    {
        $response = $this->postJson('/api/v1/attribute-views', [
            'technical_name' => 'ungueltig',
            'name_de' => 'Ungültig',
            'allowed_product_type_ids' => ['00000000-0000-0000-0000-000000000000'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['allowed_product_type_ids.0']);
    }

    public function test_allows_product_type_gilt_ohne_einschraenkung_fuer_alle(): void
    {
        $view = AttributeView::factory()->create(['allowed_product_type_ids' => null]);

        $this->assertTrue($view->allowsProductType('irgendein-typ'));
        $this->assertTrue($view->allowsProductType(null));
    }

    public function test_allows_product_type_beschraenkt_auf_erlaubte_typen(): void
    {
        $type = ProductType::factory()->create();
        $view = AttributeView::factory()->create(['allowed_product_type_ids' => [$type->id]]);

        $this->assertTrue($view->allowsProductType($type->id));
        $this->assertFalse($view->allowsProductType('anderer-typ'));
    }
}
