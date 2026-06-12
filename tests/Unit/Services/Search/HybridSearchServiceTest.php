<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Services\Search\HybridSearchService;
use App\Services\Search\MeilisearchClient;
use App\Services\Search\SearchSchemaService;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Unit-Tests für den HybridSearchService.
 *
 * Der MeilisearchClient wird gemockt; die an Meilisearch übergebenen
 * Parameter werden über einen Callback eingefangen und geprüft.
 */
class HybridSearchServiceTest extends TestCase
{
    /** Zuletzt an den Client übergebene Suchparameter. */
    private array $sentParams = [];

    /**
     * Service mit gemocktem Client und vorgeladenem Schema bauen.
     */
    private function service(array $clientResponse = []): HybridSearchService
    {
        $client = $this->createMock(MeilisearchClient::class);
        $client->method('search')
            ->willReturnCallback(function (string $uid, array $params) use ($clientResponse): array {
                $this->sentParams = $params;

                return $clientResponse;
            });

        return new HybridSearchService($client, new SearchSchemaService($this->schema()));
    }

    /** Test-Schema analog SearchSchemaService::schema(). */
    private function schema(): array
    {
        return [
            'attributes' => [
                'gewicht' => [
                    'field' => 'attr_gewicht',
                    'data_type' => 'Number',
                    'aliases' => ['gewicht', 'weight'],
                ],
                'farbe' => [
                    'field' => 'attr_farbe',
                    'data_type' => 'Selection',
                    'aliases' => ['farbe', 'color'],
                ],
            ],
            'units' => [
                'kg' => ['group_id' => 'mass', 'to_base' => 1.0],
                'g' => ['group_id' => 'mass', 'to_base' => 0.001],
            ],
            'unit_attributes' => ['mass' => ['gewicht']],
            'concepts' => [],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'meilisearch.index' => 'products',
            'meilisearch.embedder.enabled' => true,
            'meilisearch.embedder.name' => 'products',
            'meilisearch.search.semantic_ratio' => 0.5,
            'meilisearch.search.numeric_tolerance' => 0.05,
        ]);
    }

    public function test_hybrid_modus_setzt_embedder_und_semantic_ratio(): void
    {
        $result = $this->service(['hits' => [], 'estimatedTotalHits' => 0])
            ->search(q: 'robuster Bohrer');

        $this->assertSame('hybrid', $result['mode']);
        $this->assertSame('robuster Bohrer', $this->sentParams['q']);
        $this->assertSame('products', $this->sentParams['hybrid']['embedder']);
        $this->assertSame(0.5, $this->sentParams['hybrid']['semanticRatio']);
    }

    public function test_explizite_semantic_ratio_wird_durchgereicht(): void
    {
        $this->service(['hits' => []])->search(q: 'Bohrer', semanticRatio: 0.9);

        $this->assertSame(0.9, $this->sentParams['hybrid']['semanticRatio']);
    }

    public function test_keyword_modus_wenn_embedder_deaktiviert(): void
    {
        config(['meilisearch.embedder.enabled' => false]);

        $result = $this->service(['hits' => []])->search(q: 'Bohrer');

        $this->assertSame('keyword', $result['mode']);
        $this->assertArrayNotHasKey('hybrid', $this->sentParams);
    }

    public function test_leere_query_ergibt_filter_modus(): void
    {
        $result = $this->service(['hits' => []])->search(q: '   ');

        $this->assertSame('filter', $result['mode']);
        $this->assertSame('', $this->sentParams['q']);
        $this->assertArrayNotHasKey('hybrid', $this->sentParams);
    }

    public function test_quantitative_constraints_werden_zu_harten_filtern(): void
    {
        $result = $this->service(['hits' => []])->search(q: 'Bohrer unter 2 kg');

        // Constraint extrahiert: gewicht <= 2 (Basiseinheit kg)
        $this->assertCount(1, $result['applied_constraints']);
        $this->assertSame(['gewicht'], $result['applied_constraints'][0]['attributes']);
        $this->assertSame('le', $result['applied_constraints'][0]['operator']);
        $this->assertSame('Bohrer', $result['residual_query']);

        // Filter-String enthält Attribut-Feld und Status-Default
        $this->assertStringContainsString('attr_gewicht <= 2', $this->sentParams['filter']);
        $this->assertStringContainsString('status = "active"', $this->sentParams['filter']);
    }

    public function test_status_null_setzt_keinen_status_filter(): void
    {
        $this->service(['hits' => []])->search(q: 'Bohrer', status: null);

        $this->assertStringNotContainsString('status', $this->sentParams['filter'] ?? '');
    }

    public function test_expliziter_between_filter_wird_zu_range(): void
    {
        $this->service(['hits' => []])->search(
            q: '',
            explicitFilters: [
                ['attribute' => 'gewicht', 'operator' => 'between', 'value' => ['min' => 1, 'max' => 2]],
            ],
            status: null,
        );

        $this->assertSame('(attr_gewicht >= 1 AND attr_gewicht <= 2)', $this->sentParams['filter']);
    }

    public function test_unbekanntes_filter_attribut_wirft_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unbekanntes Filter-Attribut: unbekannt');

        $this->service()->search(
            q: '',
            explicitFilters: [['attribute' => 'unbekannt', 'operator' => 'eq', 'value' => 1]],
        );
    }

    public function test_unbekannter_operator_wirft_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unbekannter Filter-Operator: magic');

        $this->service()->search(
            q: '',
            explicitFilters: [['attribute' => 'gewicht', 'operator' => 'magic', 'value' => 1]],
        );
    }

    public function test_facetten_werden_auf_meilisearch_felder_gemappt(): void
    {
        $this->service(['hits' => [], 'facetDistribution' => ['attr_farbe' => ['rot' => 3]]])
            ->search(q: 'Bohrer', facets: ['farbe', 'status']);

        // Schema-Attribut → attr_*-Feld, Direktfeld bleibt unverändert
        $this->assertSame(['attr_farbe', 'status'], $this->sentParams['facets']);
    }

    public function test_treffer_werden_mit_score_und_total_aufbereitet(): void
    {
        $result = $this->service([
            'hits' => [
                ['id' => 'p1', 'sku' => 'A-1', '_rankingScore' => 0.87],
                ['id' => 'p2', 'sku' => 'A-2'],
            ],
            'estimatedTotalHits' => 2,
            'processingTimeMs' => 12,
        ])->search(q: 'Bohrer');

        $this->assertSame(2, $result['total']);
        $this->assertSame(12, $result['processing_time_ms']);
        $this->assertSame(0.87, $result['hits'][0]['score']);
        $this->assertArrayNotHasKey('_rankingScore', $result['hits'][0]);
        $this->assertNull($result['hits'][1]['score']);
    }
}
