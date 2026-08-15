<?php

declare(strict_types=1);

namespace Tms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tms\Models\TmsTranslation;
use Tms\Models\TmsUnit;
use Tms\Models\TmsUsage;
use Tms\Tests\TestCase;

/**
 * Glossar-Austausch: Begriffe herausgeben, extern übersetzen lassen,
 * zurückspielen — ohne MT-Anbieter.
 */
class GlossaryExchangeTest extends TestCase
{
    use RefreshDatabase;

    private const string API_KEY = 'test-api-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['tms.api_key' => self::API_KEY]);
        Redis::shouldReceive('setex')->andReturnTrue()->byDefault();
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . self::API_KEY];
    }

    private function unit(string $text, string $domain = 'attribute'): TmsUnit
    {
        return TmsUnit::create([
            'source_lang' => 'de',
            'source_text' => $text,
            'text_hash' => hash('sha256', "de|{$text}"),
            'domain' => $domain,
            'char_count' => mb_strlen($text),
        ]);
    }

    // ─── Export ──────────────────────────────────────────────────────────

    public function test_export_returns_terms_with_translations_and_usages(): void
    {
        $unit = $this->unit('Gewicht');

        TmsUsage::create([
            'tms_unit_id' => $unit->id,
            'entity_type' => 'attribute',
            'entity_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'field_name' => 'name_de',
            'entity_label' => 'gewicht',
        ]);

        TmsTranslation::create([
            'tms_unit_id' => $unit->id,
            'target_lang' => 'fi',
            'translation' => 'Paino',
            'provider' => 'deepl',
            'status' => 'auto',
        ]);

        $response = $this->getJson('/api/glossary?langs=fi', $this->headers())->assertOk();

        $row = $response->json('data.0');

        $this->assertSame(hash('sha256', 'de|Gewicht'), $row['hash']);
        $this->assertSame('Gewicht', $row['source_text']);
        $this->assertSame('attribute', $row['domain']);
        $this->assertSame('Paino', $row['translations']['fi']['text']);
        $this->assertSame('auto', $row['translations']['fi']['status']);
        $this->assertSame(['attribute: gewicht'], $row['usages']);
    }

    public function test_export_can_be_limited_to_missing_translations(): void
    {
        $done = $this->unit('Gewicht');
        TmsTranslation::create([
            'tms_unit_id' => $done->id,
            'target_lang' => 'fi',
            'translation' => 'Paino',
            'provider' => 'deepl',
            'status' => 'auto',
        ]);

        $this->unit('Breite');

        $response = $this->getJson('/api/glossary?langs=fi&status=missing', $this->headers())->assertOk();

        $this->assertSame(1, $response->json('total'));
        $this->assertSame('Breite', $response->json('data.0.source_text'));
    }

    public function test_export_can_be_filtered_by_domain(): void
    {
        $this->unit('Gewicht', 'attribute');
        $this->unit('Schwarz', 'value_list_entry');

        $response = $this->getJson('/api/glossary?langs=fi&domain=value_list_entry', $this->headers())->assertOk();

        $this->assertSame(1, $response->json('total'));
        $this->assertSame('Schwarz', $response->json('data.0.source_text'));
    }

    public function test_export_requires_languages(): void
    {
        $this->getJson('/api/glossary', $this->headers())->assertStatus(422);
    }

    // ─── Import ──────────────────────────────────────────────────────────

    public function test_import_writes_translation_as_human_reviewed(): void
    {
        $unit = $this->unit('Gewicht');

        $this->postJson('/api/glossary', [
            'items' => [
                ['hash' => $unit->text_hash, 'lang' => 'fi', 'translation' => 'Paino'],
            ],
        ], $this->headers())->assertOk();

        $this->assertDatabaseHas('tms_translations', [
            'tms_unit_id' => $unit->id,
            'target_lang' => 'fi',
            'translation' => 'Paino',
            'provider' => 'human',
            'status' => 'reviewed',
        ]);
    }

    public function test_import_overwrites_machine_translation(): void
    {
        $unit = $this->unit('Gewicht');
        TmsTranslation::create([
            'tms_unit_id' => $unit->id,
            'target_lang' => 'fi',
            'translation' => 'Maschinell',
            'provider' => 'deepl',
            'status' => 'auto',
        ]);

        $response = $this->postJson('/api/glossary', [
            'items' => [['hash' => $unit->text_hash, 'lang' => 'fi', 'translation' => 'Von Hand']],
        ], $this->headers())->assertOk();

        $this->assertSame(1, $response->json('updated'));
        $this->assertSame('Von Hand', $unit->translations()->where('target_lang', 'fi')->value('translation'));
    }

    /**
     * Die wichtigste Regel des Rückwegs.
     */
    public function test_unchanged_cells_do_not_become_reviewed(): void
    {
        // Sonst wuerde ein Export-Import-Durchlauf ohne jede Bearbeitung den
        // gesamten maschinellen Bestand zu "geprueft" befoerdern — und damit
        // jede spaetere Korrektur durch den MT-Anbieter blockieren.
        $unit = $this->unit('Gewicht');
        TmsTranslation::create([
            'tms_unit_id' => $unit->id,
            'target_lang' => 'fi',
            'translation' => 'Paino',
            'provider' => 'deepl',
            'status' => 'auto',
        ]);

        $response = $this->postJson('/api/glossary', [
            'items' => [['hash' => $unit->text_hash, 'lang' => 'fi', 'translation' => 'Paino']],
        ], $this->headers())->assertOk();

        $this->assertSame(0, $response->json('updated'));
        $this->assertSame(1, $response->json('unchanged'));

        $this->assertDatabaseHas('tms_translations', [
            'tms_unit_id' => $unit->id,
            'target_lang' => 'fi',
            'status' => 'auto',
            'provider' => 'deepl',
        ]);
    }

    public function test_import_reports_unknown_hashes_instead_of_failing(): void
    {
        $response = $this->postJson('/api/glossary', [
            'items' => [['hash' => str_repeat('a', 64), 'lang' => 'fi', 'translation' => 'Paino']],
        ], $this->headers())->assertOk();

        $this->assertSame(0, $response->json('updated'));
        $this->assertSame(1, $response->json('unknown'));
        $this->assertCount(1, $response->json('unknown_hashes'));
    }

    public function test_import_ignores_empty_cells(): void
    {
        $unit = $this->unit('Gewicht');

        $this->postJson('/api/glossary', [
            'items' => [['hash' => $unit->text_hash, 'lang' => 'fi', 'translation' => '   ']],
        ], $this->headers())->assertStatus(422);

        $this->assertSame(0, TmsTranslation::count());
    }

    public function test_import_rejects_malformed_hashes(): void
    {
        $this->postJson('/api/glossary', [
            'items' => [['hash' => 'zu-kurz', 'lang' => 'fi', 'translation' => 'Paino']],
        ], $this->headers())->assertStatus(422);
    }

    public function test_roundtrip_export_then_import(): void
    {
        $unit = $this->unit('Gewicht');

        $exported = $this->getJson('/api/glossary?langs=fi&status=missing', $this->headers())
            ->assertOk()
            ->json('data.0');

        // Was der Übersetzer in der Datei ausfüllt
        $this->postJson('/api/glossary', [
            'items' => [['hash' => $exported['hash'], 'lang' => 'fi', 'translation' => 'Paino']],
        ], $this->headers())->assertOk();

        $this->assertSame('Paino', $unit->translations()->where('target_lang', 'fi')->value('translation'));

        // Danach ist der Begriff nicht mehr offen
        $this->assertSame(
            0,
            $this->getJson('/api/glossary?langs=fi&status=missing', $this->headers())->json('total'),
        );
    }
}
