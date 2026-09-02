<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tag-Suche im Asset-Katalog (/assetpreview).
 *
 * Tags laufen über denselben filters[...]-Vertrag wie die Attribut-Facetten —
 * unter dem reservierten Schlüssel `tags`, damit das Facetten-Widget unverändert
 * rendert. Anders als im Produktkatalog braucht es keine Freischaltung: die
 * Facette erscheint, sobald überhaupt Assets getaggt sind.
 */
class AssetCatalogTagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Admin', 'sanctum'));
        $this->actingAs($user);

        \Illuminate\Support\Once::flush();
    }

    public function test_facette_fehlt_solange_kein_asset_getaggt_ist(): void
    {
        Media::factory()->create();

        $this->getJson('/api/v1/asset-catalog/facets')
            ->assertOk()
            ->assertJsonPath('facets', []);
    }

    public function test_facette_zaehlt_assets_je_tag(): void
    {
        $freigabe = Tag::factory()->create(['name_de' => 'Freigegeben']);
        $entwurf = Tag::factory()->create(['name_de' => 'Entwurf']);

        Media::factory()->create()->tags()->attach([$freigabe->id, $entwurf->id]);
        Media::factory()->create()->tags()->attach($freigabe->id);
        Media::factory()->create();

        $response = $this->getJson('/api/v1/asset-catalog/facets');

        $response->assertOk()
            ->assertJsonPath('facets.0.attribute_id', 'tags')
            ->assertJsonPath('facets.0.data_type', 'ValueList')
            ->assertJsonPath('facets.0.values.0.value', 'Freigegeben')
            ->assertJsonPath('facets.0.values.0.count', 2)
            ->assertJsonPath('facets.0.values.1.count', 1);
    }

    public function test_facette_zeigt_inaktive_tags_nicht(): void
    {
        $inaktiv = Tag::factory()->create(['is_active' => false]);
        Media::factory()->create()->tags()->attach($inaktiv->id);

        $this->getJson('/api/v1/asset-catalog/facets')
            ->assertOk()
            ->assertJsonPath('facets', []);
    }

    public function test_facette_zeigt_englische_namen(): void
    {
        $tag = Tag::factory()->create(['name_de' => 'Freigegeben', 'name_en' => 'Approved']);
        Media::factory()->create()->tags()->attach($tag->id);

        $this->getJson('/api/v1/asset-catalog/facets?lang=en')
            ->assertOk()
            ->assertJsonPath('facets.0.values.0.value', 'Approved');
    }

    public function test_facette_zeigt_trotz_eigener_auswahl_alle_werte(): void
    {
        $erster = Tag::factory()->create();
        $zweiter = Tag::factory()->create();

        Media::factory()->create()->tags()->attach($erster->id);
        Media::factory()->create()->tags()->attach($zweiter->id);

        // Smart Graying: die eigene Auswahl darf die eigene Gruppe nicht leeren
        $this->getJson('/api/v1/asset-catalog/facets?filters[tags]='.$erster->id)
            ->assertOk()
            ->assertJsonCount(2, 'facets.0.values');
    }

    public function test_assetliste_filtert_nach_tag(): void
    {
        $tag = Tag::factory()->create();
        $mitTag = Media::factory()->create(['file_name' => 'mit-tag.jpg']);
        $mitTag->tags()->attach($tag->id);
        Media::factory()->create(['file_name' => 'ohne-tag.jpg']);

        $response = $this->getJson('/api/v1/asset-catalog/assets?filters[tags]='.$tag->id);

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('mit-tag.jpg', $response->json('data.0.file_name'));
    }

    public function test_asset_mit_mehreren_treffer_tags_erscheint_nur_einmal(): void
    {
        $erster = Tag::factory()->create();
        $zweiter = Tag::factory()->create();
        Media::factory()->create()->tags()->attach([$erster->id, $zweiter->id]);

        $this->getJson("/api/v1/asset-catalog/assets?filters[tags]={$erster->id},{$zweiter->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_assetliste_liefert_die_tags_mit(): void
    {
        $tag = Tag::factory()->create(['name_de' => 'Freigegeben']);
        Media::factory()->create()->tags()->attach($tag->id);

        $this->getJson('/api/v1/asset-catalog/assets')
            ->assertOk()
            ->assertJsonPath('data.0.tags.0.name_de', 'Freigegeben');
    }
}
