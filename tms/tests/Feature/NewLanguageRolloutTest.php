<?php

declare(strict_types=1);

namespace Tms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tms\Jobs\TranslateUnitJob;
use Tms\Models\TmsTranslation;
use Tms\Models\TmsUnit;
use Tms\Tests\TestCase;

/**
 * Deckt das Szenario ab, für das das TM eigentlich gebaut ist:
 * eine neue Zielsprache (hier Finnisch) wird hinzugefügt und der bestehende
 * Bestand soll darin übersetzt werden.
 */
class NewLanguageRolloutTest extends TestCase
{
    use RefreshDatabase;

    private const string API_KEY = 'test-api-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['tms.api_key' => self::API_KEY]);

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

    private function ingest(string $text = 'Gewicht', string $id = '11111111-1111-1111-1111-111111111111'): void
    {
        $this->postJson('/api/ingest', [
            'entities' => [[
                'entity_type' => 'attribute',
                'entity_id' => $id,
                'fields' => [['field' => 'name_de', 'text' => $text, 'lang' => 'de']],
            ]],
        ], $this->headers())->assertStatus(202);
    }

    // ─── Regression: neue Sprache muss nachgezogen werden ────────────────

    public function test_new_target_language_is_translated_on_next_ingest(): void
    {
        // Runde 1: Bestand mit en,fr aufbauen
        config(['tms.target_languages' => ['en', 'fr']]);
        Queue::fake([TranslateUnitJob::class]);
        $this->ingest();

        $unit = TmsUnit::firstOrFail();

        // Übersetzungen simulieren, als haette der Worker sie erledigt
        foreach (['en' => 'Weight', 'fr' => 'Poids'] as $lang => $text) {
            TmsTranslation::create([
                'tms_unit_id' => $unit->id,
                'target_lang' => $lang,
                'translation' => $text,
                'provider' => 'deepl',
                'status' => 'auto',
            ]);
        }

        // Runde 2: Finnisch kommt dazu, derselbe Bestand wird erneut ingestet.
        // Frueher passierte hier NICHTS, weil die Unit nicht mehr neu war.
        config(['tms.target_languages' => ['en', 'fr', 'fi']]);
        Queue::fake([TranslateUnitJob::class]);
        $this->ingest();

        Queue::assertPushed(TranslateUnitJob::class, 1);
        Queue::assertPushed(function (TranslateUnitJob $job) use ($unit) {
            $r = new \ReflectionClass($job);
            $langs = $r->getProperty('targetLangs')->getValue($job);
            $unitId = $r->getProperty('unitId')->getValue($job);

            // Nur die fehlende Sprache, nicht erneut en/fr
            return $unitId === $unit->id && $langs === ['fi'];
        });
    }

    public function test_ingest_does_not_requeue_when_nothing_is_missing(): void
    {
        config(['tms.target_languages' => ['en']]);
        Queue::fake([TranslateUnitJob::class]);
        $this->ingest();

        $unit = TmsUnit::firstOrFail();
        TmsTranslation::create([
            'tms_unit_id' => $unit->id,
            'target_lang' => 'en',
            'translation' => 'Weight',
            'provider' => 'deepl',
            'status' => 'auto',
        ]);

        Queue::fake([TranslateUnitJob::class]);
        $this->ingest();

        Queue::assertNotPushed(TranslateUnitJob::class);
    }

    public function test_source_language_is_never_queued_for_itself(): void
    {
        config(['tms.target_languages' => ['de', 'fi']]);
        Queue::fake([TranslateUnitJob::class]);

        $this->ingest();

        Queue::assertPushed(function (TranslateUnitJob $job) {
            $langs = (new \ReflectionClass($job))->getProperty('targetLangs')->getValue($job);
            return $langs === ['fi'];
        });
    }

    // ─── Massen-Rollout einer neuen Sprache ──────────────────────────────

    public function test_translate_missing_queues_units_without_that_language(): void
    {
        Queue::fake([TranslateUnitJob::class]);

        foreach (['Gewicht', 'Breite', 'Höhe'] as $i => $text) {
            TmsUnit::create([
                'source_lang' => 'de',
                'source_text' => $text,
                'text_hash' => hash('sha256', "de|{$text}"),
                'char_count' => mb_strlen($text),
            ]);
        }

        // Einer hat Finnisch schon
        $done = TmsUnit::where('source_text', 'Breite')->firstOrFail();
        TmsTranslation::create([
            'tms_unit_id' => $done->id,
            'target_lang' => 'fi',
            'translation' => 'Leveys',
            'provider' => 'deepl',
            'status' => 'auto',
        ]);

        $response = $this->postJson('/api/translate-missing', ['lang' => 'fi'], $this->headers())
            ->assertStatus(202);

        $this->assertSame(2, $response->json('dispatched'));
        $this->assertSame(0, $response->json('remaining'));

        Queue::assertPushed(TranslateUnitJob::class, 2);
        Queue::assertPushed(function (TranslateUnitJob $job) {
            $langs = (new \ReflectionClass($job))->getProperty('targetLangs')->getValue($job);
            return $langs === ['fi'];
        });
    }

    public function test_translate_missing_reports_remaining_so_caller_can_loop(): void
    {
        Queue::fake([TranslateUnitJob::class]);

        for ($i = 0; $i < 5; $i++) {
            TmsUnit::create([
                'source_lang' => 'de',
                'source_text' => "Begriff {$i}",
                'text_hash' => hash('sha256', "de|Begriff {$i}"),
                'char_count' => 9,
            ]);
        }

        $first = $this->postJson('/api/translate-missing', ['lang' => 'fi', 'limit' => 2], $this->headers())
            ->assertStatus(202);

        $this->assertSame(2, $first->json('dispatched'));
        $this->assertSame(3, $first->json('remaining'));
    }

    public function test_translate_missing_skips_units_whose_source_is_the_target(): void
    {
        Queue::fake([TranslateUnitJob::class]);

        TmsUnit::create([
            'source_lang' => 'fi',
            'source_text' => 'Paino',
            'text_hash' => hash('sha256', 'fi|Paino'),
            'char_count' => 5,
        ]);

        $response = $this->postJson('/api/translate-missing', ['lang' => 'fi'], $this->headers())
            ->assertStatus(202);

        $this->assertSame(0, $response->json('dispatched'));
        Queue::assertNotPushed(TranslateUnitJob::class);
    }

    // ─── Kostenschutz: derselbe Quelltext darf nicht mehrfach zahlen ─────

    public function test_repeated_source_text_is_queued_only_once_per_batch(): void
    {
        config(['tms.target_languages' => ['fi']]);
        Queue::fake([TranslateUnitJob::class]);

        // Derselbe Attributname an drei Entitäten — der Normalfall im PIM
        $this->postJson('/api/ingest', [
            'entities' => array_map(fn ($i) => [
                'entity_type' => 'attribute',
                'entity_id' => str_repeat((string) $i, 8) . '-1111-1111-1111-111111111111',
                'fields' => [['field' => 'name_de', 'text' => 'Gewicht', 'lang' => 'de']],
            ], [1, 2, 3]),
        ], $this->headers())->assertStatus(202);

        $this->assertSame(1, TmsUnit::count());
        Queue::assertPushed(TranslateUnitJob::class, 1);
    }

    public function test_translation_is_not_redone_when_it_already_exists(): void
    {
        // Zweite Verteidigungslinie: selbst wenn ein Job doppelt in der Queue
        // landet, darf er den MT-Anbieter nicht erneut beauftragen.
        $unit = TmsUnit::create([
            'source_lang' => 'de',
            'source_text' => 'Gewicht',
            'text_hash' => hash('sha256', 'de|Gewicht'),
            'char_count' => 7,
        ]);

        TmsTranslation::create([
            'tms_unit_id' => $unit->id,
            'target_lang' => 'fi',
            'translation' => 'Paino',
            'provider' => 'deepl',
            'status' => 'auto',
        ]);

        \Illuminate\Support\Facades\Http::fake();

        (new TranslateUnitJob($unit->id, ['fi']))
            ->handle(app(\Tms\Services\TranslationProviders\ProviderChain::class));

        \Illuminate\Support\Facades\Http::assertNothingSent();
        $this->assertSame('Paino', $unit->translations()->where('target_lang', 'fi')->value('translation'));
    }

    public function test_force_allows_retranslation_of_machine_output(): void
    {
        config([
            'tms.provider_chain' => ['deepl'],
            'tms.providers.deepl.api_key' => 'k',
            'tms.providers.deepl.api_url' => 'https://api-free.deepl.com/v2',
        ]);

        $unit = TmsUnit::create([
            'source_lang' => 'de',
            'source_text' => 'Gewicht',
            'text_hash' => hash('sha256', 'de|Gewicht'),
            'char_count' => 7,
        ]);

        TmsTranslation::create([
            'tms_unit_id' => $unit->id,
            'target_lang' => 'fi',
            'translation' => 'ALT',
            'provider' => 'deepl',
            'status' => 'auto',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            '*deepl*' => \Illuminate\Support\Facades\Http::response(['translations' => [['text' => 'NEU']]]),
        ]);

        (new TranslateUnitJob($unit->id, ['fi'], true))
            ->handle(app(\Tms\Services\TranslationProviders\ProviderChain::class));

        $this->assertSame('NEU', $unit->translations()->where('target_lang', 'fi')->value('translation'));
    }

    public function test_reviewed_translations_survive_even_with_force(): void
    {
        config([
            'tms.provider_chain' => ['deepl'],
            'tms.providers.deepl.api_key' => 'k',
        ]);

        $unit = TmsUnit::create([
            'source_lang' => 'de',
            'source_text' => 'Gewicht',
            'text_hash' => hash('sha256', 'de|Gewicht'),
            'char_count' => 7,
        ]);

        TmsTranslation::create([
            'tms_unit_id' => $unit->id,
            'target_lang' => 'fi',
            'translation' => 'Von Hand',
            'provider' => 'human',
            'status' => 'reviewed',
        ]);

        \Illuminate\Support\Facades\Http::fake();

        (new TranslateUnitJob($unit->id, ['fi'], true))
            ->handle(app(\Tms\Services\TranslationProviders\ProviderChain::class));

        \Illuminate\Support\Facades\Http::assertNothingSent();
        $this->assertSame('Von Hand', $unit->translations()->where('target_lang', 'fi')->value('translation'));
    }

    public function test_translate_missing_requires_a_language(): void
    {
        $this->postJson('/api/translate-missing', [], $this->headers())->assertStatus(422);
    }

    public function test_translate_missing_is_idempotent(): void
    {
        Queue::fake([TranslateUnitJob::class]);

        TmsUnit::create([
            'source_lang' => 'de',
            'source_text' => 'Gewicht',
            'text_hash' => hash('sha256', 'de|Gewicht'),
            'char_count' => 7,
        ]);

        $a = $this->postJson('/api/translate-missing', ['lang' => 'fi'], $this->headers());
        $b = $this->postJson('/api/translate-missing', ['lang' => 'fi'], $this->headers());

        // Solange der Worker nichts geschrieben hat, bleibt die Unit offen —
        // ein wiederholter Aufruf plant sie erneut ein, statt zu scheitern.
        $this->assertSame(1, $a->json('dispatched'));
        $this->assertSame(1, $b->json('dispatched'));
    }
}
