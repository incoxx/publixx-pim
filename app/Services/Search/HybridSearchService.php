<?php

declare(strict_types=1);

namespace App\Services\Search;

use InvalidArgumentException;

/**
 * Orchestriert die semantische Schnellsuche:
 *
 * 1. Constraint-Extraktion (deterministisch): Zahlen/Einheiten/Preise
 *    werden zu harten Meilisearch-Filtern, der Rest bleibt Suchtext.
 * 2. Suche: Hybrid (Keyword + Vektor) bei Suchtext, reiner Filter-Betrieb
 *    ohne Suchtext, Keyword-only falls der Embedder deaktiviert ist.
 * 3. Strukturierte JSON-Antwort mit gerankten Treffern, angewandten
 *    Constraints und optionalen Facetten — keine LLM-generierte Prosa.
 */
class HybridSearchService
{
    public function __construct(
        private readonly MeilisearchClient $client,
        private readonly SearchSchemaService $schemaService,
    ) {
    }

    /**
     * Suche ausführen.
     *
     * @param string $q natürlichsprachige Anfrage (darf leer sein)
     * @param array $explicitFilters [{attribute, operator, value}] aus der GUI
     * @param array $facets Attribut-technical_names für Facetten-Verteilung
     */
    public function search(
        string $q = '',
        array $explicitFilters = [],
        array $facets = [],
        int $limit = 20,
        int $offset = 0,
        ?float $semanticRatio = null,
        string $mode = 'auto',
        ?string $status = 'active',
    ): array {
        $schema = $this->schemaService->schema();

        // 1) Quantitative Constraints deterministisch extrahieren
        $extraction = ['filters' => [], 'residual' => trim($q), 'unresolved' => []];
        if (trim($q) !== '' && $mode !== 'filter') {
            $extractor = new ConstraintExtractor(
                $schema,
                (float) config('meilisearch.search.numeric_tolerance', 0.05),
            );
            $extraction = $extractor->extract($q);
        }

        $filters = $extraction['filters'];

        foreach ($explicitFilters as $explicit) {
            $filters[] = $this->mapExplicitFilter($explicit, $schema);
        }

        if ($status !== null && $status !== '') {
            $filters[] = [
                'fields' => ['status'],
                'attributes' => ['status'],
                'op' => 'eq',
                'value' => $status,
            ];
        }

        // 2) Suchmodus bestimmen
        $residual = $mode === 'filter' ? '' : $extraction['residual'];
        $embedderEnabled = (bool) config('meilisearch.embedder.enabled');
        $effectiveMode = match (true) {
            $residual === '' => 'filter',
            $mode === 'keyword' || !$embedderEnabled => 'keyword',
            default => 'hybrid',
        };

        $params = [
            'q' => $residual,
            'limit' => $limit,
            'offset' => $offset,
            'showRankingScore' => true,
            'attributesToRetrieve' => [
                'id', 'sku', 'ean', 'status', 'product_type', 'name_de', 'name_en',
                'hierarchy_path', 'primary_image', 'list_price', 'attribute_completeness',
            ],
        ];

        $filterString = MeilisearchFilterBuilder::build($filters);
        if ($filterString !== '') {
            $params['filter'] = $filterString;
        }

        if ($effectiveMode === 'hybrid') {
            $params['hybrid'] = [
                'embedder' => (string) config('meilisearch.embedder.name', 'products'),
                'semanticRatio' => $semanticRatio
                    ?? (float) config('meilisearch.search.semantic_ratio', 0.5),
            ];
        }

        if ($facets !== []) {
            $params['facets'] = array_map(
                fn (string $name): string => $this->facetField($name, $schema),
                $facets,
            );
        }

        // 3) Suche ausführen und strukturiert antworten
        $result = $this->client->search((string) config('meilisearch.index', 'products'), $params);

        return [
            'query' => $q,
            'residual_query' => $residual,
            'mode' => $effectiveMode,
            'applied_constraints' => array_map(
                static fn (array $f): array => [
                    'attributes' => $f['attributes'],
                    'operator' => $f['op'],
                    'value' => $f['value'] ?? null,
                    'min' => $f['min'] ?? null,
                    'max' => $f['max'] ?? null,
                    'unit' => $f['unit'] ?? null,
                    'source_text' => $f['text'] ?? null,
                ],
                $extraction['filters'],
            ),
            'unresolved_constraints' => $extraction['unresolved'],
            'hits' => array_map(
                static function (array $hit): array {
                    $hit['score'] = $hit['_rankingScore'] ?? null;
                    unset($hit['_rankingScore']);

                    return $hit;
                },
                $result['hits'] ?? [],
            ),
            'total' => $result['estimatedTotalHits'] ?? $result['totalHits'] ?? 0,
            'facets' => $result['facetDistribution'] ?? null,
            'processing_time_ms' => $result['processingTimeMs'] ?? null,
        ];
    }

    /**
     * Expliziten GUI-Filter (attribute/operator/value) auf das
     * Meilisearch-Feld abbilden.
     */
    private function mapExplicitFilter(array $explicit, array $schema): array
    {
        $name = (string) ($explicit['attribute'] ?? '');

        // Direkte Index-Felder zulassen (list_price, product_type, ...)
        $directFields = ['list_price', 'product_type', 'product_type_ref', 'hierarchy_path', 'status'];
        if (in_array($name, $directFields, true)) {
            $field = $name;
        } elseif (isset($schema['attributes'][$name])) {
            $field = $schema['attributes'][$name]['field'];
        } else {
            throw new InvalidArgumentException("Unbekanntes Filter-Attribut: {$name}");
        }

        $operator = (string) ($explicit['operator'] ?? 'eq');
        $value = $explicit['value'] ?? null;

        if ($operator === 'between') {
            // array_values() normalisiert auch assoziative Arrays (['min'=>1,'max'=>2]),
            // damit $values[0] und $values[1] immer definiert sind.
            $values = array_values((array) $value);
            if (count($values) !== 2) {
                throw new InvalidArgumentException("Filter 'between' benötigt [min, max]: {$name}");
            }

            return [
                'fields' => [$field],
                'attributes' => [$name],
                'op' => 'range',
                'min' => (float) $values[0],
                'max' => (float) $values[1],
            ];
        }

        $opMap = ['eq' => 'eq', 'neq' => 'neq', 'gt' => 'gt', 'gte' => 'ge', 'lt' => 'lt', 'lte' => 'le'];
        if (!isset($opMap[$operator])) {
            throw new InvalidArgumentException("Unbekannter Filter-Operator: {$operator}");
        }

        return [
            'fields' => [$field],
            'attributes' => [$name],
            'op' => $opMap[$operator],
            'value' => is_numeric($value) ? (float) $value : $value,
        ];
    }

    /**
     * Facetten-Name auf Meilisearch-Feld abbilden.
     */
    private function facetField(string $name, array $schema): string
    {
        if (isset($schema['attributes'][$name])) {
            return $schema['attributes'][$name]['field'];
        }

        return $name;
    }
}
