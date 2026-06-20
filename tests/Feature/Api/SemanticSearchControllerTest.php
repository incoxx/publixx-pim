<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature-Tests für die semantische Schnellsuche (SemanticSearchController).
 *
 * POST /semantic-search        → Hybrid-Suche (Meilisearch, hier per Http::fake gemockt)
 * GET  /semantic-search/health → Index-/Embedder-Status
 *
 * Meilisearch wird nie real angesprochen — der MeilisearchClient nutzt die
 * Laravel-Http-Facade, daher genügt Http::fake().
 */
class SemanticSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://meilisearch.test:7700';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->user = User::factory()->create();
        $role = Role::findOrCreate('Admin', 'sanctum');
        $this->user->assignRole($role);

        config([
            'meilisearch.enabled' => true,
            'meilisearch.host' => self::HOST,
            'meilisearch.index' => 'products',
            'meilisearch.embedder.enabled' => true,
        ]);

        // Sicherheitsnetz: kein realer HTTP-Verkehr
        Http::preventStrayRequests();
    }

    /** Als Admin authentifizieren (nicht im setUp, damit der 401-Test möglich ist). */
    private function login(): void
    {
        $this->actingAs($this->user);
    }

    /** Erfolgreiche Meilisearch-Suchantwort faken. */
    private function fakeSearchResponse(array $hits = [], int $total = 0): void
    {
        Http::fake([
            self::HOST . '/indexes/products/search' => Http::response([
                'hits' => $hits,
                'estimatedTotalHits' => $total,
                'processingTimeMs' => 7,
            ]),
        ]);
    }

    // ── Auth & Verfügbarkeit ──────────────────────────────────────────

    public function test_suche_erfordert_authentifizierung(): void
    {
        $this->postJson('/api/v1/semantic-search', ['q' => 'test'])->assertStatus(401);
        $this->getJson('/api/v1/semantic-search/health')->assertStatus(401);
    }

    public function test_suche_verweigert_ohne_permission(): void
    {
        // Nutzer ohne 'semantic-search.view' (eigene Rolle ohne Permissions)
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Gast', 'sanctum'));
        $this->actingAs($user);

        $this->postJson('/api/v1/semantic-search', ['q' => 'test'])->assertStatus(403);
        $this->getJson('/api/v1/semantic-search/health')->assertStatus(403);
    }

    public function test_suche_liefert_503_wenn_meilisearch_deaktiviert(): void
    {
        $this->login();
        config(['meilisearch.enabled' => false]);

        $this->postJson('/api/v1/semantic-search', ['q' => 'test'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Semantische Suche ist nicht aktiviert (MEILISEARCH_ENABLED).');
    }

    public function test_validierungsfehler_bei_ungueltigen_parametern(): void
    {
        $this->login();

        $this->postJson('/api/v1/semantic-search', ['mode' => 'magie'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mode');

        $this->postJson('/api/v1/semantic-search', ['semantic_ratio' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('semantic_ratio');
    }

    // ── POST /semantic-search ─────────────────────────────────────────

    public function test_hybridsuche_liefert_gerankte_treffer(): void
    {
        $this->login();
        $this->fakeSearchResponse([
            [
                'id' => 'prod-1',
                'sku' => 'AK-100',
                'name_de' => 'Akkubohrer Professional',
                '_rankingScore' => 0.92,
            ],
        ], 1);

        $response = $this->postJson('/api/v1/semantic-search', [
            'q' => 'starker Akkubohrer',
        ]);

        $response->assertOk()
            ->assertJsonPath('query', 'starker Akkubohrer')
            ->assertJsonPath('mode', 'hybrid')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('hits.0.id', 'prod-1')
            ->assertJsonPath('hits.0.score', 0.92)
            ->assertJsonMissingPath('hits.0._rankingScore');

        // Hybrid-Parameter und Status-Filter müssen an Meilisearch gehen
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/indexes/products/search')
                && isset($request['hybrid']['embedder'])
                && str_contains((string) ($request['filter'] ?? ''), 'status = "active"');
        });
    }

    public function test_keyword_modus_wenn_embedder_deaktiviert(): void
    {
        $this->login();
        config(['meilisearch.embedder.enabled' => false]);
        $this->fakeSearchResponse();

        $response = $this->postJson('/api/v1/semantic-search', ['q' => 'Akkubohrer']);

        $response->assertOk()->assertJsonPath('mode', 'keyword');

        Http::assertSent(fn ($request) => !isset($request['hybrid']));
    }

    public function test_leere_query_fuehrt_zu_reinem_filter_modus(): void
    {
        $this->login();
        $this->fakeSearchResponse();

        $response = $this->postJson('/api/v1/semantic-search', [
            'q' => '',
            'filters' => [
                ['attribute' => 'list_price', 'operator' => 'lte', 'value' => 300],
            ],
        ]);

        $response->assertOk()->assertJsonPath('mode', 'filter');

        Http::assertSent(function ($request) {
            return str_contains((string) ($request['filter'] ?? ''), 'list_price <= 300');
        });
    }

    public function test_unbekanntes_filter_attribut_liefert_422(): void
    {
        $this->login();
        Http::fake(); // darf nicht aufgerufen werden

        $this->postJson('/api/v1/semantic-search', [
            'q' => 'Bohrer',
            'filters' => [
                ['attribute' => 'gibt-es-nicht', 'operator' => 'eq', 'value' => 'x'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unbekanntes Filter-Attribut: gibt-es-nicht');
    }

    public function test_meilisearch_ausfall_liefert_sauberen_503(): void
    {
        $this->login();
        Http::fake([
            self::HOST . '/indexes/products/search' => Http::response('Server Error', 500),
        ]);

        $this->postJson('/api/v1/semantic-search', ['q' => 'Akkubohrer'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Suche derzeit nicht verfügbar.');
    }

    // ── GET /semantic-search/health ───────────────────────────────────

    public function test_health_meldet_deaktivierte_suche(): void
    {
        $this->login();
        config(['meilisearch.enabled' => false]);

        $this->getJson('/api/v1/semantic-search/health')
            ->assertOk()
            ->assertJson(['enabled' => false, 'healthy' => false]);
    }

    public function test_health_meldet_erreichbaren_index(): void
    {
        $this->login();
        Http::fake([
            self::HOST . '/health' => Http::response(['status' => 'available']),
            self::HOST . '/indexes/products/stats' => Http::response([
                'numberOfDocuments' => 42,
                'isIndexing' => false,
            ]),
        ]);

        $this->getJson('/api/v1/semantic-search/health')
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('healthy', true)
            ->assertJsonPath('index', 'products')
            ->assertJsonPath('documents', 42)
            ->assertJsonPath('indexing', false);
    }

    public function test_health_meldet_nicht_erreichbares_meilisearch(): void
    {
        $this->login();
        Http::fake([
            self::HOST . '/health' => Http::response('', 500),
        ]);

        $this->getJson('/api/v1/semantic-search/health')
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('healthy', false)
            ->assertJsonPath('documents', null);
    }
}
