<?php

declare(strict_types=1);

namespace Tms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tms\Jobs\ProcessIngestBatchJob;
use Tms\Jobs\TranslateUnitJob;
use Tms\Models\TmsTranslation;
use Tms\Models\TmsUnit;
use Tms\Tests\TestCase;

/**
 * Terminologie-Kennzeichen: "nicht uebersetzen" und Synonyme.
 *
 * Beide sind nicht nur Dokumentation — sie steuern, ob ein Begriff Geld kostet
 * (maschinelle Uebersetzung je Zielsprache) und was das PIM beim Aufloesen
 * zurueckbekommt. Genau das pruefen diese Tests.
 */
class TerminologyTest extends TestCase
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
        Redis::shouldReceive('scan')->andReturn(['0', []])->byDefault();
        Redis::shouldReceive('del')->andReturnTrue()->byDefault();
    }

    private function unit(array $attributes = []): TmsUnit
    {
        return TmsUnit::create(array_merge([
            'source_lang' => 'de',
            'source_text' => 'Gewicht',
            'text_hash' => hash('sha256', 'de|Gewicht'),
            'domain' => 'attribute',
            'char_count' => 7,
        ], $attributes));
    }

    // ─── needsTranslation ────────────────────────────────────────────────

    public function test_normal_unit_needs_translation(): void
    {
        $this->assertTrue($this->unit()->needsTranslation());
    }

    public function test_do_not_translate_unit_needs_no_translation(): void
    {
        $this->assertFalse($this->unit(['do_not_translate' => true])->needsTranslation());
    }

    public function test_synonym_needs_no_translation(): void
    {
        $preferred = $this->unit();
        $synonym = $this->unit([
            'source_text' => 'Masse',
            'text_hash' => hash('sha256', 'de|Masse'),
            'preferred_unit_id' => $preferred->id,
        ]);

        $this->assertFalse($synonym->needsTranslation());
        $this->assertTrue($synonym->isSynonym());
    }

    // ─── effectiveTranslation ────────────────────────────────────────────

    public function test_own_translation_wins(): void
    {
        $unit = $this->unit();
        TmsTranslation::create([
            'tms_unit_id' => $unit->id,
            'target_lang' => 'fr',
            'translation' => 'Poids',
            'provider' => 'deepl',
            'status' => 'auto',
        ]);

        $this->assertSame('Poids', $unit->fresh()->effectiveTranslation('fr'));
    }

    public function test_do_not_translate_resolves_to_the_source_text(): void
    {
        // "IP65" heisst in jeder Sprache "IP65" — der Begriff gilt damit als
        // erledigt und nicht als dauerhaft fehlend.
        $unit = $this->unit([
            'source_text' => 'IP65',
            'text_hash' => hash('sha256', 'de|IP65'),
            'do_not_translate' => true,
        ]);

        $this->assertSame('IP65', $unit->effectiveTranslation('fr'));
    }

    public function test_synonym_inherits_the_preferred_translation(): void
    {
        $preferred = $this->unit(['source_text' => 'Akku', 'text_hash' => hash('sha256', 'de|Akku')]);
        TmsTranslation::create([
            'tms_unit_id' => $preferred->id,
            'target_lang' => 'fr',
            'translation' => 'Batterie',
            'provider' => 'deepl',
            'status' => 'auto',
        ]);

        $synonym = $this->unit([
            'source_text' => 'Energiespeicher',
            'text_hash' => hash('sha256', 'de|Energiespeicher'),
            'preferred_unit_id' => $preferred->id,
        ]);

        $this->assertSame('Batterie', $synonym->fresh()->effectiveTranslation('fr'));
    }

    public function test_untranslated_unit_resolves_to_null(): void
    {
        $this->assertNull($this->unit()->effectiveTranslation('fr'));
    }

    // ─── Kein Geld fuer ausgenommene Begriffe ────────────────────────────

    public function test_ingest_queues_no_translation_for_exempt_units(): void
    {
        Queue::fake();

        $preferred = $this->unit(['source_text' => 'Akku', 'text_hash' => hash('sha256', 'de|Akku')]);
        $this->unit([
            'source_text' => 'IP65',
            'text_hash' => hash('sha256', 'de|IP65'),
            'do_not_translate' => true,
        ]);
        $this->unit([
            'source_text' => 'Energiespeicher',
            'text_hash' => hash('sha256', 'de|Energiespeicher'),
            'preferred_unit_id' => $preferred->id,
        ]);

        $entities = [
            ['entity_type' => 'attribute', 'entity_id' => 'a', 'fields' => [
                ['field' => 'name_de', 'text' => 'IP65', 'lang' => 'de'],
            ]],
            ['entity_type' => 'attribute', 'entity_id' => 'b', 'fields' => [
                ['field' => 'name_de', 'text' => 'Energiespeicher', 'lang' => 'de'],
            ]],
            ['entity_type' => 'attribute', 'entity_id' => 'c', 'fields' => [
                ['field' => 'name_de', 'text' => 'Akku', 'lang' => 'de'],
            ]],
        ];

        (new ProcessIngestBatchJob($entities, ['fr', 'es']))->handle();

        // Nur der Hauptbegriff wird uebersetzt — nicht das Kennzeichen-Paar.
        Queue::assertPushed(TranslateUnitJob::class, 1);
    }

    // ─── Pflege ueber die API ────────────────────────────────────────────

    public function test_metadata_can_be_updated(): void
    {
        $unit = $this->unit();

        $this->withToken(self::API_KEY)
            ->patchJson("/api/units/{$unit->id}", [
                'do_not_translate' => true,
                'definition' => 'Masse eines Artikels in Kilogramm',
                'word_class' => 'noun',
            ])
            ->assertOk();

        $unit->refresh();
        $this->assertTrue($unit->do_not_translate);
        $this->assertSame('Masse eines Artikels in Kilogramm', $unit->definition);
        $this->assertSame('noun', $unit->word_class);
    }

    public function test_invalid_word_class_is_rejected(): void
    {
        $unit = $this->unit();

        $this->withToken(self::API_KEY)
            ->patchJson("/api/units/{$unit->id}", ['word_class' => 'unfug'])
            ->assertStatus(422);
    }

    public function test_a_unit_cannot_be_its_own_synonym(): void
    {
        $unit = $this->unit();

        $this->withToken(self::API_KEY)
            ->patchJson("/api/units/{$unit->id}", ['preferred_unit_id' => $unit->id])
            ->assertStatus(422);
    }

    public function test_synonym_chains_are_rejected(): void
    {
        // Ketten wuerden effectiveTranslation() in eine Endlosschleife schicken.
        $preferred = $this->unit(['source_text' => 'Akku', 'text_hash' => hash('sha256', 'de|Akku')]);
        $synonym = $this->unit([
            'source_text' => 'Batterie',
            'text_hash' => hash('sha256', 'de|Batterie'),
            'preferred_unit_id' => $preferred->id,
        ]);
        $third = $this->unit(['source_text' => 'Zelle', 'text_hash' => hash('sha256', 'de|Zelle')]);

        $this->withToken(self::API_KEY)
            ->patchJson("/api/units/{$third->id}", ['preferred_unit_id' => $synonym->id])
            ->assertStatus(422);
    }

    public function test_a_preferred_term_cannot_become_a_synonym(): void
    {
        $preferred = $this->unit(['source_text' => 'Akku', 'text_hash' => hash('sha256', 'de|Akku')]);
        $this->unit([
            'source_text' => 'Batterie',
            'text_hash' => hash('sha256', 'de|Batterie'),
            'preferred_unit_id' => $preferred->id,
        ]);
        $other = $this->unit(['source_text' => 'Zelle', 'text_hash' => hash('sha256', 'de|Zelle')]);

        $this->withToken(self::API_KEY)
            ->patchJson("/api/units/{$preferred->id}", ['preferred_unit_id' => $other->id])
            ->assertStatus(422);
    }

    // ─── Auswirkung auf Statistik und Listen ─────────────────────────────

    public function test_exempt_units_do_not_count_as_missing(): void
    {
        $preferred = $this->unit(['source_text' => 'Akku', 'text_hash' => hash('sha256', 'de|Akku')]);
        $this->unit([
            'source_text' => 'IP65',
            'text_hash' => hash('sha256', 'de|IP65'),
            'do_not_translate' => true,
        ]);
        $this->unit([
            'source_text' => 'Energiespeicher',
            'text_hash' => hash('sha256', 'de|Energiespeicher'),
            'preferred_unit_id' => $preferred->id,
        ]);

        $response = $this->withToken(self::API_KEY)->getJson('/api/stats?langs=fr');

        $response->assertOk();
        $this->assertSame(3, $response->json('total_units'));
        $this->assertSame(2, $response->json('exempt_units'));
        // Offen ist nur der Hauptbegriff.
        $this->assertSame(1, $response->json('languages.fr.missing'));
    }

    public function test_exempt_units_are_not_listed_as_missing(): void
    {
        $preferred = $this->unit(['source_text' => 'Akku', 'text_hash' => hash('sha256', 'de|Akku')]);
        $this->unit([
            'source_text' => 'IP65',
            'text_hash' => hash('sha256', 'de|IP65'),
            'do_not_translate' => true,
        ]);
        $this->unit([
            'source_text' => 'Energiespeicher',
            'text_hash' => hash('sha256', 'de|Energiespeicher'),
            'preferred_unit_id' => $preferred->id,
        ]);

        $response = $this->withToken(self::API_KEY)->getJson('/api/units?status=missing&lang=fr');

        $response->assertOk();
        $texts = array_column($response->json('data'), 'source_text');
        $this->assertSame(['Akku'], $texts);
    }
}
