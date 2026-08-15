<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\TranslationJob;
use App\Services\Connectors\DeepL\DeepLTranslationService;
use App\Services\Tms\TmsClient;
use App\Services\TranslationJobService;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Sichert die Entscheidung ab, dass das Translation Memory auf Metadaten
 * beschränkt bleibt (docs/features/tms-konzept-validierung.md, Abschnitt 0).
 *
 * Übersetzte Attributwerte flossen bisher nach jedem Übersetzungs-Job ins TM.
 * Weil die `domain` nicht in den Hash eingeht, konnte eine aus einem Produkttext
 * stammende Übersetzung anschließend vom Metadaten-Sync zurückgelesen werden.
 */
class TranslationJobTmsSyncTest extends TestCase
{
    private function service(): TranslationJobService
    {
        config([
            'tms.enabled' => true,
            'tms.base_url' => 'http://tms.test/api',
            'tms.api_key' => 'test-key',
        ]);

        return new TranslationJobService(
            $this->createMock(DeepLTranslationService::class),
            new TmsClient(),
        );
    }

    /**
     * Nicht gespeichertes Model — syncToTms() liest nur Attribute, keine DB.
     */
    private function job(): TranslationJob
    {
        $job = new TranslationJob();
        $job->id = 'job-1';
        $job->source_language = 'de';
        $job->target_language = 'fi';

        return $job;
    }

    private function item(string $entityType, string $source, string $translated): object
    {
        return new class($entityType, $source, $translated) {
            public function __construct(
                public string $entity_type,
                public string $source_text,
                public string $translated_text,
                public string $entity_id = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                public ?string $attribute_id = null,
            ) {}
        };
    }

    private function syncToTms(TranslationJobService $service, array $items): void
    {
        $method = new ReflectionMethod(TranslationJobService::class, 'syncToTms');
        $method->setAccessible(true);
        $method->invoke($service, $this->job(), collect($items));
    }

    public function test_product_attribute_values_are_not_pushed_into_the_tm(): void
    {
        Http::fake();

        $this->syncToTms($this->service(), [
            $this->item('product', 'Rostfreier Edelstahl', 'Ruostumaton teräs'),
        ]);

        Http::assertNothingSent();
    }

    public function test_metadata_is_still_pushed_into_the_tm(): void
    {
        Http::fake(['*/import-translations' => Http::response(['imported' => 1])]);

        $this->syncToTms($this->service(), [
            $this->item('attribute', 'Gewicht', 'Paino'),
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/import-translations')
                && count($request['items']) === 1
                && $request['items'][0]['source_text'] === 'Gewicht'
                && $request['items'][0]['entity_type'] === 'attribute';
        });
    }

    public function test_mixed_job_pushes_only_the_metadata_positions(): void
    {
        Http::fake(['*/import-translations' => Http::response(['imported' => 2])]);

        $this->syncToTms($this->service(), [
            $this->item('product', 'Marketingtext', 'Markkinointiteksti'),
            $this->item('attribute', 'Gewicht', 'Paino'),
            $this->item('product', 'Noch ein Produkttext', 'Toinen teksti'),
            $this->item('value_list_entry', 'Schwarz', 'Musta'),
        ]);

        Http::assertSent(function ($request) {
            $types = array_column($request['items'], 'entity_type');
            sort($types);

            return $types === ['attribute', 'value_list_entry'];
        });
    }

    public function test_unknown_entity_types_are_not_pushed(): void
    {
        // Die Liste ist positiv: was resolveModelClass() nicht kennt, gilt
        // nicht als Metadate und darf nicht ins TM sickern.
        Http::fake();

        $this->syncToTms($this->service(), [
            $this->item('irgendwas_neues', 'Text', 'Teksti'),
        ]);

        Http::assertNothingSent();
    }

    public function test_all_documented_metadata_types_are_accepted(): void
    {
        Http::fake(['*/import-translations' => Http::response([])]);

        $metadataTypes = [
            'attribute', 'attribute_type', 'value_list_entry', 'value_list',
            'hierarchy_node', 'hierarchy', 'product_type', 'unit_group',
            'price_type', 'relation_type',
        ];

        $this->syncToTms(
            $this->service(),
            array_map(fn ($t) => $this->item($t, "Quelle {$t}", "Ziel {$t}"), $metadataTypes),
        );

        Http::assertSent(fn ($request) => count($request['items']) === count($metadataTypes));
    }

    public function test_nothing_is_sent_when_tms_is_disabled(): void
    {
        config(['tms.enabled' => false]);
        Http::fake();

        $service = new TranslationJobService(
            $this->createMock(DeepLTranslationService::class),
            new TmsClient(),
        );

        $this->syncToTms($service, [$this->item('attribute', 'Gewicht', 'Paino')]);

        Http::assertNothingSent();
    }
}
