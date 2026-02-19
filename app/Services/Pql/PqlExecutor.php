<?php

declare(strict_types=1);

namespace App\Services\Pql;

use App\Services\Pql\Ast\SelectNode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * PQL Execution Engine.
 *
 * Pipeline: Parse → Validate → Generate SQL → Execute → Fuzzy Post-Filter → Cache → Format Response
 *
 * Caching:
 *   Key: pql:hash:{sha256(pql + mapping_id + lang + limit + offset)}
 *   TTL: 15 minutes
 *   Invalidation: TTL-based only (PQL queries too variable for event triggers)
 */
final class PqlExecutor
{
    private const CACHE_TTL_SECONDS = 900; // 15 minutes
    private const CACHE_PREFIX = 'pql:hash:';
    private const MAX_LIMIT = 500;
    private const DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly PqlParser $parser,
        private readonly PqlValidator $validator,
        private readonly PqlSqlGenerator $generator,
        private readonly FuzzyMatcher $fuzzyMatcher,
    ) {}

    /**
     * Execute a PQL query and return formatted results.
     *
     * @param string $pql PQL query string
     * @param array<string> $languages Language codes (e.g. ['de', 'en'])
     * @param int $limit Max results (1–500)
     * @param int $offset Pagination offset
     * @param string|null $mappingId Optional export mapping ID
     * @return array{meta: array, data: array}
     * @throws \InvalidArgumentException on parse/validation errors
     */
    public function execute(
        string $pql,
        array $languages = ['de'],
        int $limit = self::DEFAULT_LIMIT,
        int $offset = 0,
        ?string $mappingId = null,
    ): array {
        $startTime = microtime(true);
        $limit = max(1, min($limit, self::MAX_LIMIT));

        // Check cache
        $cacheKey = $this->buildCacheKey($pql, $mappingId, $languages, $limit, $offset);
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $cached['meta']['cache_hit'] = true;
            $cached['meta']['query_time_ms'] = round((microtime(true) - $startTime) * 1000, 1);
            return $cached;
        }

        // 1. Parse
        $ast = $this->parser->parse($pql);
        $ast = new SelectNode(
            fields: $ast->fields,
            source: $ast->source,
            where: $ast->where,
            orderByScore: $ast->orderByScore,
            limit: $limit,
            offset: $offset,
        );

        // 2. Validate
        $validation = $this->validator->validate($ast);
        if (!$validation['valid']) {
            throw new \InvalidArgumentException(
                'PQL validation errors: ' . json_encode($validation['errors'], JSON_UNESCAPED_UNICODE)
            );
        }

        // 3. Generate SQL
        $primaryLang = $languages[0] ?? 'de';
        $generated = $this->generator->generate($ast, $validation['resolved_fields'], $primaryLang);
        $query = $generated['query'];

        // 4. Execute SQL
        $results = $query->get();
        $rows = $results->toArray();

        // 5. Fuzzy post-filter (if needed)
        if ($generated['has_fuzzy'] && !empty($generated['fuzzy_nodes'])) {
            $rows = $this->applyFuzzyPostFilter($rows, $generated['fuzzy_nodes']);
        }

        // 6. Apply actual limit (fuzzy may have over-fetched)
        $total = count($rows);
        $rows = array_slice($rows, 0, $limit);

        // 7. Format response
        $data = $this->formatResults($rows, $generated['needs_scoring']);

        $result = [
            'meta' => [
                'total' => $total,
                'returned' => count($data),
                'offset' => $offset,
                'query_time_ms' => round((microtime(true) - $startTime) * 1000, 1),
                'cache_hit' => false,
                'pql_parsed' => $pql,
            ],
            'data' => $data,
        ];

        // 8. Cache result
        try {
            Cache::put($cacheKey, $result, self::CACHE_TTL_SECONDS);
        } catch (\Exception $e) {
            Log::warning('PQL cache write failed', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Execute a PQL query and return only the count.
     */
    public function count(
        string $pql,
        array $languages = ['de'],
        ?string $mappingId = null,
    ): array {
        $startTime = microtime(true);

        $ast = $this->parser->parse($pql);
        $validation = $this->validator->validate($ast);

        if (!$validation['valid']) {
            throw new \InvalidArgumentException(
                'PQL validation errors: ' . json_encode($validation['errors'], JSON_UNESCAPED_UNICODE)
            );
        }

        $primaryLang = $languages[0] ?? 'de';
        $generated = $this->generator->generate($ast, $validation['resolved_fields'], $primaryLang);

        // For count, remove limit/offset and use COUNT
        $count = $generated['query']->limit(PHP_INT_MAX)->offset(0)->count();

        return [
            'meta' => [
                'total' => $count,
                'query_time_ms' => round((microtime(true) - $startTime) * 1000, 1),
                'pql_parsed' => $pql,
            ],
        ];
    }

    /**
     * Validate a PQL query without executing.
     *
     * @return array{valid: bool, errors: array, ast: array|null}
     */
    public function validate(string $pql): array
    {
        try {
            $ast = $this->parser->parse($pql);
            $validation = $this->validator->validate($ast);

            return [
                'valid' => $validation['valid'],
                'errors' => $validation['errors'],
                'ast' => $ast->toArray(),
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'valid' => false,
                'errors' => [
                    ['position' => 0, 'field' => '', 'error' => $e->getMessage()],
                ],
                'ast' => null,
            ];
        }
    }

    /**
     * Explain a PQL query: parse → validate → show query plan.
     *
     * @return array{ast: array|null, sql: string|null, bindings: array, validation: array, estimated_cost: string}
     */
    public function explain(string $pql, array $languages = ['de']): array
    {
        try {
            $ast = $this->parser->parse($pql);
            $validation = $this->validator->validate($ast);

            if (!$validation['valid']) {
                return [
                    'ast' => $ast->toArray(),
                    'sql' => null,
                    'bindings' => [],
                    'validation' => $validation,
                    'estimated_cost' => 'N/A (validation failed)',
                ];
            }

            $primaryLang = $languages[0] ?? 'de';
            $generated = $this->generator->generate($ast, $validation['resolved_fields'], $primaryLang);
            $sqlInfo = $this->generator->toSql($generated['query']);

            // Estimate cost based on query complexity
            $cost = $this->estimateCost($ast, $generated);

            return [
                'ast' => $ast->toArray(),
                'sql' => $sqlInfo['sql'],
                'bindings' => $sqlInfo['bindings'],
                'validation' => $validation,
                'estimated_cost' => $cost,
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'ast' => null,
                'sql' => null,
                'bindings' => [],
                'validation' => ['valid' => false, 'errors' => [['error' => $e->getMessage()]]],
                'estimated_cost' => 'N/A (parse error)',
            ];
        }
    }

    // ─── Fuzzy Post-Filter ──────────────────────────────────────

    /**
     * Apply fuzzy matching in PHP after SQL pre-filter.
     *
     * @param array<object> $rows
     * @param array<array{field: string, term: string, threshold: float, negated: bool}> $fuzzyNodes
     * @return array<object>
     */
    private function applyFuzzyPostFilter(array $rows, array $fuzzyNodes): array
    {
        $filtered = [];

        foreach ($rows as $row) {
            $rowArray = (array) $row;
            $matches = true;
            $maxScore = 0.0;

            foreach ($fuzzyNodes as $fuzzyNode) {
                $field = $fuzzyNode['field'];
                $term = $fuzzyNode['term'];
                $threshold = $fuzzyNode['threshold'];
                $negated = $fuzzyNode['negated'];

                // Resolve field value from row
                $value = $this->getFieldValueFromRow($rowArray, $field);
                if ($value === null) {
                    $matches = !$negated;
                    continue;
                }

                $score = $this->fuzzyMatcher->similarity($term, (string) $value);
                $maxScore = max($maxScore, $score);

                if ($negated) {
                    if ($score >= $threshold) {
                        $matches = false;
                        break;
                    }
                } else {
                    if ($score < $threshold) {
                        $matches = false;
                        break;
                    }
                }
            }

            if ($matches) {
                // Attach fuzzy score to row
                $row->_fuzzyScore = round($maxScore, 4);
                $filtered[] = $row;
            }
        }

        // Sort by fuzzy score descending
        usort($filtered, fn($a, $b) => ($b->_fuzzyScore ?? 0) <=> ($a->_fuzzyScore ?? 0));

        return $filtered;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function getFieldValueFromRow(array $row, string $field): ?string
    {
        // Direct field match
        if (isset($row[$field])) {
            return (string) $row[$field];
        }

        // Map PQL field to column name
        $mapping = match ($field) {
            'name', 'productName' => 'name_de',
            'description' => 'description_de',
            default => $field,
        };

        return isset($row[$mapping]) ? (string) $row[$mapping] : null;
    }

    // ─── Response Formatting ───────────────────────────────────

    /**
     * @param array<object> $rows
     * @return array<array<string, mixed>>
     */
    private function formatResults(array $rows, bool $includeScore): array
    {
        $data = [];
        foreach ($rows as $row) {
            $item = (array) $row;

            // Include _pqlScore if scoring is active
            if ($includeScore && isset($item['_pqlScore'])) {
                $item['_pqlScore'] = round((float) $item['_pqlScore'], 2);
            }

            // Include fuzzy score if present
            if (isset($item['_fuzzyScore'])) {
                $item['_pqlScore'] = ($item['_pqlScore'] ?? 0) + ($item['_fuzzyScore'] * 100);
                $item['_pqlScore'] = round($item['_pqlScore'], 2);
                unset($item['_fuzzyScore']);
            }

            // Remove internal score columns
            foreach (array_keys($item) as $key) {
                if (is_string($key) && str_starts_with($key, '_score_')) {
                    unset($item[$key]);
                }
            }

            $data[] = $item;
        }

        return $data;
    }

    // ─── Cost Estimation ───────────────────────────────────────

    private function estimateCost(SelectNode $ast, array $generated): string
    {
        $factors = [];

        if ($generated['has_fuzzy']) {
            $factors[] = 'FUZZY (PHP post-filter, ~100-200ms)';
        }
        if ($generated['needs_scoring']) {
            $factors[] = 'SCORING (FULLTEXT MATCH, ~50-100ms)';
        }
        if ($ast->hasWhereClause()) {
            $factors[] = 'WHERE (indexed, ~10-50ms)';
        }

        if (empty($factors)) {
            return 'SIMPLE (< 50ms)';
        }

        return implode(' + ', $factors);
    }

    // ─── Cache Key ─────────────────────────────────────────────

    private function buildCacheKey(
        string $pql,
        ?string $mappingId,
        array $languages,
        int $limit,
        int $offset,
    ): string {
        $raw = implode('|', [
            $pql,
            $mappingId ?? '',
            implode(',', $languages),
            (string) $limit,
            (string) $offset,
        ]);

        return self::CACHE_PREFIX . hash('sha256', $raw);
    }
}
