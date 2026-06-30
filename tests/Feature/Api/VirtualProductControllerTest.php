<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Role;
use App\Models\SearchProfile;
use App\Models\User;
use App\Models\VirtualProductDefinition;
use App\Models\WatchlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests für virtuelle Produkte (dynamische Cluster).
 *
 * Routen:
 *   GET    /api/v1/products/{product}/virtual-members
 *   GET    /api/v1/products/{product}/virtual-definition
 *   PUT    /api/v1/products/{product}/virtual-definition
 *   DELETE /api/v1/products/{product}/virtual-definition
 *   POST   /api/v1/products/{product}/virtual-definition/from-watchlist
 */
class VirtualProductControllerTest extends TestCase
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

    private function virtualProduct(): Product
    {
        return Product::factory()->create(['product_type_ref' => 'virtual']);
    }

    public function test_manuelle_auswahl_loest_genau_die_gewaehlten_produkte_auf(): void
    {
        $virtual = $this->virtualProduct();
        $a = Product::factory()->create();
        $b = Product::factory()->create();

        $this->putJson("/api/v1/products/{$virtual->id}/virtual-definition", [
            'source_type' => 'manual',
            'manual_product_ids' => [$b->id, $a->id],
        ])->assertOk()->assertJsonPath('data.source_type', 'manual');

        $response = $this->getJson("/api/v1/products/{$virtual->id}/virtual-members");

        $response->assertOk()->assertJsonCount(2, 'data');
        // Reihenfolge bleibt erhalten (b vor a)
        $this->assertSame([$b->id, $a->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_suchprofil_loest_gefilterte_produkte_auf(): void
    {
        $virtual = $this->virtualProduct();
        $active = Product::factory()->create(['status' => 'active']);
        Product::factory()->create(['status' => 'draft']);

        $profile = SearchProfile::create([
            'name' => 'Aktive Produkte',
            'user_id' => $this->user->id,
            'status_filter' => 'active',
        ]);

        $this->putJson("/api/v1/products/{$virtual->id}/virtual-definition", [
            'source_type' => 'search_profile',
            'search_profile_id' => $profile->id,
        ])->assertOk();

        $response = $this->getJson("/api/v1/products/{$virtual->id}/virtual-members");

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($active->id, $response->json('data.0.id'));
    }

    public function test_klammer_und_virtuelle_produkte_sind_keine_mitglieder(): void
    {
        $virtual = $this->virtualProduct();
        $other = Product::factory()->create();
        $anotherVirtual = Product::factory()->create(['product_type_ref' => 'virtual']);

        $this->putJson("/api/v1/products/{$virtual->id}/virtual-definition", [
            'source_type' => 'manual',
            'manual_product_ids' => [$virtual->id, $anotherVirtual->id, $other->id],
        ])->assertOk();

        $response = $this->getJson("/api/v1/products/{$virtual->id}/virtual-members");

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($other->id, $response->json('data.0.id'));
    }

    public function test_max_members_begrenzt_die_mitgliederzahl(): void
    {
        $virtual = $this->virtualProduct();
        $members = Product::factory()->count(5)->create()->pluck('id')->all();

        $this->putJson("/api/v1/products/{$virtual->id}/virtual-definition", [
            'source_type' => 'manual',
            'manual_product_ids' => $members,
            'max_members' => 2,
        ])->assertOk();

        $this->getJson("/api/v1/products/{$virtual->id}/virtual-members")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_definition_nur_fuer_virtuelle_produkte_erlaubt(): void
    {
        $normal = Product::factory()->create(['product_type_ref' => 'product']);
        $other = Product::factory()->create();

        $this->putJson("/api/v1/products/{$normal->id}/virtual-definition", [
            'source_type' => 'manual',
            'manual_product_ids' => [$other->id],
        ])->assertStatus(422);
    }

    public function test_from_watchlist_uebernimmt_merkliste_als_manuelle_mitglieder(): void
    {
        $virtual = $this->virtualProduct();
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        WatchlistItem::create(['user_id' => $this->user->id, 'product_id' => $a->id]);
        WatchlistItem::create(['user_id' => $this->user->id, 'product_id' => $b->id]);

        $this->postJson("/api/v1/products/{$virtual->id}/virtual-definition/from-watchlist")
            ->assertOk()
            ->assertJsonPath('data.source_type', 'manual');

        $this->assertDatabaseHas('virtual_product_definitions', [
            'product_id' => $virtual->id,
            'source_type' => 'manual',
        ]);

        $this->getJson("/api/v1/products/{$virtual->id}/virtual-members")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_upsert_aktualisiert_bestehende_definition_und_leert_fremde_felder(): void
    {
        $virtual = $this->virtualProduct();
        $a = Product::factory()->create();
        $profile = SearchProfile::create([
            'name' => 'P',
            'user_id' => $this->user->id,
            'status_filter' => 'active',
        ]);

        // Zuerst manuell
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-definition", [
            'source_type' => 'manual',
            'manual_product_ids' => [$a->id],
        ])->assertOk();

        // Dann auf Suchprofil umstellen → manual_product_ids muss geleert werden
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-definition", [
            'source_type' => 'search_profile',
            'search_profile_id' => $profile->id,
        ])->assertOk();

        $definition = VirtualProductDefinition::where('product_id', $virtual->id)->firstOrFail();
        $this->assertSame('search_profile', $definition->source_type);
        $this->assertNull($definition->manual_product_ids);
        $this->assertSame(1, VirtualProductDefinition::where('product_id', $virtual->id)->count());
    }

    public function test_kann_virtuelles_produkt_ueber_api_anlegen(): void
    {
        $type = \App\Models\ProductType::factory()->create();

        $this->postJson('/api/v1/products', [
            'product_type_id' => $type->id,
            'product_type_ref' => 'virtual',
            'sku' => 'SMARTONE-V',
            'name' => 'Virtuelles Smartone Produkt',
        ])->assertCreated()
            ->assertJsonPath('data.product_type_ref', 'virtual');

        $this->assertDatabaseHas('products', [
            'sku' => 'SMARTONE-V',
            'product_type_ref' => 'virtual',
        ]);
    }

    public function test_virtuelle_produkte_erscheinen_in_produktliste(): void
    {
        $virtual = Product::factory()->create(['product_type_ref' => 'virtual']);
        $normal = Product::factory()->create(['product_type_ref' => 'product']);
        $variant = Product::factory()->create(['product_type_ref' => 'variant']);

        $ids = collect($this->getJson('/api/v1/products')->assertOk()->json('data'))
            ->pluck('id')->all();

        $this->assertContains($virtual->id, $ids);
        $this->assertContains($normal->id, $ids);
        $this->assertNotContains($variant->id, $ids);
    }

    public function test_delete_entfernt_definition(): void
    {
        $virtual = $this->virtualProduct();
        $a = Product::factory()->create();
        $this->putJson("/api/v1/products/{$virtual->id}/virtual-definition", [
            'source_type' => 'manual',
            'manual_product_ids' => [$a->id],
        ])->assertOk();

        $this->deleteJson("/api/v1/products/{$virtual->id}/virtual-definition")->assertNoContent();

        $this->assertDatabaseMissing('virtual_product_definitions', ['product_id' => $virtual->id]);
    }
}
