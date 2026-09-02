<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Role;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tag-Gruppen — Muster wie Attributgruppen (AttributeType).
 *
 * Wichtigste Zusage: eine Gruppe zu löschen darf ihre Tags nicht mitreißen.
 * Tags hängen an Produkten und Medien; eine Sortierhilfe darf keine Daten kosten.
 */
class TagGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::findOrCreate('Admin', 'sanctum'));
        $this->actingAs($this->user);
    }

    public function test_index_liefert_gruppen_mit_anzahl_der_tags(): void
    {
        $gruppe = TagGroup::factory()->create(['name_de' => 'Saison']);
        Tag::factory()->count(2)->create(['tag_group_id' => $gruppe->id]);
        Tag::factory()->create();

        $this->getJson('/api/v1/tag-groups')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name_de', 'Saison')
            ->assertJsonPath('data.0.tags_count', 2);
    }

    public function test_store_leitet_technischen_namen_aus_name_de_ab(): void
    {
        $this->postJson('/api/v1/tag-groups', ['name_de' => 'Für Außenbereich'])
            ->assertCreated()
            ->assertJsonPath('data.technical_name', 'fuer-aussenbereich');
    }

    public function test_store_validiert_pflichtfeld_und_doppelten_namen(): void
    {
        $this->postJson('/api/v1/tag-groups', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name_de']);

        TagGroup::factory()->create(['technical_name' => 'saison']);

        $this->postJson('/api/v1/tag-groups', ['technical_name' => 'saison', 'name_de' => 'Andere'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name']);
    }

    public function test_update_aendert_die_gruppe(): void
    {
        $gruppe = TagGroup::factory()->create(['name_de' => 'Alt']);

        $this->putJson("/api/v1/tag-groups/{$gruppe->id}", ['name_de' => 'Neu', 'sort_order' => 5])
            ->assertOk()
            ->assertJsonPath('data.name_de', 'Neu')
            ->assertJsonPath('data.sort_order', 5);
    }

    public function test_loeschen_einer_gruppe_entfernt_die_tags_nicht(): void
    {
        $gruppe = TagGroup::factory()->create();
        $tag = Tag::factory()->create(['tag_group_id' => $gruppe->id]);
        $product = Product::factory()->create();
        $product->tags()->attach($tag->id);

        $this->deleteJson("/api/v1/tag-groups/{$gruppe->id}")->assertNoContent();

        // Tag bleibt, ist nur nicht mehr gruppiert — und hängt weiter am Produkt
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'tag_group_id' => null]);
        $this->assertSame(1, $product->fresh()->tags->count());
    }

    public function test_tag_laesst_sich_einer_gruppe_zuordnen(): void
    {
        $gruppe = TagGroup::factory()->create(['name_de' => 'Saison']);

        $response = $this->postJson('/api/v1/tags', [
            'name_de' => 'Winter',
            'tag_group_id' => $gruppe->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.tag_group_id', $gruppe->id);
    }

    public function test_tag_liste_liefert_die_gruppe_mit(): void
    {
        $gruppe = TagGroup::factory()->create(['name_de' => 'Saison']);
        Tag::factory()->create(['tag_group_id' => $gruppe->id]);

        $this->getJson('/api/v1/tags')
            ->assertOk()
            ->assertJsonPath('data.0.group.name_de', 'Saison');
    }

    public function test_tag_liste_laesst_sich_nach_gruppe_filtern(): void
    {
        $gruppe = TagGroup::factory()->create();
        Tag::factory()->create(['tag_group_id' => $gruppe->id, 'name_de' => 'In Gruppe']);
        Tag::factory()->create(['name_de' => 'Ohne Gruppe']);

        $this->getJson('/api/v1/tags?filter[tag_group_id]='.$gruppe->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name_de', 'In Gruppe');
    }

    public function test_unbekannte_gruppe_wird_abgelehnt(): void
    {
        $this->postJson('/api/v1/tags', [
            'name_de' => 'Winter',
            'tag_group_id' => '3f4d8b1e-0000-4000-8000-000000000000',
        ])->assertUnprocessable()->assertJsonValidationErrors(['tag_group_id']);
    }

    public function test_ohne_tag_recht_kein_zugriff(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/tag-groups')->assertForbidden();
        $this->postJson('/api/v1/tag-groups', ['name_de' => 'X'])->assertForbidden();
    }
}
