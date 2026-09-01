<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Manufacturer;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\SearchProfile;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Suchprofile der Profisuche.
 *
 * Schwerpunkt: ein gespeichertes Profil muss dieselbe Treffermenge liefern wie
 * die Suche beim Speichern. Produkttyp-, Hersteller- und Tag-Filter wurden
 * früher verworfen — das Profil war danach unbemerkt weiter gefasst.
 */
class SearchProfileControllerTest extends TestCase
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

    public function test_speichert_produkttyp_hersteller_und_tag_filter(): void
    {
        $productType = ProductType::factory()->create();
        $manufacturer = Manufacturer::create(['name' => 'Testhersteller']);
        $tag = Tag::factory()->create();

        $response = $this->postJson('/api/v1/search-profiles', [
            'name' => 'Neuheiten eines Herstellers',
            'product_type_ids' => [$productType->id],
            'manufacturer_ids' => [$manufacturer->id],
            'tag_ids' => [$tag->id],
            'tag_match' => 'all',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.product_type_ids', [$productType->id])
            ->assertJsonPath('data.manufacturer_ids', [$manufacturer->id])
            ->assertJsonPath('data.tag_ids', [$tag->id])
            ->assertJsonPath('data.tag_match', 'all');
    }

    public function test_gibt_die_filter_beim_laden_zurueck(): void
    {
        $tag = Tag::factory()->create();
        $manufacturer = Manufacturer::create(['name' => 'Testhersteller']);

        SearchProfile::create([
            'name' => 'Profil',
            'user_id' => $this->user->id,
            'tag_ids' => [$tag->id],
            'manufacturer_ids' => [$manufacturer->id],
            'tag_match' => 'any',
        ]);

        $this->getJson('/api/v1/search-profiles')
            ->assertOk()
            ->assertJsonPath('data.0.tag_ids', [$tag->id])
            ->assertJsonPath('data.0.manufacturer_ids', [$manufacturer->id]);
    }

    public function test_tag_match_ist_standardmaessig_any(): void
    {
        $this->postJson('/api/v1/search-profiles', ['name' => 'Ohne Tags'])
            ->assertCreated()
            ->assertJsonPath('data.tag_match', 'any');
    }

    public function test_lehnt_unbekanntes_tag_match_ab(): void
    {
        $this->postJson('/api/v1/search-profiles', ['name' => 'X', 'tag_match' => 'vielleicht'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tag_match']);
    }

    public function test_update_aendert_die_filter(): void
    {
        $profile = SearchProfile::create(['name' => 'Profil', 'user_id' => $this->user->id]);
        $tag = Tag::factory()->create();

        $this->putJson("/api/v1/search-profiles/{$profile->id}", [
            'tag_ids' => [$tag->id],
            'tag_match' => 'all',
        ])
            ->assertOk()
            ->assertJsonPath('data.tag_ids', [$tag->id])
            ->assertJsonPath('data.tag_match', 'all');
    }

    public function test_entfernt_geloeschte_tags_aus_dem_profil(): void
    {
        $bleibt = Tag::factory()->create();
        $geloescht = Tag::factory()->create();

        $profile = SearchProfile::create([
            'name' => 'Profil',
            'user_id' => $this->user->id,
            'tag_ids' => [$bleibt->id, $geloescht->id],
        ]);

        $geloescht->delete();

        $this->getJson('/api/v1/search-profiles')
            ->assertOk()
            ->assertJsonPath('data.0.tag_ids', [$bleibt->id]);

        // Bereinigung wird persistiert, damit sie sich nicht ansammelt
        $this->assertSame([$bleibt->id], $profile->fresh()->tag_ids);
    }

    public function test_entfernt_geloeschte_hersteller_und_produkttypen(): void
    {
        $produkttyp = ProductType::factory()->create();
        $hersteller = Manufacturer::create(['name' => 'Testhersteller']);

        SearchProfile::create([
            'name' => 'Profil',
            'user_id' => $this->user->id,
            'product_type_ids' => [$produkttyp->id],
            'manufacturer_ids' => [$hersteller->id],
        ]);

        $produkttyp->delete();
        $hersteller->delete();

        $this->getJson('/api/v1/search-profiles')
            ->assertOk()
            ->assertJsonPath('data.0.product_type_ids', [])
            ->assertJsonPath('data.0.manufacturer_ids', []);
    }

    public function test_behaelt_die_reihenfolge_der_auswahl_beim_bereinigen(): void
    {
        $ersterTag = Tag::factory()->create();
        $zweiterTag = Tag::factory()->create();
        $dritterTag = Tag::factory()->create();

        SearchProfile::create([
            'name' => 'Profil',
            'user_id' => $this->user->id,
            'tag_ids' => [$dritterTag->id, $ersterTag->id, $zweiterTag->id],
        ]);

        $ersterTag->delete();

        $this->getJson('/api/v1/search-profiles')
            ->assertOk()
            ->assertJsonPath('data.0.tag_ids', [$dritterTag->id, $zweiterTag->id]);
    }

    public function test_bestehende_kategoriebereinigung_bleibt_erhalten(): void
    {
        $hierarchy = Hierarchy::factory()->create();
        $knoten = HierarchyNode::factory()->create(['hierarchy_id' => $hierarchy->id]);

        SearchProfile::create([
            'name' => 'Profil',
            'user_id' => $this->user->id,
            'category_ids' => [$knoten->id, '3f4d8b1e-0000-4000-8000-000000000000'],
        ]);

        $this->getJson('/api/v1/search-profiles')
            ->assertOk()
            ->assertJsonPath('data.0.category_ids', [$knoten->id]);
    }

    public function test_fremde_profile_bleiben_unsichtbar_wenn_nicht_geteilt(): void
    {
        $anderer = User::factory()->create();
        SearchProfile::create(['name' => 'Privat', 'user_id' => $anderer->id, 'is_shared' => false]);
        SearchProfile::create(['name' => 'Geteilt', 'user_id' => $anderer->id, 'is_shared' => true]);

        $namen = collect($this->getJson('/api/v1/search-profiles')->json('data'))->pluck('name')->all();

        $this->assertSame(['Geteilt'], $namen);
    }

    public function test_bereinigung_skaliert_nicht_mit_der_anzahl_der_profile(): void
    {
        $tag = Tag::factory()->create();
        $geloescht = Tag::factory()->create();
        $geloeschteId = $geloescht->id;
        $geloescht->delete();

        // Zehn Profile mit je einem gültigen und einem gelöschten Tag
        foreach (range(1, 10) as $i) {
            SearchProfile::create([
                'name' => "Profil {$i}",
                'user_id' => $this->user->id,
                'tag_ids' => [$tag->id, $geloeschteId],
            ]);
        }

        DB::enableQueryLog();
        $this->getJson('/api/v1/search-profiles')->assertOk();
        $abfragen = DB::getQueryLog();
        DB::disableQueryLog();

        // Eine Abfrage je Referenzfeld, nicht je Profil. Die Aktualisierungen der
        // zehn bereinigten Profile sind unvermeidbar, die Prüfabfragen nicht.
        $pruefungen = collect($abfragen)->filter(
            fn ($q) => str_contains($q['query'], 'select') && str_contains($q['query'], '"tags"'),
        );

        $this->assertCount(1, $pruefungen, 'Tag-Existenzprüfung darf nur einmal laufen, nicht je Profil');
    }
}
