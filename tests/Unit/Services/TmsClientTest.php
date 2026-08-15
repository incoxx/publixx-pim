<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Tms\TmsClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TmsClientTest extends TestCase
{
    private function client(bool $enabled = true): TmsClient
    {
        config([
            'tms.enabled' => $enabled,
            'tms.base_url' => 'http://tms.test/api',
            'tms.api_key' => 'test-key',
            'tms.timeout' => 2,
        ]);

        // Der Client liest die Config im Konstruktor
        return new TmsClient();
    }

    // ─── Feature-Flag ────────────────────────────────────────────────────

    public function test_is_enabled_reflects_config(): void
    {
        $this->assertTrue($this->client(true)->isEnabled());
        $this->assertFalse($this->client(false)->isEnabled());
    }

    public function test_no_request_is_sent_when_disabled(): void
    {
        Http::fake();

        $client = $this->client(false);

        $this->assertSame([], $client->resolve(['abc'], ['en']));
        $client->ingest([['entity_type' => 'attribute']]);
        $this->assertSame([], $client->getStats());
        $this->assertSame([], $client->importTranslations([['source_text' => 'x']]));

        Http::assertNothingSent();
    }

    // ─── resolve ─────────────────────────────────────────────────────────

    public function test_resolve_sends_hashes_and_langs_as_csv(): void
    {
        Http::fake([
            '*/resolve*' => Http::response(['hash1' => ['en' => 'Weight']]),
        ]);

        $result = $this->client()->resolve(['hash1', 'hash2'], ['en', 'fr']);

        $this->assertSame(['hash1' => ['en' => 'Weight']], $result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/resolve')
                && $request['hashes'] === 'hash1,hash2'
                && $request['langs'] === 'en,fr'
                && $request->header('Authorization')[0] === 'Bearer test-key';
        });
    }

    public function test_resolve_returns_empty_array_without_hashes(): void
    {
        Http::fake();

        $this->assertSame([], $this->client()->resolve([], ['en']));

        Http::assertNothingSent();
    }

    public function test_resolve_swallows_server_errors(): void
    {
        Http::fake(['*/resolve*' => Http::response('boom', 500)]);

        $this->assertSame([], $this->client()->resolve(['hash1'], ['en']));
    }

    public function test_resolve_swallows_connection_errors(): void
    {
        Http::fake(fn () => throw new \RuntimeException('Connection refused'));

        $this->assertSame([], $this->client()->resolve(['hash1'], ['en']));
    }

    // ─── ingest ──────────────────────────────────────────────────────────

    public function test_ingest_posts_entities_wrapped(): void
    {
        Http::fake(['*/ingest' => Http::response(['count' => 1], 202)]);

        $this->client()->ingest([
            ['entity_type' => 'attribute', 'entity_id' => 'uuid-1', 'fields' => []],
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/ingest')
                && $request['entities'][0]['entity_type'] === 'attribute';
        });
    }

    public function test_ingest_skips_empty_payload(): void
    {
        Http::fake();

        $this->client()->ingest([]);

        Http::assertNothingSent();
    }

    public function test_ingest_reports_success_and_failure(): void
    {
        // Der Rueckgabewert ist die einzige Naht, an der der Aufrufer einen
        // fehlgeschlagenen Ingest bemerken kann — Exceptions werden hier
        // bewusst verschluckt, damit das PIM weiterlaeuft.
        $entities = [['entity_type' => 'attribute', 'entity_id' => 'uuid-1', 'fields' => []]];

        // Sequenz statt mehrfachem Http::fake(): ein zweiter fake()-Aufruf
        // ersetzt den ersten Stub nicht, der bereits registrierte greift weiter.
        Http::fakeSequence('*/ingest')
            ->push(['count' => 1], 202)
            ->push(['message' => 'Unauthorized'], 401)
            ->push(['message' => 'boom'], 500);

        $client = $this->client();

        $this->assertTrue($client->ingest($entities), '202 muss als Erfolg gelten.');
        $this->assertFalse($client->ingest($entities), '401 muss als Fehlschlag gemeldet werden.');
        $this->assertFalse($client->ingest($entities), '500 muss als Fehlschlag gemeldet werden.');
    }

    // ─── importTranslations ──────────────────────────────────────────────

    public function test_import_translations_chunks_at_200_items(): void
    {
        Http::fake(['*/import-translations' => Http::response(['imported' => 200])]);

        $items = array_fill(0, 450, [
            'source_text' => 'Gewicht',
            'source_lang' => 'de',
            'target_lang' => 'en',
            'translated_text' => 'Weight',
            'entity_type' => 'attribute',
            'entity_id' => 'uuid-1',
        ]);

        $this->client()->importTranslations($items);

        // 450 Einträge → 200 + 200 + 50
        Http::assertSentCount(3);

        $sizes = [];
        Http::assertSent(function ($request) use (&$sizes) {
            $sizes[] = count($request['items']);
            return true;
        });

        $this->assertSame([200, 200, 50], $sizes);
    }

    // ─── Sonstige Endpunkte ──────────────────────────────────────────────

    public function test_update_translation_puts_to_correct_path(): void
    {
        Http::fake(['*' => Http::response(['status' => 'reviewed'])]);

        $this->client()->updateTranslation('unit-1', 'fr', ['translation' => 'Poids']);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/units/unit-1/translations/fr')
                && $request['translation'] === 'Poids';
        });
    }

    public function test_purge_all_units_sends_delete(): void
    {
        Http::fake(['*' => Http::response(['deleted' => 5])]);

        $result = $this->client()->purgeAllUnits();

        $this->assertSame(['deleted' => 5], $result);

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/units'));
    }

    public function test_base_url_trailing_slash_is_normalised(): void
    {
        config([
            'tms.enabled' => true,
            'tms.base_url' => 'http://tms.test/api/',
            'tms.api_key' => 'test-key',
        ]);

        Http::fake(['*' => Http::response([])]);

        (new TmsClient())->getStats();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'http://tms.test/api/stats'));
    }
}
