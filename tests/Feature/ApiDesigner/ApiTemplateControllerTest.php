<?php

declare(strict_types=1);

namespace Tests\Feature\ApiDesigner;

use App\Http\Middleware\CheckModuleLicense;
use App\Models\ApiTemplate;
use App\Models\Attribute;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-Tests für die API-Template-Verwaltung (CRUD + fields-Endpoint).
 */
class ApiTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Enterprise-Lizenz-Middleware deaktivieren (Modul api_designer)
        $this->withoutMiddleware(CheckModuleLicense::class);

        $this->user = User::factory()->create();
        $role = Role::findOrCreate('Admin', 'sanctum');
        $this->user->assignRole($role);
    }

    /** Minimale, gültige template_json-Struktur. */
    private function templateJson(): array
    {
        return [
            'version' => 1,
            'groups' => [[
                'id' => 'g1',
                'field' => 'none',
                'label' => 'Alle Produkte',
                'sortOrder' => 'asc',
                'header' => ['elements' => []],
                'footer' => ['elements' => []],
                'groups' => [],
                'detail' => ['elements' => [
                    ['type' => 'field', 'field' => 'sku', 'jsonKey' => 'sku', 'dataType' => 'string'],
                    ['type' => 'field', 'field' => 'name', 'jsonKey' => 'name', 'dataType' => 'string'],
                ]],
            ]],
        ];
    }

    public function test_store_erstellt_template_mit_felddefinitionen_und_api_key(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/v1/api-templates', [
            'name' => 'Shop Export',
            'description' => 'Produktdaten für den Shop',
            'template_json' => $this->templateJson(),
            'output_format' => 'json',
            'language' => 'de',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Shop Export')
            // Slug wird automatisch aus dem Namen generiert
            ->assertJsonPath('data.slug', 'shop-export')
            ->assertJsonStructure(['data', 'api_key_plain']);

        // Der Klartext-Key wird nur einmal zurückgegeben, gespeichert wird der SHA256-Hash
        $plainKey = $response->json('api_key_plain');
        $template = ApiTemplate::where('slug', 'shop-export')->firstOrFail();
        $this->assertSame(hash('sha256', $plainKey), $template->api_key);
        $this->assertSame($this->user->id, $template->user_id);
        $this->assertSame('sku', $template->template_json['groups'][0]['detail']['elements'][0]['field']);
    }

    public function test_store_validiert_pflichtfelder_und_enums(): void
    {
        $this->actingAs($this->user);

        // name + template_json fehlen
        $this->postJson('/api/v1/api-templates', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'template_json']);

        // Ungültige Enum-Werte
        $this->postJson('/api/v1/api-templates', [
            'name' => 'Test',
            'template_json' => $this->templateJson(),
            'direction' => 'sideways',
            'output_format' => 'xml',
            'auth_type' => 'oauth',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['direction', 'output_format', 'auth_type']);
    }

    public function test_store_validiert_eindeutigen_slug(): void
    {
        $this->actingAs($this->user);

        ApiTemplate::create([
            'name' => 'Vorhanden',
            'template_json' => $this->templateJson(),
            'slug' => 'mein-slug',
        ]);

        $this->postJson('/api/v1/api-templates', [
            'name' => 'Neu',
            'template_json' => $this->templateJson(),
            'slug' => 'mein-slug',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_index_listet_eigene_und_geteilte_templates(): void
    {
        $this->actingAs($this->user);

        $other = User::factory()->create();

        ApiTemplate::create(['name' => 'Eigenes', 'template_json' => $this->templateJson(), 'user_id' => $this->user->id, 'slug' => 'eigenes']);
        ApiTemplate::create(['name' => 'Geteilt', 'template_json' => $this->templateJson(), 'user_id' => $other->id, 'is_shared' => true, 'slug' => 'geteilt']);
        ApiTemplate::create(['name' => 'Fremd', 'template_json' => $this->templateJson(), 'user_id' => $other->id, 'is_shared' => false, 'slug' => 'fremd']);

        $response = $this->getJson('/api/v1/api-templates');

        $response->assertOk()->assertJsonCount(2, 'data');
        $names = array_column($response->json('data'), 'name');
        $this->assertEqualsCanonicalizing(['Eigenes', 'Geteilt'], $names);
    }

    public function test_show_liefert_template_und_verweigert_fremde(): void
    {
        $this->actingAs($this->user);

        $own = ApiTemplate::create(['name' => 'Eigenes', 'template_json' => $this->templateJson(), 'user_id' => $this->user->id, 'slug' => 'eigenes']);
        $this->getJson("/api/v1/api-templates/{$own->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Eigenes');

        // Nicht geteiltes Template eines anderen Nutzers → 403
        $other = User::factory()->create();
        $foreign = ApiTemplate::create(['name' => 'Fremd', 'template_json' => $this->templateJson(), 'user_id' => $other->id, 'slug' => 'fremd']);
        $this->getJson("/api/v1/api-templates/{$foreign->id}")->assertForbidden();
    }

    public function test_update_aendert_template(): void
    {
        $this->actingAs($this->user);

        $template = ApiTemplate::create([
            'name' => 'Alt',
            'template_json' => $this->templateJson(),
            'user_id' => $this->user->id,
            'slug' => 'alt',
        ]);

        $response = $this->putJson("/api/v1/api-templates/{$template->id}", [
            'name' => 'Neu',
            'direction' => 'bidirectional',
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Neu')
            ->assertJsonPath('data.direction', 'bidirectional')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('api_templates', ['id' => $template->id, 'name' => 'Neu']);
    }

    public function test_destroy_loescht_template(): void
    {
        $this->actingAs($this->user);

        $template = ApiTemplate::create([
            'name' => 'Wegwerf',
            'template_json' => $this->templateJson(),
            'user_id' => $this->user->id,
            'slug' => 'wegwerf',
        ]);

        $this->deleteJson("/api/v1/api-templates/{$template->id}")->assertNoContent();

        $this->assertDatabaseMissing('api_templates', ['id' => $template->id]);
    }

    public function test_fields_endpoint_liefert_feldpalette(): void
    {
        $this->actingAs($this->user);

        $attribute = Attribute::factory()->create(['technical_name' => 'farbe', 'name_de' => 'Farbe']);

        $response = $this->getJson('/api/v1/api-templates/fields');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'base_fields', 'attributes', 'group_fields', 'price_types', 'media_usage_types', 'relation_types',
            ]]);

        // Basisfelder enthalten sku
        $baseFields = array_column($response->json('data.base_fields'), 'field');
        $this->assertContains('sku', $baseFields);

        // Attribute aus der DB sind in der Palette
        $technicalNames = array_column($response->json('data.attributes'), 'technical_name');
        $this->assertContains('farbe', $technicalNames);
    }

    public function test_regenerate_key_erzeugt_neuen_api_key(): void
    {
        $this->actingAs($this->user);

        $template = ApiTemplate::create([
            'name' => 'Key Test',
            'template_json' => $this->templateJson(),
            'user_id' => $this->user->id,
            'slug' => 'key-test',
            'api_key' => hash('sha256', 'alter-key'),
        ]);

        $response = $this->postJson("/api/v1/api-templates/{$template->id}/regenerate-key");

        $response->assertOk()->assertJsonStructure(['data', 'api_key_plain']);

        $template->refresh();
        $this->assertSame(hash('sha256', $response->json('api_key_plain')), $template->api_key);
        $this->assertNotSame(hash('sha256', 'alter-key'), $template->api_key);
    }

    public function test_unauthentifizierte_zugriffe_erhalten_401(): void
    {
        // Kein actingAs — auth:sanctum greift vor der Modul-Middleware
        $this->getJson('/api/v1/api-templates')->assertUnauthorized();
        $this->postJson('/api/v1/api-templates', ['name' => 'X'])->assertUnauthorized();
        $this->getJson('/api/v1/api-templates/fields')->assertUnauthorized();
    }
}
