<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AttributeType;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductSearchIndex;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-Tests für den öffentlichen Katalog (CatalogController).
 *
 * Die Katalog-Endpunkte sind standardmäßig öffentlich (catalog_access_mode
 * = 'public'); nur checkConfig erfordert eine Sanctum-Authentifizierung.
 */
class CatalogControllerTest extends TestCase
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

        // WebsiteProfile::getActivePayload() nutzt once()-Memoization, die
        // prozessweit über Tests hinweg bestehen bleibt → vor jedem Test leeren.
        \Illuminate\Support\Once::flush();
    }

    /**
     * Aktives Website-Profil mit Payload anlegen (Katalog-Einstellungen).
     */
    private function createActiveProfile(array $payload): WebsiteProfile
    {
        // Migration legt ein aktives "Standard"-Profil an → erst deaktivieren,
        // damit getActivePayload() das Testprofil findet.
        WebsiteProfile::query()->update(['is_active' => false]);

        $profile = WebsiteProfile::create([
            'name' => 'Testprofil',
            'is_active' => true,
            'payload' => $payload,
        ]);

        \Illuminate\Support\Once::flush();

        return $profile;
    }

    /**
     * Aktives Produkt inkl. Suchindex-Eintrag anlegen (der Katalog liest
     * ausschließlich über products_search_index).
     */
    private function createIndexedProduct(array $productAttrs = [], array $indexAttrs = []): Product
    {
        $product = Product::factory()->active()->create($productAttrs);

        ProductSearchIndex::updateOrCreate(
            ['product_id' => $product->id],
            array_merge([
                'sku' => $product->sku,
                'ean' => $product->ean,
                'name_de' => $product->name,
                'status' => $product->status,
                'product_type' => 'product',
            ], $indexAttrs)
        );

        return $product;
    }

    // ── GET /catalog/products ────────────────────────────────────────

    public function test_products_listet_nur_aktive_produkte(): void
    {
        $this->createIndexedProduct(['name' => 'Aktiv Eins']);
        $this->createIndexedProduct(['name' => 'Aktiv Zwei']);

        // Draft-Produkt darf nicht erscheinen, auch wenn ein Index-Eintrag existiert
        $draft = Product::factory()->create(['status' => 'draft']);
        ProductSearchIndex::updateOrCreate(
            ['product_id' => $draft->id],
            ['sku' => $draft->sku, 'name_de' => $draft->name, 'status' => 'draft', 'product_type' => 'product']
        );

        $response = $this->getJson('/api/v1/catalog/products');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertHeader('X-Total-Count', '2')
            ->assertJsonStructure([['id', 'sku', 'name', 'price', 'currency']]);
    }

    public function test_products_suche_filtert_nach_name(): void
    {
        $this->createIndexedProduct(['name' => 'Bohrmaschine Profi'], ['name_de' => 'Bohrmaschine Profi']);
        $this->createIndexedProduct(['name' => 'Akkuschrauber'], ['name_de' => 'Akkuschrauber']);

        $response = $this->getJson('/api/v1/catalog/products?search=Bohrmaschine');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Bohrmaschine Profi');
    }

    public function test_products_filtert_nach_kategorie(): void
    {
        $hierarchy = Hierarchy::factory()->create();
        $nodeA = HierarchyNode::factory()->create(['hierarchy_id' => $hierarchy->id, 'path' => '/a/']);
        $nodeB = HierarchyNode::factory()->create(['hierarchy_id' => $hierarchy->id, 'path' => '/b/']);

        $inCategory = $this->createIndexedProduct(['master_hierarchy_node_id' => $nodeA->id]);
        $this->createIndexedProduct(['master_hierarchy_node_id' => $nodeB->id]);

        $response = $this->getJson('/api/v1/catalog/products?category=' . $nodeA->id);

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $inCategory->id);
    }

    public function test_products_paginierung_liefert_korrekte_header(): void
    {
        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            $this->createIndexedProduct(['name' => $name], ['name_de' => $name]);
        }

        $response = $this->getJson('/api/v1/catalog/products?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertHeader('X-Total-Count', '3')
            ->assertHeader('X-Last-Page', '2')
            ->assertHeader('X-Per-Page', '2');
    }

    // ── GET /catalog/products/{product} ──────────────────────────────

    public function test_product_detail_liefert_aktives_produkt(): void
    {
        $product = $this->createIndexedProduct(['name' => 'Detail-Produkt']);

        $response = $this->getJson("/api/v1/catalog/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_product_detail_404_fuer_inaktives_produkt(): void
    {
        $draft = Product::factory()->create(['status' => 'draft']);

        $this->getJson("/api/v1/catalog/products/{$draft->id}")->assertNotFound();
    }

    public function test_product_detail_404_fuer_unbekannte_id(): void
    {
        $this->getJson('/api/v1/catalog/products/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    // ── GET /catalog/categories ──────────────────────────────────────

    public function test_categories_liefert_baum_mit_produktzahlen(): void
    {
        $hierarchy = Hierarchy::factory()->create(['name_de' => 'Master-Baum']);
        $root = HierarchyNode::factory()->create([
            'hierarchy_id' => $hierarchy->id,
            'path' => '/r/',
            'depth' => 0,
        ]);
        $child = HierarchyNode::factory()->create([
            'hierarchy_id' => $hierarchy->id,
            'parent_node_id' => $root->id,
            'path' => '/r/c/',
            'depth' => 1,
        ]);

        Product::factory()->active()->count(2)->create(['master_hierarchy_node_id' => $child->id]);

        $response = $this->getJson('/api/v1/catalog/categories');

        $response->assertOk()
            ->assertJsonPath('data.hierarchy_id', $hierarchy->id)
            ->assertJsonPath('data.nodes.0.id', $root->id)
            // Roll-up: Kindknoten-Produkte zählen auch auf dem Elternknoten
            ->assertJsonPath('data.nodes.0.product_count', 2)
            ->assertJsonPath('data.nodes.0.children.0.id', $child->id)
            ->assertJsonPath('data.nodes.0.children.0.product_count', 2);
    }

    public function test_categories_ohne_hierarchie_liefert_leeres_ergebnis(): void
    {
        $response = $this->getJson('/api/v1/catalog/categories');

        $response->assertOk()
            ->assertJsonPath('data.hierarchy_id', null)
            ->assertJsonPath('data.nodes', []);
    }

    public function test_category_assets_404_fuer_unbekannten_knoten(): void
    {
        $this->getJson('/api/v1/catalog/categories/00000000-0000-0000-0000-000000000000/assets')
            ->assertNotFound();
    }

    // ── GET /catalog/facets + /catalog/attribute-groups ──────────────

    public function test_facets_leer_ohne_konfiguration(): void
    {
        $response = $this->getJson('/api/v1/catalog/facets');

        $response->assertOk()
            ->assertJsonPath('facets', []);
    }

    public function test_attribute_groups_liefert_gruppen(): void
    {
        AttributeType::factory()->create(['name_de' => 'Technische Daten', 'sort_order' => 1]);
        AttributeType::factory()->create(['name_de' => 'Logistik', 'sort_order' => 2]);

        $response = $this->getJson('/api/v1/catalog/attribute-groups');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Technische Daten');
    }

    // ── POST /catalog/products/compare ───────────────────────────────

    public function test_compare_403_wenn_nicht_aktiviert(): void
    {
        $products = Product::factory()->active()->count(2)->create();

        $response = $this->postJson('/api/v1/catalog/products/compare', [
            'product_ids' => $products->pluck('id')->all(),
        ]);

        $response->assertForbidden();
    }

    public function test_compare_vergleicht_produkte(): void
    {
        $this->createActiveProfile(['catalog_compare_enabled' => true]);

        $products = Product::factory()->active()->count(2)->create();

        // Gemeinsames Attribut mit unterschiedlichen Werten → is_different
        $attribute = \App\Models\Attribute::factory()->create(['data_type' => 'String']);
        ProductAttributeValue::factory()->create([
            'product_id' => $products[0]->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Rot',
        ]);
        ProductAttributeValue::factory()->create([
            'product_id' => $products[1]->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Blau',
        ]);

        $response = $this->postJson('/api/v1/catalog/products/compare', [
            'product_ids' => $products->pluck('id')->all(),
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.products')
            // 4 Basisfelder (SKU, Name, EAN, Status) + 1 Attribut
            ->assertJsonPath('data.total_attributes', 5);

        $attrRow = collect($response->json('data.rows'))
            ->firstWhere('attribute_id', $attribute->id);
        $this->assertNotNull($attrRow);
        $this->assertTrue($attrRow['is_different']);
    }

    public function test_compare_validierung_erfordert_mindestens_zwei_produkte(): void
    {
        $this->createActiveProfile(['catalog_compare_enabled' => true]);

        $product = Product::factory()->active()->create();

        $this->postJson('/api/v1/catalog/products/compare', [
            'product_ids' => [$product->id],
        ])->assertUnprocessable();
    }

    // ── Excel-Export + Zugriffskontrolle ──────────────────────────────

    public function test_wishlist_excel_403_wenn_nicht_aktiviert(): void
    {
        $product = Product::factory()->active()->create();

        $this->postJson('/api/v1/catalog/wishlist/excel', [
            'product_ids' => [$product->id],
        ])->assertForbidden();
    }

    public function test_zugriff_im_login_modus_erfordert_authentifizierung(): void
    {
        $this->createActiveProfile(['catalog_access_mode' => 'login']);

        $this->getJson('/api/v1/catalog/products')->assertUnauthorized();
    }

    // ── POST /catalog/check-config (Admin, auth:sanctum) ─────────────

    public function test_check_config_zaehlt_produkte(): void
    {
        $this->actingAs($this->user);

        Product::factory()->active()->count(3)->create();
        Product::factory()->create(['status' => 'draft']);

        $response = $this->postJson('/api/v1/catalog/check-config', []);

        $response->assertOk()
            ->assertJsonPath('data.product_count', 3);
    }

    public function test_check_config_erfordert_authentifizierung(): void
    {
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/catalog/check-config', [])->assertUnauthorized();
    }

    /**
     * Collection-Merkliste: liefert die Produkt-IDs in Positions-Reihenfolge,
     * lässt Freitext-Positionen (ohne product_id) und inaktive Produkte aus.
     */
    public function test_collection_wishlist_liefert_aktive_produkt_ids_in_reihenfolge(): void
    {
        $type = \App\Models\CollectionType::create([
            'technical_name' => 'angebot',
            'name_de' => 'Angebot',
        ]);

        $collection = \App\Models\Collection::create([
            'collection_type_id' => $type->id,
            'name' => 'Testkatalog',
        ]);

        $p1 = Product::factory()->active()->create();
        $p2 = Product::factory()->active()->create();
        $draft = Product::factory()->create(['status' => 'draft']);

        // Reihenfolge bewusst verdreht anlegen – die Position bestimmt die Ausgabe.
        \App\Models\CollectionItem::create(['collection_id' => $collection->id, 'product_id' => $p2->id, 'position' => 2]);
        \App\Models\CollectionItem::create(['collection_id' => $collection->id, 'product_id' => $p1->id, 'position' => 1]);
        // Freitext-Position ohne Produkt → muss ausgelassen werden
        \App\Models\CollectionItem::create(['collection_id' => $collection->id, 'product_id' => null, 'position' => 3]);
        // Inaktives (Draft-)Produkt → nicht im Katalog sichtbar, muss ausgelassen werden
        \App\Models\CollectionItem::create(['collection_id' => $collection->id, 'product_id' => $draft->id, 'position' => 4]);

        $response = $this->getJson('/api/v1/catalog/collections/' . $collection->id . '/wishlist');

        $response->assertOk()
            ->assertJsonPath('data.name', 'Testkatalog')
            ->assertJsonPath('data.product_ids', [$p1->id, $p2->id]);
    }

    public function test_collection_wishlist_404_fuer_unbekannte_collection(): void
    {
        $this->getJson('/api/v1/catalog/collections/00000000-0000-0000-0000-000000000000/wishlist')
            ->assertNotFound();
    }

    // ─── Katalog-Freigabelink (Token + optionales Passwort, ohne PIM-Login) ───

    private function createShareLink(array $attrs = []): \App\Models\CollectionShareLink
    {
        $type = \App\Models\CollectionType::create([
            'technical_name' => 'angebot-' . \Illuminate\Support\Str::random(6),
            'name_de' => 'Angebot',
        ]);
        $collection = \App\Models\Collection::create([
            'collection_type_id' => $type->id,
            'name' => 'Freigabe-Katalog',
        ]);

        return $collection->shareLinks()->create(array_merge([
            'token' => \Illuminate\Support\Str::random(48),
            'password_hash' => \Illuminate\Support\Facades\Hash::make('geheim123'),
        ], $attrs));
    }

    public function test_share_info_meldet_passwortpflicht(): void
    {
        $link = $this->createShareLink();

        $this->getJson('/api/v1/catalog/share/' . $link->token)
            ->assertOk()
            ->assertJsonPath('data.expired', false)
            ->assertJsonPath('data.requires_password', true)
            ->assertJsonPath('data.collection_name', 'Freigabe-Katalog');
    }

    public function test_share_info_410_fuer_abgelaufenen_link(): void
    {
        $link = $this->createShareLink(['expires_at' => now()->subDay()]);

        $this->getJson('/api/v1/catalog/share/' . $link->token)
            ->assertStatus(410)
            ->assertJsonPath('data.expired', true);
    }

    public function test_share_unlock_liefert_access_und_produkt_ids(): void
    {
        $link = $this->createShareLink();
        $p1 = Product::factory()->active()->create();
        $p2 = Product::factory()->active()->create();
        \App\Models\CollectionItem::create(['collection_id' => $link->collection_id, 'product_id' => $p2->id, 'position' => 2]);
        \App\Models\CollectionItem::create(['collection_id' => $link->collection_id, 'product_id' => $p1->id, 'position' => 1]);

        $response = $this->postJson('/api/v1/catalog/share/' . $link->token, ['password' => 'geheim123']);

        $response->assertOk()
            ->assertJsonPath('data.collection_name', 'Freigabe-Katalog')
            ->assertJsonPath('data.product_ids', [$p1->id, $p2->id]);
        $this->assertNotEmpty($response->json('data.access'));

        // Zugriffszähler wurde hochgezählt.
        $this->assertSame(1, $link->fresh()->view_count);
    }

    public function test_share_unlock_falsches_passwort_422(): void
    {
        $link = $this->createShareLink();

        $this->postJson('/api/v1/catalog/share/' . $link->token, ['password' => 'falsch'])
            ->assertStatus(422);

        $this->assertSame(0, $link->fresh()->view_count);
    }

    public function test_share_unlock_ohne_passwort_wenn_link_keines_hat(): void
    {
        $link = $this->createShareLink(['password_hash' => null]);

        $this->postJson('/api/v1/catalog/share/' . $link->token, [])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access', 'collection_name', 'product_ids']]);
    }

    public function test_share_access_token_oeffnet_login_gesicherten_katalog(): void
    {
        // Katalog auf Login-Zwang stellen — ohne Auth/Token ist /products dann gesperrt.
        $this->createActiveProfile(['catalog_access_mode' => 'login']);
        $this->createIndexedProduct(['name' => 'Freigegeben']);

        $this->getJson('/api/v1/catalog/products')->assertUnauthorized();

        // Über den Freigabelink ein Access-Token holen …
        $link = $this->createShareLink();
        $access = $this->postJson('/api/v1/catalog/share/' . $link->token, ['password' => 'geheim123'])
            ->assertOk()
            ->json('data.access');

        // … und damit den Katalog ohne PIM-Login öffnen.
        $this->getJson('/api/v1/catalog/products', ['X-Catalog-Share' => $access])
            ->assertOk();
    }

    public function test_share_access_token_darf_lesen_aber_nicht_bestellen(): void
    {
        $this->createActiveProfile(['catalog_access_mode' => 'login']);
        $this->createIndexedProduct(['name' => 'Freigegeben']);

        $link = $this->createShareLink();
        $access = $this->postJson('/api/v1/catalog/share/' . $link->token, ['password' => 'geheim123'])
            ->assertOk()
            ->json('data.access');

        // Lesen (Browsen) ist erlaubt …
        $this->getJson('/api/v1/catalog/products', ['X-Catalog-Share' => $access])
            ->assertOk();

        // … schreibende Warenkorb-/Bestell-Aktionen aber nicht (catalog.no-share → 403).
        $this->postJson('/api/v1/catalog/cart/standard/submit', [], ['X-Catalog-Share' => $access])
            ->assertForbidden();
    }
}
