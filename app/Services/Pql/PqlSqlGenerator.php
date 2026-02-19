<?php

declare(strict_types=1);

namespace App\Services\Pql;

use App\Services\Pql\Ast\ComparisonNode;
use App\Services\Pql\Ast\FuzzyNode;
use App\Services\Pql\Ast\LogicalNode;
use App\Services\Pql\Ast\SearchFieldsNode;
use App\Services\Pql\Ast\SelectNode;
use App\Services\Pql\Ast\SoundsLikeNode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Transpiles a PQL AST into parameterized MySQL via Laravel Query Builder.
 *
 * NEVER uses string concatenation for SQL — all values go through bindings.
 *
 * Strategy:
 * - Simple field queries → products_search_index (denormalized, fast)
 * - EAV attribute queries → JOIN product_attribute_values
 * - FUZZY → FULLTEXT pre-filter in SQL, post-filter in PHP (PqlExecutor)
 * - SEARCH_FIELDS → weighted MATCH AGAINST with boost multipliers
 */
final class PqlSqlGenerator
{
    /**
     * Base fields that map directly to products / products_search_index columns.
     */
    private const BASE_FIELD_MAP = [
        'status'       => ['psi' => 'status',         'p' => 'status'],
        'sku'          => ['psi' => 'sku',             'p' => 'sku'],
        'ean'          => ['psi' => 'ean',             'p' => 'ean'],
        'name'         => ['psi' => 'name_de',         'p' => 'name'],
        'hierarchy'    => ['psi' => 'hierarchy_path'],
        'product_type' => ['psi' => 'product_type'],
        'list_price'   => ['psi' => 'list_price'],
    ];

    /**
     * @var array<string, array<string, mixed>> Resolved fields from validator
     */
    private array $resolvedFields = [];

    /**
     * @var int Counter for EAV join aliases
     */
    private int $eavJoinCounter = 0;

    /**
     * @var bool Whether the query needs scoring (SEARCH_FIELDS)
     */
    private bool $needsScoring = false;

    /**
     * @var array<string, string> Score expressions for ORDER BY SCORE
     */
    private array $scoreExpressions = [];

    /**
     * @var string Current language for i18n resolution
     */
    private string $language = 'de';

    /**
     * @var bool Whether the query contains FUZZY and needs PHP post-filtering
     */
    private bool $hasFuzzy = false;

    /**
     * @var array<array{field: string, term: string, threshold: float, negated: bool}> Fuzzy nodes for PHP post-filter
     */
    private array $fuzzyNodes = [];

    /**
     * Generate a SQL query from the AST.
     *
     * @param SelectNode $ast Parsed AST
     * @param array<string, array<string, mixed>> $resolvedFields From PqlValidator
     * @param string $language Primary language code
     * @return array{query: Builder, has_fuzzy: bool, fuzzy_nodes: array, needs_scoring: bool, score_expressions: array}
     */
    public function generate(SelectNode $ast, array $resolvedFields, string $language = 'de'): array
    {
        $this->resolvedFields = $resolvedFields;
        $this->eavJoinCounter = 0;
        $this->needsScoring = false;
        $this->scoreExpressions = [];
        $this->language = $language;
        $this->hasFuzzy = false;
        $this->fuzzyNodes = [];

        $query = DB::table('products as p')
            ->join('products_search_index as psi', 'p.id', '=', 'psi.product_id');

        // Apply WHERE conditions
        if ($ast->hasWhereClause()) {
            $this->applyExpression($query, $ast->where->expression);
        }

        // SELECT clause
        if ($ast->hasWildcardSelect()) {
            $query->select(['p.*', 'psi.name_de', 'psi.description_de', 'psi.hierarchy_path', 'psi.list_price', 'psi.attribute_completeness', 'psi.phonetic_name_de']);
        } else {
            $selects = ['p.id'];
            foreach ($ast->fields as $field) {
                $col = $this->resolveColumn($field);
                if ($col !== null) {
                    $selects[] = $col;
                }
            }
            $query->select(array_unique($selects));
        }

        // Add score columns
        if ($this->needsScoring) {
            foreach ($this->scoreExpressions as $alias => $expr) {
                $query->selectRaw("{$expr} AS {$alias}");
            }

            // Total score
            if (count($this->scoreExpressions) > 0) {
                $totalExpr = implode(' + ', array_keys($this->scoreExpressions));
                $query->selectRaw("({$totalExpr}) AS _pqlScore");
            }
        }

        // ORDER BY SCORE
        if ($ast->hasOrderByScore() && $this->needsScoring) {
            $query->orderByRaw("_pqlScore {$ast->orderByScore->direction}");
        }

        // LIMIT / OFFSET — for fuzzy, we fetch more for PHP post-filter
        $limit = $this->hasFuzzy ? min($ast->limit * 5, 500) : $ast->limit;
        $query->limit($limit)->offset($ast->offset);

        return [
            'query' => $query,
            'has_fuzzy' => $this->hasFuzzy,
            'fuzzy_nodes' => $this->fuzzyNodes,
            'needs_scoring' => $this->needsScoring,
            'score_expressions' => $this->scoreExpressions,
        ];
    }

    /**
     * Get the generated SQL and bindings for debugging/explain.
     *
     * @return array{sql: string, bindings: array<mixed>}
     */
    public function toSql(Builder $query): array
    {
        return [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ];
    }

    // ─── Expression Application ────────────────────────────────

    private function applyExpression(
        Builder $query,
        ComparisonNode|LogicalNode|FuzzyNode|SoundsLikeNode|SearchFieldsNode $node,
    ): void {
        if ($node instanceof LogicalNode) {
            $this->applyLogical($query, $node);
            return;
        }

        if ($node instanceof ComparisonNode) {
            $this->applyComparison($query, $node);
            return;
        }

        if ($node instanceof FuzzyNode) {
            $this->applyFuzzy($query, $node);
            return;
        }

        if ($node instanceof SoundsLikeNode) {
            $this->applySoundsLike($query, $node);
            return;
        }

        if ($node instanceof SearchFieldsNode) {
            $this->applySearchFields($query, $node);
            return;
        }
    }

    private function applyLogical(Builder $query, LogicalNode $node): void
    {
        if ($node->operator === 'AND') {
            $this->applyExpression($query, $node->left);
            $this->applyExpression($query, $node->right);
        } elseif ($node->operator === 'OR') {
            $query->where(function (Builder $q) use ($node): void {
                $q->where(function (Builder $inner) use ($node): void {
                    $this->applyExpression($inner, $node->left);
                });
                $q->orWhere(function (Builder $inner) use ($node): void {
                    $this->applyExpression($inner, $node->right);
                });
            });
        }
    }

    private function applyComparison(Builder $query, ComparisonNode $node): void
    {
        $field = $node->field;
        $column = $this->resolveColumnForWhere($query, $field);

        if ($node->isExistenceCheck()) {
            $this->applyExistence($query, $field, $node->operator === 'NOT EXISTS');
            return;
        }

        if ($node->isBetween()) {
            [$min, $max] = $node->value;
            $numericColumn = $this->resolveNumericColumn($query, $field);
            if ($node->negated) {
                $query->whereNotBetween($numericColumn, [$min, $max]);
            } else {
                $query->whereBetween($numericColumn, [$min, $max]);
            }
            return;
        }

        if ($node->isIn()) {
            if ($node->negated) {
                $query->whereNotIn($column, $node->value);
            } else {
                $query->whereIn($column, $node->value);
            }
            return;
        }

        if ($node->isLike()) {
            $pattern = $node->value;
            $isWrapped = str_starts_with($pattern, '%') && str_ends_with($pattern, '%');

            // Use FULLTEXT for %text% patterns on search_index fields
            if ($isWrapped && $this->isFulltextField($field)) {
                $searchTerm = trim($pattern, '%');
                $ftColumn = $this->resolveFulltextColumn($field);
                if ($node->negated) {
                    $query->whereRaw("NOT MATCH({$ftColumn}) AGAINST(? IN BOOLEAN MODE)", [$searchTerm]);
                } else {
                    $query->whereRaw("MATCH({$ftColumn}) AGAINST(? IN BOOLEAN MODE)", [$searchTerm]);
                }
            } else {
                if ($node->negated) {
                    $query->where($column, 'NOT LIKE', $pattern);
                } else {
                    $query->where($column, 'LIKE', $pattern);
                }
            }
            return;
        }

        // Simple comparison: =, !=, >, <, >=, <=
        $operator = match ($node->operator) {
            '!=' => '!=',
            '<>' => '!=',
            default => $node->operator,
        };

        $query->where($column, $operator, $node->value);
    }

    private function applyFuzzy(Builder $query, FuzzyNode $node): void
    {
        $this->hasFuzzy = true;
        $this->fuzzyNodes[] = [
            'field' => $node->field,
            'term' => $node->term,
            'threshold' => $node->threshold,
            'negated' => $node->negated,
        ];

        // SQL: FULLTEXT pre-filter (wider net, then PHP post-filter)
        if ($this->isFulltextField($node->field)) {
            $ftColumn = $this->resolveFulltextColumn($node->field);
            $query->whereRaw(
                "MATCH({$ftColumn}) AGAINST(? IN BOOLEAN MODE)",
                [$node->term . '*']
            );
        } else {
            // For EAV fields, use LIKE as pre-filter
            $column = $this->resolveColumnForWhere($query, $node->field);
            $query->where($column, 'LIKE', '%' . $node->term . '%');
        }
    }

    private function applySoundsLike(Builder $query, SoundsLikeNode $node): void
    {
        $field = $node->field;
        $column = $this->resolveColumnForWhere($query, $field);

        // Use phonetic_name_de from search_index if available for name fields
        if (in_array($field, ['name', 'productName'], true)) {
            if ($node->negated) {
                $query->where(function (Builder $q) use ($node): void {
                    $q->whereRaw('psi.phonetic_name_de != ?', [
                        app(PhoneticMatcher::class)->koelnerPhonetik($node->term),
                    ]);
                    $q->whereRaw('SOUNDEX(psi.name_de) != SOUNDEX(?)', [$node->term]);
                });
            } else {
                $query->where(function (Builder $q) use ($node): void {
                    $q->whereRaw('psi.phonetic_name_de = ?', [
                        app(PhoneticMatcher::class)->koelnerPhonetik($node->term),
                    ]);
                    $q->orWhereRaw('SOUNDEX(psi.name_de) = SOUNDEX(?)', [$node->term]);
                });
            }
        } else {
            // Generic SOUNDEX for other fields
            if ($node->negated) {
                $query->whereRaw("SOUNDEX({$column}) != SOUNDEX(?)", [$node->term]);
            } else {
                $query->whereRaw("SOUNDEX({$column}) = SOUNDEX(?)", [$node->term]);
            }
        }
    }

    private function applySearchFields(Builder $query, SearchFieldsNode $node): void
    {
        $this->needsScoring = true;
        $condition = $node->condition;
        $scoreIndex = 0;

        // Build OR condition across all fields + score expressions
        $query->where(function (Builder $q) use ($node, $condition, &$scoreIndex): void {
            $first = true;
            foreach ($node->fields as $field => $boost) {
                $method = $first ? 'where' : 'orWhere';
                $first = false;

                if ($condition instanceof FuzzyNode) {
                    // FULLTEXT pre-filter across fields
                    $this->hasFuzzy = true;
                    $this->fuzzyNodes[] = [
                        'field' => $field,
                        'term' => $condition->term,
                        'threshold' => $condition->threshold,
                        'negated' => $condition->negated,
                    ];

                    if ($this->isFulltextField($field)) {
                        $ftColumn = $this->resolveFulltextColumn($field);
                        $q->{$method}(function (Builder $inner) use ($ftColumn, $condition): void {
                            $inner->whereRaw(
                                "MATCH({$ftColumn}) AGAINST(? IN BOOLEAN MODE)",
                                [$condition->term . '*']
                            );
                        });

                        // Score expression
                        $alias = "_score_{$scoreIndex}";
                        $this->scoreExpressions[$alias] = "MATCH({$ftColumn}) AGAINST('{$condition->term}' IN BOOLEAN MODE) * {$boost}";
                        $scoreIndex++;
                    }
                } elseif ($condition instanceof ComparisonNode && $condition->isLike()) {
                    if ($this->isFulltextField($field)) {
                        $ftColumn = $this->resolveFulltextColumn($field);
                        $searchTerm = trim((string) $condition->value, '%');
                        $q->{$method}(function (Builder $inner) use ($ftColumn, $searchTerm): void {
                            $inner->whereRaw(
                                "MATCH({$ftColumn}) AGAINST(? IN BOOLEAN MODE)",
                                [$searchTerm]
                            );
                        });

                        $alias = "_score_{$scoreIndex}";
                        $this->scoreExpressions[$alias] = "MATCH({$ftColumn}) AGAINST('{$searchTerm}' IN BOOLEAN MODE) * {$boost}";
                        $scoreIndex++;
                    }
                } elseif ($condition instanceof SoundsLikeNode) {
                    $this->applySoundsLikeForField($q, $field, $condition, $method);
                }
            }
        });
    }

    private function applySoundsLikeForField(Builder $query, string $field, SoundsLikeNode $condition, string $method): void
    {
        if ($this->isFulltextField($field)) {
            $ftColumn = $this->resolveFulltextColumn($field);
            $query->{$method}(function (Builder $inner) use ($ftColumn, $condition): void {
                $inner->whereRaw("SOUNDEX({$ftColumn}) = SOUNDEX(?)", [$condition->term]);
            });
        }
    }

    // ─── Existence Checks ──────────────────────────────────────

    private function applyExistence(Builder $query, string $field, bool $notExists): void
    {
        if ($this->isBaseField($field)) {
            $column = $this->resolveBaseColumn($field);
            if ($notExists) {
                $query->whereNull($column);
            } else {
                $query->whereNotNull($column);
            }
            return;
        }

        // EAV: check if product_attribute_values has a row
        $alias = $this->getEavAlias();
        $resolved = $this->resolvedFields[$field] ?? null;
        $attrId = $resolved['attribute_id'] ?? null;

        if ($attrId !== null) {
            if ($notExists) {
                $query->leftJoin("product_attribute_values as {$alias}", function ($join) use ($alias, $attrId): void {
                    $join->on("{$alias}.product_id", '=', 'p.id')
                         ->where("{$alias}.attribute_id", '=', $attrId);
                });
                $query->whereNull("{$alias}.id");
            } else {
                $query->join("product_attribute_values as {$alias}", function ($join) use ($alias, $attrId): void {
                    $join->on("{$alias}.product_id", '=', 'p.id')
                         ->where("{$alias}.attribute_id", '=', $attrId);
                });
            }
        }
    }

    // ─── Column Resolution ────────────────────────────────────

    private function isBaseField(string $field): bool
    {
        return isset(self::BASE_FIELD_MAP[$field]);
    }

    private function isFulltextField(string $field): bool
    {
        return in_array($field, ['name', 'productName', 'description', 'name_de', 'name_en', 'description_de'], true);
    }

    private function resolveFulltextColumn(string $field): string
    {
        return match ($field) {
            'name', 'productName', 'name_de' => 'psi.name_de',
            'name_en'                        => 'psi.name_en',
            'description', 'description_de'  => 'psi.description_de',
            default                          => 'psi.name_de',
        };
    }

    private function resolveBaseColumn(string $field): string
    {
        $map = self::BASE_FIELD_MAP[$field] ?? null;
        if ($map === null) {
            return "p.{$field}";
        }
        return isset($map['psi']) ? "psi.{$map['psi']}" : "p.{$map['p']}";
    }

    private function resolveColumn(string $field): ?string
    {
        if ($this->isBaseField($field)) {
            return $this->resolveBaseColumn($field);
        }
        // For select, we can't resolve EAV easily — skip
        return null;
    }

    /**
     * Resolve a field to a column for WHERE clause, adding EAV JOINs as needed.
     */
    private function resolveColumnForWhere(Builder $query, string $field): string
    {
        if ($this->isBaseField($field)) {
            return $this->resolveBaseColumn($field);
        }

        // Map well-known PQL field names to search index
        $psiMapping = match ($field) {
            'productName' => 'psi.name_de',
            'description' => 'psi.description_de',
            'price'       => 'psi.list_price',
            default       => null,
        };

        if ($psiMapping !== null) {
            return $psiMapping;
        }

        // EAV: JOIN product_attribute_values
        $resolved = $this->resolvedFields[$field] ?? null;
        $attrId = $resolved['attribute_id'] ?? null;

        if ($attrId !== null) {
            $alias = $this->getEavAlias();
            $query->join("product_attribute_values as {$alias}", function ($join) use ($alias, $attrId): void {
                $join->on("{$alias}.product_id", '=', 'p.id')
                     ->where("{$alias}.attribute_id", '=', $attrId);
            });

            $dataType = $resolved['data_type'] ?? 'String';
            return match ($dataType) {
                'Number', 'Float' => "{$alias}.value_number",
                'Date'            => "{$alias}.value_date",
                'Flag'            => "{$alias}.value_flag",
                default           => "{$alias}.value_string",
            };
        }

        // Fallback: assume it's on products_search_index
        return "psi.{$field}";
    }

    /**
     * Resolve a field to its numeric column.
     */
    private function resolveNumericColumn(Builder $query, string $field): string
    {
        if ($field === 'price' || $field === 'list_price') {
            return 'psi.list_price';
        }

        $resolved = $this->resolvedFields[$field] ?? null;
        if ($resolved !== null && isset($resolved['attribute_id'])) {
            $alias = $this->getEavAlias();
            $query->join("product_attribute_values as {$alias}", function ($join) use ($alias, $resolved): void {
                $join->on("{$alias}.product_id", '=', 'p.id')
                     ->where("{$alias}.attribute_id", '=', $resolved['attribute_id']);
            });
            return "{$alias}.value_number";
        }

        return $this->resolveColumnForWhere($query, $field);
    }

    private function getEavAlias(): string
    {
        $this->eavJoinCounter++;
        return "pav_{$this->eavJoinCounter}";
    }
}
