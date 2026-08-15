<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\IngestToTmsJob;
use App\Services\Tms\TmsClient;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regressionstests für die Batch-Bildung des Ingests.
 *
 * Die beiden geprüften Methoden sind privat und haben keine andere Naht — der
 * Rest des Jobs iteriert über zehn Eloquent-Modelle und bräuchte eine
 * vollständige Datenbank. Der Zugriff per Reflection ist hier bewusst gewählt:
 * genau in diesen beiden Methoden saß der Fehler, durch den ganze 200er-Batches
 * still verloren gingen.
 */
class IngestToTmsJobTest extends TestCase
{
    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(IngestToTmsJob::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs(new IngestToTmsJob(), $args);
    }

    private function client(): TmsClient
    {
        config([
            'tms.enabled' => true,
            'tms.base_url' => 'http://tms.test/api',
            'tms.api_key' => 'test-key',
        ]);

        return new TmsClient();
    }

    // ─── buildEntity ─────────────────────────────────────────────────────

    public function test_build_entity_drops_empty_fields(): void
    {
        $entity = $this->callPrivate('buildEntity', [
            'attribute', 'uuid-1', 'gewicht',
            [
                ['field' => 'name_de', 'text' => 'Gewicht', 'lang' => 'de'],
                ['field' => 'name_en', 'text' => '', 'lang' => 'en'],
            ],
        ]);

        $this->assertCount(1, $entity['fields']);
        $this->assertSame('name_de', $entity['fields'][0]['field']);
    }

    public function test_build_entity_reindexes_fields_after_filtering(): void
    {
        // Regression: array_filter erhält die Schlüssel. Fiel das ERSTE Feld
        // weg, entstand [1 => ...] und json_encode machte daraus ein Objekt
        // {"1": ...} statt einer Liste — eine stille Abweichung vom API-Vertrag.
        $entity = $this->callPrivate('buildEntity', [
            'attribute', 'uuid-1', 'gewicht',
            [
                ['field' => 'name_de', 'text' => '', 'lang' => 'de'],
                ['field' => 'name_en', 'text' => 'Weight', 'lang' => 'en'],
            ],
        ]);

        $this->assertSame([0], array_keys($entity['fields']));
        $this->assertStringContainsString(
            '"fields":[{',
            json_encode($entity, JSON_UNESCAPED_UNICODE),
            'fields muss als JSON-Array serialisieren, nicht als Objekt.',
        );
    }

    public function test_build_entity_with_no_usable_text_yields_empty_fields(): void
    {
        $entity = $this->callPrivate('buildEntity', [
            'hierarchy_node', 'uuid-9', 'knoten',
            [
                ['field' => 'name_de', 'text' => null, 'lang' => 'de'],
                ['field' => 'name_en', 'text' => '', 'lang' => 'en'],
            ],
        ]);

        $this->assertSame([], $entity['fields']);
    }

    // ─── sendBatches ─────────────────────────────────────────────────────

    public function test_send_batches_filters_field_less_entities(): void
    {
        // Der Kern des Fehlers: der IngestController validiert
        // entities.*.fields mit required|array|min:1. Eine einzige feldlose
        // Entität liess den kompletten Batch mit 422 scheitern und der
        // TmsClient verschluckte das mit einer blossen Log-Warnung.
        Http::fake(['*/ingest' => Http::response(['count' => 1], 202)]);

        $entities = [
            ['entity_type' => 'attribute', 'entity_id' => 'a', 'fields' => [['field' => 'name_de', 'text' => 'A']]],
            ['entity_type' => 'attribute', 'entity_id' => 'b', 'fields' => []],
            ['entity_type' => 'attribute', 'entity_id' => 'c', 'fields' => [['field' => 'name_de', 'text' => 'C']]],
        ];

        $sent = $this->callPrivate('sendBatches', [$this->client(), $entities, 200]);

        $this->assertSame(2, $sent);

        Http::assertSent(function ($request) {
            $ids = array_column($request['entities'], 'entity_id');
            return $ids === ['a', 'c'];
        });
    }

    public function test_send_batches_reindexes_so_payload_is_a_json_array(): void
    {
        Http::fake(['*/ingest' => Http::response([], 202)]);

        // Die feldlose Entität steht an Position 0 — ohne array_values bliebe
        // der Schlüssel 1 stehen und entities würde als Objekt serialisiert.
        $entities = [
            ['entity_type' => 'attribute', 'entity_id' => 'leer', 'fields' => []],
            ['entity_type' => 'attribute', 'entity_id' => 'voll', 'fields' => [['field' => 'name_de', 'text' => 'A']]],
        ];

        $this->callPrivate('sendBatches', [$this->client(), $entities, 200]);

        Http::assertSent(fn ($request) => array_keys($request['entities']) === [0]);
    }

    public function test_send_batches_chunks_at_batch_size(): void
    {
        Http::fake(['*/ingest' => Http::response([], 202)]);

        $entities = array_fill(0, 450, [
            'entity_type' => 'attribute',
            'entity_id' => 'x',
            'fields' => [['field' => 'name_de', 'text' => 'A']],
        ]);

        $sent = $this->callPrivate('sendBatches', [$this->client(), $entities, 200]);

        $this->assertSame(450, $sent);
        Http::assertSentCount(3);
    }

    public function test_send_batches_sends_nothing_when_all_entities_are_empty(): void
    {
        Http::fake();

        $entities = [
            ['entity_type' => 'attribute', 'entity_id' => 'a', 'fields' => []],
            ['entity_type' => 'attribute', 'entity_id' => 'b', 'fields' => []],
        ];

        $sent = $this->callPrivate('sendBatches', [$this->client(), $entities, 200]);

        $this->assertSame(0, $sent);
        Http::assertNothingSent();
    }

    public function test_send_batches_counts_only_what_was_sent(): void
    {
        Http::fake(['*/ingest' => Http::response([], 202)]);

        $entities = array_merge(
            array_fill(0, 10, ['entity_type' => 'attribute', 'entity_id' => 'x', 'fields' => [['field' => 'n', 'text' => 'A']]]),
            array_fill(0, 5, ['entity_type' => 'attribute', 'entity_id' => 'y', 'fields' => []]),
        );

        $this->assertSame(10, $this->callPrivate('sendBatches', [$this->client(), $entities, 200]));
    }
}
