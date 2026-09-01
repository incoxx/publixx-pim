<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature-Tests für die Profisuche (ProductSearchController).
 *
 * POST /products/search            → paginierte Suche mit Filtern
 * POST /products/search/ids        → alle Treffer-IDs
 * POST /products/search/count      → Treffer-Anzahl (Live-Preview)
 * GET  /products/search/attributes → suchbare Attribute
 *
 * Hinweis: search_mode=soundex/regex benötigt MySQL-Funktionen
 * (SOUNDEX, REGEXP) und ist unter SQLite nicht testbar.
 */
class ProductSearchControllerTest extends TestCase
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

    /** Als Admin authentifizieren (nicht im setUp, damit 401-Tests möglich sind). */
    private function login(): void
    {
        $this->actingAs($this->user);
    }

    // ── Auth ──────────────────────────────────────────────────────────

    public function test_suche_erfordert_authentifizierung(): void
    {
        $this->postJson('/api/v1/products/search', [])->assertStatus(401);
        $this->postJson('/api/v1/products/search/ids', [])->assertStatus(401);
        $this->postJson('/api/v1/products/search/count', [])->assertStatus(401);
        $this->getJson('/api/v1/products/search/attributes')->assertStatus(401);
    }

    // ── POST /products/search ─────────────────────────────────────────

    public function test_suche_ohne_filter_liefert_alle_produkte_paginiert(): void
    {
        $this->login();
        Product::factory()->count(3)->create();

        $response = $this->postJson('/api/v1/products/search', []);

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_varianten_werden_nicht_gefunden(): void
    {
        $this->login();
        Product::factory()->create(['name' => 'Hauptprodukt']);
        Product::factory()->variant()->create(['name' => 'Variante Rot']);

        $response = $this->postJson('/api/v1/products/search', []);

        $response->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame('Hauptprodukt', $response->json('data.0.name'));
    }

    public function test_textsuche_like_filtert_nach_name_sku_und_ean(): void
    {
        $this->login();
        $treffer = Product::factory()->create(['name' => 'Akkubohrer Professional']);
        Product::factory()->create(['name' => 'Hammer', 'sku' => 'HA-001-000', 'ean' => '1111111111111']);
        $skuTreffer = Product::factory()->create(['name' => 'Säge', 'sku' => 'AKKU-99', 'ean' => '2222222222222']);

        $response = $this->postJson('/api/v1/products/search', [
            'search' => 'Akku',
            'search_mode' => 'like',
        ]);

        $response->assertOk()->assertJsonPath('meta.total', 2);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($treffer->id, $ids);
        $this->assertContains($skuTreffer->id, $ids);
    }

    public function test_status_filter_liefert_nur_passende_produkte(): void
    {
        $this->login();
        $aktiv = Product::factory()->active()->create();
        Product::factory()->create(['status' => 'draft']);

        $response = $this->postJson('/api/v1/products/search', ['status' => 'active']);

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $aktiv->id);
    }

    public function test_produkttyp_filter(): void
    {
        $this->login();
        $typ = ProductType::factory()->create();
        $treffer = Product::factory()->create(['product_type_id' => $typ->id]);
        Product::factory()->create();

        $response = $this->postJson('/api/v1/products/search', [
            'product_type_ids' => [$typ->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $treffer->id);
    }

    public function test_kategorie_filter_mit_und_ohne_unterknoten(): void
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

        $imRoot = Product::factory()->create(['master_hierarchy_node_id' => $root->id]);
        $imChild = Product::factory()->create(['master_hierarchy_node_id' => $child->id]);
        Product::factory()->create(); // ohne Kategorie

        // Mit Unterknoten: beide Produkte
        $mit = $this->postJson('/api/v1/products/search', [
            'category_ids' => [$root->id],
            'include_descendants' => true,
        ]);
        $mit->assertOk()->assertJsonPath('meta.total', 2);

        // Ohne Unterknoten: nur das direkt zugeordnete Produkt
        $ohne = $this->postJson('/api/v1/products/search', [
            'category_ids' => [$root->id],
            'include_descendants' => false,
        ]);
        $ohne->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $imRoot->id);

        // Unbenutzte Variable vermeiden
        $this->assertNotNull($imChild->id);
    }

    public function test_attribut_filter_eq_auf_string_attribut(): void
    {
        $this->login();
        $attribut = Attribute::factory()->create(['data_type' => 'String']);
        $rot = Product::factory()->create();
        $blau = Product::factory()->create();
        ProductAttributeValue::factory()->create([
            'product_id' => $rot->id,
            'attribute_id' => $attribut->id,
            'value_string' => 'rot',
        ]);
        ProductAttributeValue::factory()->create([
            'product_id' => $blau->id,
            'attribute_id' => $attribut->id,
            'value_string' => 'blau',
        ]);

        $response = $this->postJson('/api/v1/products/search', [
            'attribute_filters' => [
                ['attribute_id' => $attribut->id, 'value' => 'rot', 'operator' => 'eq'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $rot->id);
    }

    public function test_attribut_filter_gte_auf_zahlen_attribut(): void
    {
        $this->login();
        $attribut = Attribute::factory()->numeric()->create();
        $schwer = Product::factory()->create();
        $leicht = Product::factory()->create();
        ProductAttributeValue::factory()->create([
            'product_id' => $schwer->id,
            'attribute_id' => $attribut->id,
            'value_string' => null,
            'value_number' => 25,
        ]);
        ProductAttributeValue::factory()->create([
            'product_id' => $leicht->id,
            'attribute_id' => $attribut->id,
            'value_string' => null,
            'value_number' => 5,
        ]);

        $response = $this->postJson('/api/v1/products/search', [
            'attribute_filters' => [
                ['attribute_id' => $attribut->id, 'value' => 10, 'operator' => 'gte'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $schwer->id);
    }

    public function test_attribut_filtergruppen_mit_or_verknuepfung(): void
    {
        $this->login();
        $farbe = Attribute::factory()->create(['data_type' => 'String']);
        $rot = Product::factory()->create();
        $blau = Product::factory()->create();
        $gruen = Product::factory()->create();
        foreach ([[$rot, 'rot'], [$blau, 'blau'], [$gruen, 'grün']] as [$produkt, $wert]) {
            ProductAttributeValue::factory()->create([
                'product_id' => $produkt->id,
                'attribute_id' => $farbe->id,
                'value_string' => $wert,
            ]);
        }

        $response = $this->postJson('/api/v1/products/search', [
            'attribute_filter_groups' => [
                'operator' => 'OR',
                'rules' => [
                    ['attribute_id' => $farbe->id, 'value' => 'rot', 'operator' => 'eq'],
                    ['attribute_id' => $farbe->id, 'value' => 'blau', 'operator' => 'eq'],
                ],
            ],
        ]);

        $response->assertOk()->assertJsonPath('meta.total', 2);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($rot->id, $ids);
        $this->assertContains($blau->id, $ids);
        $this->assertNotContains($gruen->id, $ids);
    }

    public function test_sortierung_nach_name_aufsteigend(): void
    {
        $this->login();
        Product::factory()->create(['name' => 'Zange']);
        Product::factory()->create(['name' => 'Akkubohrer']);
        Product::factory()->create(['name' => 'Meißel']);

        $response = $this->postJson('/api/v1/products/search', [
            'sort' => 'name',
            'order' => 'asc',
        ]);

        $response->assertOk();
        $namen = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Akkubohrer', 'Meißel', 'Zange'], $namen);
    }

    public function test_pagination_mit_per_page(): void
    {
        $this->login();
        Product::factory()->count(5)->create();

        $response = $this->postJson('/api/v1/products/search', [
            'per_page' => 2,
            'page' => 2,
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_attribut_spalten_werden_mitgeliefert(): void
    {
        $this->login();
        $attribut = Attribute::factory()->create(['data_type' => 'String']);
        $produkt = Product::factory()->create();
        ProductAttributeValue::factory()->create([
            'product_id' => $produkt->id,
            'attribute_id' => $attribut->id,
            'value_string' => 'Edelstahl',
        ]);

        $response = $this->postJson('/api/v1/products/search', [
            'attribute_columns' => [$attribut->id],
        ]);

        $response->assertOk()
            ->assertJsonPath("data.0.attributes.{$attribut->id}", 'Edelstahl');
    }

    public function test_validierungsfehler_bei_ungueltigen_parametern(): void
    {
        $this->login();

        $this->postJson('/api/v1/products/search', ['sort' => 'evil_column'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');

        $this->postJson('/api/v1/products/search', ['status' => 'gibt-es-nicht'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->postJson('/api/v1/products/search', ['per_page' => 5000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');

        $this->postJson('/api/v1/products/search', [
            'attribute_filters' => [['value' => 'x']],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('attribute_filters.0.attribute_id');
    }

    public function test_zu_tiefe_filtergruppen_werden_abgelehnt(): void
    {
        $this->login();
        $attribut = Attribute::factory()->create(['data_type' => 'String']);

        // 5 Ebenen Verschachtelung überschreitet die maximale Tiefe (3)
        $gruppe = ['attribute_id' => $attribut->id, 'value' => 'x', 'operator' => 'eq'];
        for ($i = 0; $i < 5; $i++) {
            $gruppe = ['type' => 'group', 'operator' => 'AND', 'rules' => [$gruppe]];
        }

        $response = $this->postJson('/api/v1/products/search', [
            'attribute_filter_groups' => $gruppe,
        ]);

        $response->assertStatus(422);
    }

    // ── POST /products/search/ids ─────────────────────────────────────

    public function test_ids_endpoint_liefert_alle_treffer_ids(): void
    {
        $this->login();
        $aktive = Product::factory()->active()->count(2)->create();
        Product::factory()->create(['status' => 'draft']);

        $response = $this->postJson('/api/v1/products/search/ids', [
            'status' => 'active',
        ]);

        $response->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing(
            $aktive->pluck('id')->all(),
            $response->json('data'),
        );
    }

    // ── POST /products/search/count ───────────────────────────────────

    public function test_count_endpoint_liefert_treffer_anzahl(): void
    {
        $this->login();
        Product::factory()->active()->count(3)->create();
        Product::factory()->create(['status' => 'draft']);

        $this->postJson('/api/v1/products/search/count', ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('count', 3);

        $this->postJson('/api/v1/products/search/count', [])
            ->assertOk()
            ->assertJsonPath('count', 4);
    }

    // ── GET /products/search/attributes ───────────────────────────────

    public function test_searchable_attributes_liefert_nur_aktive_externe_attribute(): void
    {
        $this->login();
        $sichtbar = Attribute::factory()->create([
            'data_type' => 'String',
            'is_internal' => false,
            'status' => 'active',
        ]);
        Attribute::factory()->create(['is_internal' => true, 'status' => 'active']);
        Attribute::factory()->create(['is_internal' => false, 'status' => 'inactive']);

        $response = $this->getJson('/api/v1/products/search/attributes');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($sichtbar->id, $response->json('data.0.id'));
        $this->assertSame($sichtbar->technical_name, $response->json('data.0.technical_name'));
    }

    // ── Tag-Filter ────────────────────────────────────────────────────

    public function test_tag_filter_liefert_produkte_mit_einem_der_tags(): void
    {
        $this->login();

        $neuheit = Tag::factory()->create(['name_de' => 'Neuheit']);
        $aktion = Tag::factory()->create(['name_de' => 'Aktion']);

        $mitNeuheit = Product::factory()->create(['sku' => 'TAG-1']);
        $mitAktion = Product::factory()->create(['sku' => 'TAG-2']);
        Product::factory()->create(['sku' => 'TAG-3']);

        $mitNeuheit->tags()->attach($neuheit->id, ['sort_order' => 0]);
        $mitAktion->tags()->attach($aktion->id, ['sort_order' => 0]);

        $response = $this->postJson('/api/v1/products/search', [
            'tag_ids' => [$neuheit->id, $aktion->id],
        ]);

        $response->assertOk();
        $skus = collect($response->json('data'))->pluck('sku')->sort()->values()->all();
        $this->assertSame(['TAG-1', 'TAG-2'], $skus);
    }

    public function test_tag_filter_mit_match_all_verlangt_alle_tags(): void
    {
        $this->login();

        $neuheit = Tag::factory()->create();
        $aktion = Tag::factory()->create();

        $beide = Product::factory()->create(['sku' => 'BEIDE']);
        $nurEiner = Product::factory()->create(['sku' => 'EINER']);

        $beide->tags()->attach([$neuheit->id, $aktion->id]);
        $nurEiner->tags()->attach($neuheit->id);

        $response = $this->postJson('/api/v1/products/search', [
            'tag_ids' => [$neuheit->id, $aktion->id],
            'tag_match' => 'all',
        ]);

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('BEIDE', $response->json('data.0.sku'));
    }

    public function test_treffer_enthalten_ihre_tags(): void
    {
        $this->login();

        $tag = Tag::factory()->create(['name_de' => 'Neuheit']);
        Product::factory()->create(['sku' => 'MIT-TAG'])->tags()->attach($tag->id);

        $response = $this->postJson('/api/v1/products/search', ['sku' => 'MIT-TAG']);

        $response->assertOk()
            ->assertJsonPath('data.0.tags.0.name_de', 'Neuheit');
    }

    public function test_count_beruecksichtigt_den_tag_filter(): void
    {
        $this->login();

        $tag = Tag::factory()->create();
        Product::factory()->create()->tags()->attach($tag->id);
        Product::factory()->create();

        $this->postJson('/api/v1/products/search/count', ['tag_ids' => [$tag->id]])
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_all_ids_beruecksichtigt_den_tag_filter(): void
    {
        $this->login();

        $tag = Tag::factory()->create();
        $mitTag = Product::factory()->create();
        $mitTag->tags()->attach($tag->id);
        Product::factory()->create();

        $response = $this->postJson('/api/v1/products/search/ids', ['tag_ids' => [$tag->id]]);

        $response->assertOk();
        $this->assertSame([$mitTag->id], $response->json('data'));
    }

    public function test_ohne_tag_filter_bleiben_alle_produkte_sichtbar(): void
    {
        $this->login();

        $tag = Tag::factory()->create();
        Product::factory()->create()->tags()->attach($tag->id);
        Product::factory()->create();

        $this->postJson('/api/v1/products/search', [])
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
