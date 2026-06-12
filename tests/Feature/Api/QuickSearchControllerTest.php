<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature-Tests für die Schnellsuche (QuickSearchController).
 *
 * GET /quick-search — Google-artige Suche über Produkte, Medien,
 * Hierarchien und Attribute (liest aus products_search_index).
 *
 * Hinweis: Unter SQLite greift der LIKE-Fallback (kein MySQL-FULLTEXT,
 * keine Phonetik) — getestet wird der Fallback-Pfad inkl. Drilldown.
 */
class QuickSearchControllerTest extends TestCase
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

        // Such-Index- und Konformitäts-Jobs nicht synchron ausführen
        Queue::fake();
    }

    /** Als Admin authentifizieren (nicht im setUp, damit der 401-Test möglich ist). */
    private function login(): void
    {
        $this->actingAs($this->user);
    }

    /**
     * Aktives Produkt inkl. Suchindex-Eintrag anlegen (die Schnellsuche
     * liest Produkte ausschließlich über products_search_index).
     */
    private function createIndexedProduct(array $productAttrs = [], array $indexAttrs = []): Product
    {
        $product = Product::factory()->active()->create($productAttrs);

        DB::table('products_search_index')->insert(array_merge([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'ean' => $product->ean,
            'name_de' => $product->name,
            'status' => $product->status,
        ], $indexAttrs));

        return $product;
    }

    // ── Auth & Validierung ────────────────────────────────────────────

    public function test_schnellsuche_erfordert_authentifizierung(): void
    {
        $this->getJson('/api/v1/quick-search?q=test')->assertStatus(401);
    }

    public function test_validierungsfehler_bei_ungueltigen_parametern(): void
    {
        $this->login();

        $this->getJson('/api/v1/quick-search?limit=99')
            ->assertStatus(422)
            ->assertJsonValidationErrors('limit');

        $this->getJson('/api/v1/quick-search?type=unbekannt')
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    // ── Counts (ohne type) ────────────────────────────────────────────

    public function test_ohne_type_werden_nur_counts_geliefert(): void
    {
        $this->login();
        $this->createIndexedProduct(['name' => 'Akkubohrer Profi']);
        Media::factory()->create(['title_de' => 'Akkubohrer Bild']);
        Attribute::factory()->create(['name_de' => 'Akkuleistung', 'status' => 'active']);
        HierarchyNode::factory()->create(['name_de' => 'Akkugeräte']);

        $response = $this->getJson('/api/v1/quick-search?q=Akku');

        $response->assertOk()
            ->assertJsonPath('query', 'Akku')
            ->assertJsonPath('counts.products', 1)
            ->assertJsonPath('counts.media', 1)
            ->assertJsonPath('counts.hierarchies', 1)
            ->assertJsonPath('counts.attributes', 1)
            ->assertJsonPath('results', []);
    }

    public function test_leere_query_liefert_gesamt_counts(): void
    {
        $this->login();
        $this->createIndexedProduct();
        $this->createIndexedProduct();

        $response = $this->getJson('/api/v1/quick-search');

        $response->assertOk()
            ->assertJsonPath('query', '')
            ->assertJsonPath('counts.products', 2);
    }

    // ── Produktsuche ──────────────────────────────────────────────────

    public function test_produktsuche_findet_treffer_ueber_suchindex(): void
    {
        $this->login();
        $treffer = $this->createIndexedProduct(['name' => 'Akkubohrer Professional']);
        $this->createIndexedProduct(['name' => 'Hammer', 'sku' => 'HA-100-001', 'ean' => '1111111111111']);

        $response = $this->getJson('/api/v1/quick-search?q=Akkubohrer&type=products');

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.type', 'product')
            ->assertJsonPath('results.0.id', $treffer->id)
            ->assertJsonPath('results.0.title', 'Akkubohrer Professional')
            ->assertJsonPath('results.0.subtitle', 'SKU: ' . $treffer->sku);
    }

    public function test_produktsuche_findet_treffer_ueber_sku(): void
    {
        $this->login();
        $treffer = $this->createIndexedProduct(['name' => 'Säge', 'sku' => 'XY-777-001']);
        $this->createIndexedProduct(['name' => 'Hammer', 'sku' => 'HA-100-002', 'ean' => '1111111111112']);

        $response = $this->getJson('/api/v1/quick-search?q=XY-777&type=products');

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $treffer->id);
    }

    public function test_produktsuche_liefert_nur_aktive_produkte(): void
    {
        $this->login();
        $this->createIndexedProduct(['name' => 'Akkubohrer aktiv']);

        // Indexierter Entwurf darf nicht erscheinen
        $draft = Product::factory()->create(['name' => 'Akkubohrer Entwurf', 'status' => 'draft']);
        DB::table('products_search_index')->insert([
            'product_id' => $draft->id,
            'sku' => $draft->sku,
            'name_de' => $draft->name,
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/v1/quick-search?q=Akkubohrer&type=products');

        $response->assertOk()->assertJsonCount(1, 'results');
        $this->assertSame('Akkubohrer aktiv', $response->json('results.0.title'));
    }

    public function test_limit_begrenzt_die_treffer(): void
    {
        $this->login();
        foreach (range(1, 3) as $i) {
            $this->createIndexedProduct(['name' => "Akkuschrauber {$i}", 'ean' => "300000000000{$i}"]);
        }

        $response = $this->getJson('/api/v1/quick-search?q=Akkuschrauber&type=products&limit=2');

        $response->assertOk()->assertJsonCount(2, 'results');
    }

    public function test_offset_laedt_weitere_treffer_nach(): void
    {
        $this->login();
        foreach (range(1, 3) as $i) {
            $this->createIndexedProduct(['name' => "Akkuschrauber {$i}", 'ean' => "400000000000{$i}"]);
        }

        $response = $this->getJson('/api/v1/quick-search?q=Akkuschrauber&type=products&limit=2&offset=2');

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('has_more', false);
    }

    public function test_kategorie_drilldown_filtert_produkte_nach_teilbaum(): void
    {
        $this->login();
        $hierarchy = Hierarchy::factory()->create();
        $root = HierarchyNode::factory()->create(['hierarchy_id' => $hierarchy->id]);
        $root->update(['path' => '/' . $root->id . '/']);
        $child = HierarchyNode::factory()->create([
            'hierarchy_id' => $hierarchy->id,
            'parent_node_id' => $root->id,
            'depth' => 1,
        ]);
        $child->update(['path' => $root->path . $child->id . '/']);

        $imBaum = $this->createIndexedProduct([
            'name' => 'Akkubohrer Kategorie',
            'master_hierarchy_node_id' => $child->id,
        ]);
        $this->createIndexedProduct(['name' => 'Akkubohrer ohne Kategorie', 'ean' => '5000000000001']);

        $response = $this->getJson(
            '/api/v1/quick-search?q=Akkubohrer&type=products&category_id=' . $root->id,
        );

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $imBaum->id);
    }

    public function test_attribut_drilldown_filtert_produkte_mit_attributwert(): void
    {
        $this->login();
        $attribut = Attribute::factory()->create(['data_type' => 'String']);
        $mitWert = $this->createIndexedProduct(['name' => 'Akkubohrer mit Attribut']);
        $this->createIndexedProduct(['name' => 'Akkubohrer ohne Attribut', 'ean' => '6000000000001']);

        ProductAttributeValue::factory()->create([
            'product_id' => $mitWert->id,
            'attribute_id' => $attribut->id,
            'value_string' => 'Edelstahl',
        ]);

        $response = $this->getJson(
            '/api/v1/quick-search?q=Akkubohrer&type=products&attribute_id=' . $attribut->id,
        );

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $mitWert->id);
    }

    // ── Medien, Hierarchien, Attribute ────────────────────────────────

    public function test_mediensuche_findet_treffer_nach_titel(): void
    {
        $this->login();
        $medium = Media::factory()->create(['title_de' => 'Produktfoto Akkubohrer']);
        Media::factory()->create(['title_de' => 'Logo']);

        $response = $this->getJson('/api/v1/quick-search?q=Akkubohrer&type=media');

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.type', 'media')
            ->assertJsonPath('results.0.id', $medium->id)
            ->assertJsonPath('results.0.title', 'Produktfoto Akkubohrer');
    }

    public function test_hierarchiesuche_findet_aktive_knoten(): void
    {
        $this->login();
        $hierarchy = Hierarchy::factory()->create(['name_de' => 'Master']);
        $knoten = HierarchyNode::factory()->create([
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Elektrowerkzeuge',
        ]);
        HierarchyNode::factory()->create([
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Elektro inaktiv',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/quick-search?q=Elektro&type=hierarchies');

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.type', 'hierarchy')
            ->assertJsonPath('results.0.id', $knoten->id)
            ->assertJsonPath('results.0.title', 'Elektrowerkzeuge');
    }

    public function test_attributsuche_findet_treffer_nach_name_und_technical_name(): void
    {
        $this->login();
        $attribut = Attribute::factory()->create([
            'name_de' => 'Gewicht',
            'technical_name' => 'gewicht-kg',
            'data_type' => 'Number',
            'status' => 'active',
        ]);
        Attribute::factory()->create(['name_de' => 'Farbe', 'technical_name' => 'farbe', 'status' => 'active']);

        $response = $this->getJson('/api/v1/quick-search?q=gewicht&type=attributes');

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.type', 'attribute')
            ->assertJsonPath('results.0.id', $attribut->id)
            ->assertJsonPath('results.0.title', 'Gewicht');
    }
}
