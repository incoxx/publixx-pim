<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests für TagController.
 *
 * Routen:
 *   GET/POST            /api/v1/tags
 *   GET/PUT/DELETE      /api/v1/tags/{tag}
 *   GET                 /api/v1/tags/{tag}/dependencies
 *   PUT                 /api/v1/products/{product}/tags
 *   PUT                 /api/v1/media/{medium}/tags
 */
class TagControllerTest extends TestCase
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

    public function test_index_gibt_paginierte_tags_zurueck(): void
    {
        Tag::factory()->count(3)->create();

        $this->getJson('/api/v1/tags')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filtert_nach_suchbegriff(): void
    {
        Tag::factory()->create(['name_de' => 'Aktionsware', 'technical_name' => 'aktionsware']);
        Tag::factory()->create(['name_de' => 'Auslaufmodell', 'technical_name' => 'auslaufmodell']);

        $this->getJson('/api/v1/tags?search=Aktions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name_de', 'Aktionsware');
    }

    public function test_store_erstellt_tag_mit_mehrsprachigen_namen(): void
    {
        $response = $this->postJson('/api/v1/tags', [
            'technical_name' => 'neuheit',
            'name_de' => 'Neuheit',
            'name_en' => 'New',
            'name_json' => ['fr' => 'Nouveauté'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'neuheit')
            ->assertJsonPath('data.name_json.fr', 'Nouveauté');

        $this->assertDatabaseHas('tags', ['technical_name' => 'neuheit']);
    }

    public function test_store_leitet_technical_name_aus_name_de_ab(): void
    {
        $this->postJson('/api/v1/tags', ['name_de' => 'Für Außenbereich'])
            ->assertCreated()
            ->assertJsonPath('data.technical_name', 'fuer-aussenbereich');
    }

    public function test_store_haengt_zaehler_an_bei_kollidierendem_slug(): void
    {
        Tag::factory()->create(['technical_name' => 'neuheit']);

        $this->postJson('/api/v1/tags', ['name_de' => 'Neuheit'])
            ->assertCreated()
            ->assertJsonPath('data.technical_name', 'neuheit-2');
    }

    public function test_store_validiert_pflichtfeld_name_de(): void
    {
        $this->postJson('/api/v1/tags', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name_de']);
    }

    public function test_store_validiert_doppelten_technical_name(): void
    {
        Tag::factory()->create(['technical_name' => 'neuheit']);

        $this->postJson('/api/v1/tags', ['technical_name' => 'neuheit', 'name_de' => 'Andere'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name']);
    }

    public function test_update_aendert_tag(): void
    {
        $tag = Tag::factory()->create(['name_de' => 'Alt']);

        $this->putJson("/api/v1/tags/{$tag->id}", ['name_de' => 'Neu', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name_de', 'Neu')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_update_erlaubt_unveraenderten_eigenen_technical_name(): void
    {
        $tag = Tag::factory()->create(['technical_name' => 'neuheit']);

        $this->putJson("/api/v1/tags/{$tag->id}", [
            'technical_name' => 'neuheit',
            'name_de' => 'Neuheit',
        ])->assertOk();
    }

    public function test_destroy_loescht_ungenutzten_tag(): void
    {
        $tag = Tag::factory()->create();

        $this->deleteJson("/api/v1/tags/{$tag->id}")->assertNoContent();

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_destroy_blockt_bei_verwendung_und_erzwingt_mit_force(): void
    {
        $tag = Tag::factory()->create();
        $product = Product::factory()->create();
        $product->tags()->attach($tag->id, ['sort_order' => 0]);

        $this->deleteJson("/api/v1/tags/{$tag->id}")
            ->assertStatus(409)
            ->assertJsonPath('dependencies.products.count', 1);

        $this->deleteJson("/api/v1/tags/{$tag->id}?force=true")->assertNoContent();

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertDatabaseMissing('product_tag', ['tag_id' => $tag->id]);
    }

    public function test_dependencies_zaehlt_produkte_und_medien(): void
    {
        $tag = Tag::factory()->create();
        Product::factory()->create()->tags()->attach($tag->id, ['sort_order' => 0]);
        Media::factory()->create()->tags()->attach($tag->id, ['sort_order' => 0]);

        $this->getJson("/api/v1/tags/{$tag->id}/dependencies")
            ->assertOk()
            ->assertJsonPath('data.has_dependencies', true)
            ->assertJsonPath('data.total_count', 2);
    }

    public function test_index_liefert_verwendungszaehler(): void
    {
        $tag = Tag::factory()->create();
        Product::factory()->create()->tags()->attach($tag->id, ['sort_order' => 0]);

        $this->getJson('/api/v1/tags')
            ->assertOk()
            ->assertJsonPath('data.0.products_count', 1)
            ->assertJsonPath('data.0.media_count', 0);
    }

    public function test_sync_setzt_produkt_tags_in_uebergebener_reihenfolge(): void
    {
        $product = Product::factory()->create();
        $first = Tag::factory()->create(['name_de' => 'Zuerst']);
        $second = Tag::factory()->create(['name_de' => 'Danach']);

        $this->putJson("/api/v1/products/{$product->id}/tags", [
            'tag_ids' => [$second->id, $first->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);

        $this->assertDatabaseHas('product_tag', [
            'product_id' => $product->id,
            'tag_id' => $second->id,
            'sort_order' => 0,
        ]);
    }

    public function test_sync_entfernt_nicht_mehr_uebergebene_tags(): void
    {
        $product = Product::factory()->create();
        $tag = Tag::factory()->create();
        $product->tags()->attach($tag->id, ['sort_order' => 0]);

        $this->putJson("/api/v1/products/{$product->id}/tags", ['tag_ids' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseMissing('product_tag', ['product_id' => $product->id]);
    }

    public function test_sync_setzt_medien_tags(): void
    {
        $medium = Media::factory()->create();
        $tag = Tag::factory()->create();

        $this->putJson("/api/v1/media/{$medium->id}/tags", ['tag_ids' => [$tag->id]])
            ->assertOk()
            ->assertJsonPath('data.0.id', $tag->id);

        $this->assertDatabaseHas('media_tag', ['media_id' => $medium->id, 'tag_id' => $tag->id]);
    }

    public function test_sync_validiert_unbekannte_tag_id(): void
    {
        $product = Product::factory()->create();

        $this->putJson("/api/v1/products/{$product->id}/tags", [
            'tag_ids' => ['3f4d8b1e-0000-4000-8000-000000000000'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['tag_ids.0']);
    }

    public function test_ohne_berechtigung_kein_zugriff(): void
    {
        $other = User::factory()->create();
        $this->actingAs($other);

        $this->getJson('/api/v1/tags')->assertForbidden();
        $this->postJson('/api/v1/tags', ['name_de' => 'Test'])->assertForbidden();
    }

    public function test_medien_liefern_ihre_tags_beim_lesen_mit(): void
    {
        $medium = Media::factory()->create();
        $tag = Tag::factory()->create(['name_de' => 'Freigegeben']);

        $this->putJson("/api/v1/media/{$medium->id}/tags", ['tag_ids' => [$tag->id]])->assertOk();

        // Ohne Eager Loading wären die Tags nur schreibbar, aber nie lesbar
        $this->getJson("/api/v1/media/{$medium->id}")
            ->assertOk()
            ->assertJsonPath('data.tags.0.name_de', 'Freigegeben');

        $this->getJson('/api/v1/media')
            ->assertOk()
            ->assertJsonPath('data.0.tags.0.name_de', 'Freigegeben');
    }

    public function test_produkte_liefern_ihre_tags_beim_lesen_mit(): void
    {
        $product = Product::factory()->create();
        $tag = Tag::factory()->create(['name_de' => 'Neuheit']);

        $this->putJson("/api/v1/products/{$product->id}/tags", ['tag_ids' => [$tag->id]])->assertOk();

        $this->getJson("/api/v1/products/{$product->id}?include=tags")
            ->assertOk()
            ->assertJsonPath('data.tags.0.name_de', 'Neuheit');
    }

    // ── Massenzuordnung ──────────────────────────────────────────────

    public function test_massenzuordnung_ergaenzt_tags_und_laesst_bestehende_stehen(): void
    {
        $bestehend = Tag::factory()->create();
        $neu = Tag::factory()->create();

        $erstes = Product::factory()->create();
        $zweites = Product::factory()->create();
        $erstes->tags()->attach($bestehend->id);

        $this->postJson('/api/v1/products/bulk-tags', [
            'product_ids' => [$erstes->id, $zweites->id],
            'tag_ids' => [$neu->id],
        ])->assertOk()->assertJsonPath('products_count', 2);

        $this->assertEqualsCanonicalizing(
            [$bestehend->id, $neu->id],
            $erstes->fresh()->tags->pluck('id')->all(),
        );
        $this->assertSame([$neu->id], $zweites->fresh()->tags->pluck('id')->all());
    }

    public function test_massenzuordnung_ist_wiederholbar_ohne_duplikate(): void
    {
        $tag = Tag::factory()->create();
        $product = Product::factory()->create();

        foreach (range(1, 2) as $ignored) {
            $this->postJson('/api/v1/products/bulk-tags', [
                'product_ids' => [$product->id],
                'tag_ids' => [$tag->id],
            ])->assertOk();
        }

        $this->assertSame(1, $product->fresh()->tags->count());
    }

    public function test_massenzuordnung_entfernt_nur_die_genannten_tags(): void
    {
        $bleibt = Tag::factory()->create();
        $weg = Tag::factory()->create();
        $product = Product::factory()->create();
        $product->tags()->attach([$bleibt->id, $weg->id]);

        $this->postJson('/api/v1/products/bulk-tags', [
            'product_ids' => [$product->id],
            'tag_ids' => [$weg->id],
            'mode' => 'remove',
        ])->assertOk();

        $this->assertSame([$bleibt->id], $product->fresh()->tags->pluck('id')->all());
    }

    public function test_massenzuordnung_ersetzt_alle_tags_im_replace_modus(): void
    {
        $alt = Tag::factory()->create();
        $neu = Tag::factory()->create();
        $product = Product::factory()->create();
        $product->tags()->attach($alt->id);

        $this->postJson('/api/v1/products/bulk-tags', [
            'product_ids' => [$product->id],
            'tag_ids' => [$neu->id],
            'mode' => 'replace',
        ])->assertOk();

        $this->assertSame([$neu->id], $product->fresh()->tags->pluck('id')->all());
    }

    public function test_massenzuordnung_validiert_eingaben(): void
    {
        $this->postJson('/api/v1/products/bulk-tags', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_ids', 'tag_ids']);

        $product = Product::factory()->create();
        $tag = Tag::factory()->create();

        $this->postJson('/api/v1/products/bulk-tags', [
            'product_ids' => [$product->id],
            'tag_ids' => [$tag->id],
            'mode' => 'loeschen',
        ])->assertUnprocessable()->assertJsonValidationErrors(['mode']);
    }

    public function test_massenzuordnung_braucht_produkt_bearbeiten_recht(): void
    {
        $product = Product::factory()->create();
        $tag = Tag::factory()->create();

        $this->actingAs(User::factory()->create());

        $this->postJson('/api/v1/products/bulk-tags', [
            'product_ids' => [$product->id],
            'tag_ids' => [$tag->id],
        ])->assertForbidden();

        $this->assertSame(0, $product->fresh()->tags->count());
    }

    public function test_massenzuordnung_lehnt_uebergrosse_auswahl_ab(): void
    {
        $tag = Tag::factory()->create();
        $zuViele = array_map(fn () => (string) \Illuminate\Support\Str::uuid(), range(1, 5001));

        $this->postJson('/api/v1/products/bulk-tags', [
            'product_ids' => $zuViele,
            'tag_ids' => [$tag->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['product_ids']);
    }

    public function test_massenzuordnung_meldet_nur_tatsaechlich_geaenderte_produkte(): void
    {
        $tag = Tag::factory()->create();
        $product = Product::factory()->create();

        // Eine unbekannte ID darf die Rueckmeldung nicht aufblaehen
        $this->postJson('/api/v1/products/bulk-tags', [
            'product_ids' => [$product->id, (string) \Illuminate\Support\Str::uuid()],
            'tag_ids' => [$tag->id],
        ])->assertOk()->assertJsonPath('products_count', 1);
    }

    public function test_tags_setzen_erfordert_auch_das_tag_leserecht(): void
    {
        $product = Product::factory()->create();
        $tag = Tag::factory()->create();

        $ohneTagRecht = User::factory()->create();
        $rolle = Role::findOrCreate('Nur Produkte', 'sanctum');
        $rolle->givePermissionTo(\App\Models\Permission::findOrCreate('products.edit', 'sanctum'));
        $ohneTagRecht->assignRole($rolle);
        $this->actingAs($ohneTagRecht);

        $this->putJson("/api/v1/products/{$product->id}/tags", ['tag_ids' => [$tag->id]])
            ->assertForbidden();
    }
}
