<?php

declare(strict_types=1);

namespace Tms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tms\Jobs\TranslateUnitJob;
use Tms\Models\TmsTranslation;
use Tms\Models\TmsUnit;
use Tms\Models\TmsUsage;
use Tms\Tests\TestCase;

/**
 * Deckt den Kernpfad des Translation Memory ab: PIM schickt Entitäten (ingest),
 * das TMS legt Units/Usages an, und resolve() findet sie über den Hash wieder.
 *
 * Redis wird gemockt — die Tests sollen ohne laufenden Redis-Server durchlaufen.
 */
class IngestResolveRoundtripTest extends TestCase
{
    use RefreshDatabase;

    private const string API_KEY = 'test-api-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['tms.api_key' => self::API_KEY]);

        // Redis ist im Test nicht verfügbar: alle Schreibzugriffe ins Leere,
        // alle Lesezugriffe als Cache-Miss — damit greift der DB-Fallback.
        Redis::shouldReceive('setex')->andReturnTrue()->byDefault();
        Redis::shouldReceive('get')->andReturnNull()->byDefault();
        Redis::shouldReceive('mget')->andReturnUsing(
            fn (array $keys) => array_fill(0, count($keys), null)
        )->byDefault();
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . self::API_KEY];
    }

    // ─── Authentifizierung ───────────────────────────────────────────────

    public function test_request_without_token_is_rejected(): void
    {
        $this->getJson('/api/stats')->assertStatus(401);
    }

    public function test_request_with_wrong_token_is_rejected(): void
    {
        $this->getJson('/api/stats', ['Authorization' => 'Bearer falsch'])
            ->assertStatus(401);
    }

    public function test_request_with_correct_token_is_accepted(): void
    {
        $this->getJson('/api/stats', $this->headers())->assertOk();
    }

    // ─── Ingest ──────────────────────────────────────────────────────────

    public function test_ingest_creates_units_and_usages(): void
    {
        Queue::fake([TranslateUnitJob::class]);

        $this->postJson('/api/ingest', [
            'entities' => [[
                'entity_type' => 'attribute',
                'entity_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                'entity_label' => 'gewicht',
                'fields' => [
                    ['field' => 'name_de', 'text' => 'Gewicht', 'lang' => 'de'],
                ],
            ]],
        ], $this->headers())->assertStatus(202);

        $unit = TmsUnit::where('source_text', 'Gewicht')->first();

        $this->assertNotNull($unit);
        $this->assertSame('de', $unit->source_lang);
        $this->assertSame('attribute', $unit->domain);
        $this->assertSame(7, $unit->char_count);
        $this->assertSame(
            hash('sha256', 'de|Gewicht'),
            $unit->text_hash,
            'Der Hash muss dem Vertrag mit dem PIM entsprechen.',
        );

        $this->assertDatabaseHas('tms_usages', [
            'tms_unit_id' => $unit->id,
            'entity_type' => 'attribute',
            'field_name' => 'name_de',
            'entity_label' => 'gewicht',
        ]);

        Queue::assertPushed(TranslateUnitJob::class);
    }

    public function test_ingest_deduplicates_identical_source_texts(): void
    {
        Queue::fake([TranslateUnitJob::class]);

        $payload = fn (string $id) => [
            'entity_type' => 'attribute',
            'entity_id' => $id,
            'fields' => [['field' => 'name_de', 'text' => 'Gewicht', 'lang' => 'de']],
        ];

        $this->postJson('/api/ingest', [
            'entities' => [
                $payload('11111111-1111-1111-1111-111111111111'),
                $payload('22222222-2222-2222-2222-222222222222'),
            ],
        ], $this->headers())->assertStatus(202);

        // Ein Begriff, aber zwei Verwendungsstellen
        $this->assertSame(1, TmsUnit::count());
        $this->assertSame(2, TmsUsage::count());

        // Übersetzt wird nur einmal — das ist der Sinn des Translation Memory
        Queue::assertPushed(TranslateUnitJob::class, 1);
    }

    public function test_ingest_rejects_entity_without_fields(): void
    {
        // Regression: der PIM-seitige IngestToTmsJob hat feldlose Entitäten
        // mitgeschickt und damit ganze 200er-Batches per 422 verloren.
        $this->postJson('/api/ingest', [
            'entities' => [[
                'entity_type' => 'attribute',
                'entity_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                'fields' => [],
            ]],
        ], $this->headers())->assertStatus(422);

        $this->assertSame(0, TmsUnit::count());
    }

    // ─── Resolve ─────────────────────────────────────────────────────────

    public function test_resolve_finds_translation_via_db_fallback(): void
    {
        $unit = TmsUnit::create([
            'source_lang' => 'de',
            'source_text' => 'Gewicht',
            'text_hash' => hash('sha256', 'de|Gewicht'),
            'domain' => 'attribute',
            'char_count' => 7,
        ]);

        TmsTranslation::create([
            'tms_unit_id' => $unit->id,
            'target_lang' => 'en',
            'translation' => 'Weight',
            'provider' => 'deepl',
            'status' => 'auto',
            'confidence' => 0.95,
        ]);

        $response = $this->getJson(
            '/api/resolve?hashes=' . $unit->text_hash . '&langs=en,fr',
            $this->headers(),
        )->assertOk();

        $this->assertSame('Weight', $response->json("{$unit->text_hash}.en"));
        $this->assertNull($response->json("{$unit->text_hash}.fr"));
    }

    public function test_resolve_returns_null_for_unknown_hash(): void
    {
        $response = $this->getJson(
            '/api/resolve?hashes=' . str_repeat('a', 64) . '&langs=en',
            $this->headers(),
        )->assertOk();

        $this->assertNull($response->json(str_repeat('a', 64) . '.en'));
    }

    public function test_full_roundtrip_ingest_then_resolve(): void
    {
        Queue::fake([TranslateUnitJob::class]);

        $this->postJson('/api/ingest', [
            'entities' => [[
                'entity_type' => 'attribute',
                'entity_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                'fields' => [['field' => 'name_de', 'text' => 'Gewicht', 'lang' => 'de']],
            ]],
        ], $this->headers())->assertStatus(202);

        $unit = TmsUnit::firstOrFail();

        TmsTranslation::create([
            'tms_unit_id' => $unit->id,
            'target_lang' => 'fr',
            'translation' => 'Poids',
            'provider' => 'deepl',
            'status' => 'auto',
        ]);

        // Das PIM berechnet den Hash unabhängig neu — genau so muss er passen
        $pimHash = hash('sha256', 'de|Gewicht');

        $response = $this->getJson("/api/resolve?hashes={$pimHash}&langs=fr", $this->headers())
            ->assertOk();

        $this->assertSame('Poids', $response->json("{$pimHash}.fr"));
    }

    // ─── Import (Übersetzungs-Jobs) ──────────────────────────────────────

    public function test_import_accepts_openai_as_provider(): void
    {
        // Regression B-8: 'openai' fehlte im provider-ENUM, jede von OpenAI
        // gelieferte Übersetzung lief in einen Data-Truncated-Fehler.
        $this->postJson('/api/import-translations', [
            'items' => [[
                'source_text' => 'Gewicht',
                'source_lang' => 'de',
                'target_lang' => 'en',
                'translated_text' => 'Weight',
                'entity_type' => 'attribute',
                'entity_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                'provider' => 'openai',
            ]],
        ], $this->headers())->assertOk();

        $this->assertDatabaseHas('tms_translations', [
            'target_lang' => 'en',
            'translation' => 'Weight',
            'provider' => 'openai',
        ]);
    }

    public function test_import_rejects_unknown_provider(): void
    {
        $this->postJson('/api/import-translations', [
            'items' => [[
                'source_text' => 'Gewicht',
                'source_lang' => 'de',
                'target_lang' => 'en',
                'translated_text' => 'Weight',
                'entity_type' => 'attribute',
                'entity_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                'provider' => 'nicht-existent',
            ]],
        ], $this->headers())->assertStatus(422);
    }

    public function test_import_uses_the_same_hash_as_ingest(): void
    {
        $this->postJson('/api/import-translations', [
            'items' => [[
                'source_text' => 'Gewicht',
                'source_lang' => 'de',
                'target_lang' => 'en',
                'translated_text' => 'Weight',
                'entity_type' => 'attribute',
                'entity_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            ]],
        ], $this->headers())->assertOk();

        $this->assertDatabaseHas('tms_units', [
            'text_hash' => hash('sha256', 'de|Gewicht'),
        ]);
    }

    // ─── Manuelle Pflege ─────────────────────────────────────────────────

    public function test_manual_translation_is_marked_reviewed(): void
    {
        $unit = TmsUnit::create([
            'source_lang' => 'de',
            'source_text' => 'Gewicht',
            'text_hash' => hash('sha256', 'de|Gewicht'),
            'char_count' => 7,
        ]);

        $this->putJson("/api/units/{$unit->id}/translations/fr", [
            'translation' => 'Poids',
        ], $this->headers())->assertOk();

        $this->assertDatabaseHas('tms_translations', [
            'tms_unit_id' => $unit->id,
            'target_lang' => 'fr',
            'translation' => 'Poids',
            'provider' => 'human',
            'status' => 'reviewed',
        ]);
    }

    public function test_stats_reports_coverage_per_language(): void
    {
        config(['tms.target_languages' => ['en', 'fr']]);

        $unit = TmsUnit::create([
            'source_lang' => 'de',
            'source_text' => 'Gewicht',
            'text_hash' => hash('sha256', 'de|Gewicht'),
            'char_count' => 7,
        ]);

        TmsTranslation::create([
            'tms_unit_id' => $unit->id,
            'target_lang' => 'en',
            'translation' => 'Weight',
            'provider' => 'deepl',
            'status' => 'auto',
        ]);

        $response = $this->getJson('/api/stats', $this->headers())->assertOk();

        $this->assertSame(1, $response->json('total_units'));
        $this->assertSame(1, $response->json('languages.en.translated'));
        // coverage kommt als 100 (int) durch json_encode zurueck, nicht als 100.0
        $this->assertEquals(100, $response->json('languages.en.coverage'));
        $this->assertSame(0, $response->json('languages.fr.translated'));
        $this->assertSame(1, $response->json('languages.fr.missing'));
    }

    public function test_stats_excludes_units_whose_source_language_is_the_target(): void
    {
        // Regression H-11: der Ingest schickt auch name_en als eigene Unit mit
        // source_lang='en'. TranslateUnitJob überspringt source === target, die
        // Unit galt in der Statistik aber trotzdem als "fehlend" — die
        // en-Abdeckung konnte damit nie 100 % erreichen.
        config(['tms.target_languages' => ['en', 'fr']]);

        $de = TmsUnit::create([
            'source_lang' => 'de',
            'source_text' => 'Gewicht',
            'text_hash' => hash('sha256', 'de|Gewicht'),
            'char_count' => 7,
        ]);

        TmsUnit::create([
            'source_lang' => 'en',
            'source_text' => 'Weight',
            'text_hash' => hash('sha256', 'en|Weight'),
            'char_count' => 6,
        ]);

        TmsTranslation::create([
            'tms_unit_id' => $de->id,
            'target_lang' => 'en',
            'translation' => 'Weight',
            'provider' => 'deepl',
            'status' => 'auto',
        ]);

        $response = $this->getJson('/api/stats', $this->headers())->assertOk();

        // Zwei Units insgesamt, aber nur eine ist ins Englische zu übersetzen
        $this->assertSame(2, $response->json('total_units'));
        $this->assertSame(1, $response->json('languages.en.total'));
        $this->assertSame(0, $response->json('languages.en.missing'));
        $this->assertEquals(100, $response->json('languages.en.coverage'));

        // Für Französisch sind beide Units offen
        $this->assertSame(2, $response->json('languages.fr.total'));
        $this->assertSame(2, $response->json('languages.fr.missing'));
    }
}
