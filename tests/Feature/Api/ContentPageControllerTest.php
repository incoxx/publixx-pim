<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ContentPage;
use App\Models\ContentType;
use App\Models\Role;
use App\Models\SectionType;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Modul 'content' für die Tests als lizenziert behandeln.
        $this->mock(LicenseService::class, function ($mock) {
            $mock->shouldReceive('isModuleActive')->andReturn(true);
        });

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::findOrCreate('Admin', 'sanctum'));
        $this->actingAs($this->user);
    }

    private function contentType(): ContentType
    {
        return ContentType::create([
            'technical_name' => 'teaser-page',
            'name_de' => 'Teaserseite',
            'allowed_section_types' => ['headline', 'text'],
        ]);
    }

    private function sectionType(string $tn = 'headline'): SectionType
    {
        return SectionType::create([
            'technical_name' => $tn,
            'name_de' => 'Überschrift',
            'category' => 'text',
            'schema' => ['fields' => [['key' => 'text', 'type' => 'String']]],
        ]);
    }

    public function test_store_legt_content_seite_an(): void
    {
        $type = $this->contentType();

        $response = $this->postJson('/api/v1/content-pages', [
            'content_type_id' => $type->id,
            'slug' => 'home',
            'title' => 'Startseite',
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'home');

        $this->assertDatabaseHas('content_pages', ['slug' => 'home', 'title' => 'Startseite']);
    }

    public function test_index_ist_paginiert(): void
    {
        $type = $this->contentType();
        ContentPage::create(['content_type_id' => $type->id, 'slug' => 'a', 'title' => 'A']);
        ContentPage::create(['content_type_id' => $type->id, 'slug' => 'b', 'title' => 'B']);

        $this->getJson('/api/v1/content-pages')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_sektion_kann_hinzugefuegt_werden(): void
    {
        $type = $this->contentType();
        $st = $this->sectionType();
        $page = ContentPage::create(['content_type_id' => $type->id, 'slug' => 'home', 'title' => 'Start']);

        $response = $this->postJson("/api/v1/content-pages/{$page->id}/sections", [
            'section_type_id' => $st->id,
            'values_json' => ['de' => ['text' => 'Willkommen']],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('content_sections', ['content_page_id' => $page->id]);
    }

    public function test_gueltigkeitszeitraum_scope_filtert(): void
    {
        $type = $this->contentType();
        // aktuell gültig
        ContentPage::create(['content_type_id' => $type->id, 'slug' => 'now', 'title' => 'Jetzt', 'status' => 'active']);
        // abgelaufen
        ContentPage::create([
            'content_type_id' => $type->id, 'slug' => 'past', 'title' => 'Vorbei', 'status' => 'active',
            'valid_to' => now()->subDay(),
        ]);
        // noch nicht gültig
        ContentPage::create([
            'content_type_id' => $type->id, 'slug' => 'future', 'title' => 'Später', 'status' => 'active',
            'valid_from' => now()->addDay(),
        ]);

        $this->assertSame(1, ContentPage::currentlyValid()->count());
    }

    public function test_modul_gating_blockt_ohne_lizenz(): void
    {
        // LicenseService neu binden: Modul NICHT aktiv.
        $this->mock(LicenseService::class, function ($mock) {
            $mock->shouldReceive('isModuleActive')->andReturn(false);
        });

        $this->getJson('/api/v1/content-pages')->assertStatus(403);
    }
}
