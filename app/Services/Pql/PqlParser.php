<?php

declare(strict_types=1);

namespace App\Services\Pql;

use App\Services\Pql\Ast\ComparisonNode;
use App\Services\Pql\Ast\FuzzyNode;
use App\Services\Pql\Ast\LogicalNode;
use App\Services\Pql\Ast\OrderByScoreNode;
use App\Services\Pql\Ast\SearchFieldsNode;
use App\Services\Pql\Ast\SelectNode;
use App\Services\Pql\Ast\SoundsLikeNode;
use App\Services\Pql\Ast\WhereNode;
use InvalidArgumentException;

/**
 * Pratt parser for PQL (Publixx Query Language).
 *
 * Tokenizes the PQL string and parses it into an AST.
 * No external parser dependencies — fully custom.
 */
final class PqlParser
{
    // Token types
    private const T_SELECT = 'SELECT';
    private const T_FROM = 'FROM';
    private const T_WHERE = 'WHERE';
    private const T_AND = 'AND';
    private const T_OR = 'OR';
    private const T_NOT = 'NOT';
    private const T_LIKE = 'LIKE';
    private const T_IN = 'IN';
    private const T_EXISTS = 'EXISTS';
    private const T_BETWEEN = 'BETWEEN';
    private const T_FUZZY = 'FUZZY';
    private const T_SOUNDS_LIKE = 'SOUNDS_LIKE';
    private const T_SEARCH_FIELDS = 'SEARCH_FIELDS';
    private const T_ORDER = 'ORDER';
    private const T_BY = 'BY';
    private const T_SCORE = 'SCORE';
    private const T_ASC = 'ASC';
    private const T_DESC = 'DESC';
    private const T_TRUE = 'TRUE';
    private const T_FALSE = 'FALSE';

    private const T_IDENT = 'IDENT';
    private const T_STRING = 'STRING';
    private const T_NUMBER = 'NUMBER';
    private const T_STAR = '*';
    private const T_COMMA = ',';
    private const T_LPAREN = '(';
    private const T_RPAREN = ')';
    private const T_CARET = '^';
    private const T_EQ = '=';
    private const T_NEQ = '!=';
    private const T_NEQ2 = '<>';
    private const T_GT = '>';
    private const T_LT = '<';
    private const T_GTE = '>=';
    private const T_LTE = '<=';
    private const T_EOF = 'EOF';

    private const KEYWORDS = [
        'SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'NOT', 'LIKE', 'IN',
        'EXISTS', 'BETWEEN', 'FUZZY', 'SOUNDS_LIKE', 'SEARCH_FIELDS',
        'ORDER', 'BY', 'SCORE', 'ASC', 'DESC', 'TRUE', 'FALSE',
    ];

    // Binding powers for Pratt parser (higher = tighter binding)
    private const BP_NONE = 0;
    private const BP_OR = 10;
    private const BP_AND = 20;
    private const BP_COMPARISON = 30;

    /** @var array<int, array{type: string, value: string, pos: int}> */
    private array $tokens = [];
    private int $pos = 0;

    /**
     * Parse a PQL string into a SelectNode AST.
     *
     * @throws InvalidArgumentException on syntax errors
     */
    public function parse(string $pql): SelectNode
    {
        $this->tokens = $this->tokenize(trim($pql));
        $this->pos = 0;

        return $this->parseSelect();
    }

    // ─── Tokenizer ──────────────────────────────────────────────

    /**
     * @return array<int, array{type: string, value: string, pos: int}>
     */
    private function tokenize(string $input): array
    {
        $tokens = [];
        $len = strlen($input);
        $i = 0;

        while ($i < $len) {
            // Skip whitespace
            if (ctype_space($input[$i])) {
                $i++;
                continue;
            }

            $startPos = $i;

            // Two-character operators
            if ($i + 1 < $len) {
                $twoChar = substr($input, $i, 2);
                if ($twoChar === '!=') {
                    $tokens[] = ['type' => self::T_NEQ, 'value' => '!=', 'pos' => $startPos];
                    $i += 2;
                    continue;
                }
                if ($twoChar === '<>') {
                    $tokens[] = ['type' => self::T_NEQ2, 'value' => '<>', 'pos' => $startPos];
                    $i += 2;
                    continue;
                }
                if ($twoChar === '>=') {
                    $tokens[] = ['type' => self::T_GTE, 'value' => '>=', 'pos' => $startPos];
                    $i += 2;
                    continue;
                }
                if ($twoChar === '<=') {
                    $tokens[] = ['type' => self::T_LTE, 'value' => '<=', 'pos' => $startPos];
                    $i += 2;
                    continue;
                }
            }

            // Single-character operators/symbols
            $ch = $input[$i];
            $singleTokenMap = [
                '=' => self::T_EQ,
                '>' => self::T_GT,
                '<' => self::T_LT,
                '*' => self::T_STAR,
                ',' => self::T_COMMA,
                '(' => self::T_LPAREN,
                ')' => self::T_RPAREN,
                '^' => self::T_CARET,
            ];

            if (isset($singleTokenMap[$ch])) {
                $tokens[] = ['type' => $singleTokenMap[$ch], 'value' => $ch, 'pos' => $startPos];
                $i++;
                continue;
            }

            // Quoted strings (single or double)
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $i++; // skip opening quote
                $str = '';
                while ($i < $len && $input[$i] !== $quote) {
                    if ($input[$i] === '\\' && $i + 1 < $len) {
                        $str .= $input[$i + 1];
                        $i += 2;
                    } else {
                        $str .= $input[$i];
                        $i++;
                    }
                }
                if ($i >= $len) {
                    throw new InvalidArgumentException("Unterminated string at position {$startPos}");
                }
                $i++; // skip closing quote
                $tokens[] = ['type' => self::T_STRING, 'value' => $str, 'pos' => $startPos];
                continue;
            }

            // Numbers (including decimals and negative)
            if (is_numeric($ch) || ($ch === '-' && $i + 1 < $len && is_numeric($input[$i + 1]))) {
                $num = '';
                if ($ch === '-') {
                    $num = '-';
                    $i++;
                }
                while ($i < $len && (is_numeric($input[$i]) || $input[$i] === '.')) {
                    $num .= $input[$i];
                    $i++;
                }
                $tokens[] = ['type' => self::T_NUMBER, 'value' => $num, 'pos' => $startPos];
                continue;
            }

            // Identifiers / Keywords
            if (ctype_alpha($ch) || $ch === '_') {
                $ident = '';
                while ($i < $len && (ctype_alnum($input[$i]) || $input[$i] === '_' || $input[$i] === '.')) {
                    $ident .= $input[$i];
                    $i++;
                }
                $upper = strtoupper($ident);
                $type = in_array($upper, self::KEYWORDS, true) ? $upper : self::T_IDENT;
                $tokens[] = ['type' => $type, 'value' => $ident, 'pos' => $startPos];
                continue;
            }

            throw new InvalidArgumentException("Unexpected character '{$ch}' at position {$i}");
        }

        $tokens[] = ['type' => self::T_EOF, 'value' => '', 'pos' => $len];
        return $tokens;
    }

    // ─── Token Helpers ──────────────────────────────────────────

    /**
     * @return array{type: string, value: string, pos: int}
     */
    private function current(): array
    {
        return $this->tokens[$this->pos] ?? ['type' => self::T_EOF, 'value' => '', 'pos' => 0];
    }

    /**
     * @return array{type: string, value: string, pos: int}
     */
    private function peek(int $offset = 1): array
    {
        return $this->tokens[$this->pos + $offset] ?? ['type' => self::T_EOF, 'value' => '', 'pos' => 0];
    }

    /**
     * @return array{type: string, value: string, pos: int}
     */
    private function advance(): array
    {
        $token = $this->current();
        $this->pos++;
        return $token;
    }

    private function expect(string $type): array
    {
        $token = $this->current();
        if ($token['type'] !== $type) {
            throw new InvalidArgumentException(
                "Expected {$type}, got {$token['type']} ('{$token['value']}') at position {$token['pos']}"
            );
        }
        return $this->advance();
    }

    private function match(string $type): bool
    {
        if ($this->current()['type'] === $type) {
            $this->advance();
            return true;
        }
        return false;
    }

    private function check(string $type): bool
    {
        return $this->current()['type'] === $type;
    }

    // ─── Recursive Descent / Pratt Parsing ──────────────────────

    private function parseSelect(): SelectNode
    {
        $fields = ['*'];
        $source = 'data';
        $where = null;
        $orderByScore = null;

        // SELECT [fields]
        if ($this->match(self::T_SELECT)) {
            $fields = $this->parseFieldList();
        }

        // FROM [source]
        if ($this->match(self::T_FROM)) {
            $source = $this->expect(self::T_IDENT)['value'];
        }

        // WHERE [conditions]
        if ($this->match(self::T_WHERE)) {
            $expression = $this->parseExpression(self::BP_NONE);
            $where = new WhereNode($expression);
        }

        // ORDER BY SCORE ASC|DESC
        if ($this->check(self::T_ORDER)) {
            $orderByScore = $this->parseOrderByScore();
        }

        // Verify we consumed everything
        if (!$this->check(self::T_EOF)) {
            $tok = $this->current();
            throw new InvalidArgumentException(
                "Unexpected token '{$tok['value']}' at position {$tok['pos']} (expected end of query)"
            );
        }

        return new SelectNode(
            fields: $fields,
            source: $source,
            where: $where,
            orderByScore: $orderByScore,
        );
    }

    /**
     * Parse ORDER BY SCORE [ASC|DESC].
     */
    private function parseOrderByScore(): OrderByScoreNode
    {
        $this->expect(self::T_ORDER);
        $this->expect(self::T_BY);
        $this->expect(self::T_SCORE);

        $direction = 'DESC';

        if ($this->check(self::T_ASC)) {
            $direction = 'ASC';
            $this->advance();
        } elseif ($this->check(self::T_DESC)) {
            $direction = 'DESC';
            $this->advance();
        }

        return new OrderByScoreNode($direction);
    }

    /**
     * @return array<string>
     */
    private function parseFieldList(): array
    {
        if ($this->match(self::T_STAR)) {
            return ['*'];
        }

        $fields = [];
        $fields[] = $this->parseFieldName();

        while ($this->match(self::T_COMMA)) {
            $fields[] = $this->parseFieldName();
        }

        return $fields;
    }

    private function parseFieldName(): string
    {
        $token = $this->current();
        if ($token['type'] === self::T_IDENT || in_array($token['type'], self::KEYWORDS, true)) {
            $this->advance();
            return $token['value'];
        }
        throw new InvalidArgumentException(
            "Expected field name, got {$token['type']} at position {$token['pos']}"
        );
    }

    /**
     * Pratt parser: parse expression with binding power.
     */
    private function parseExpression(int $minBp): ComparisonNode|LogicalNode|FuzzyNode|SoundsLikeNode|SearchFieldsNode
    {
        $left = $this->parsePrimary();

        while (true) {
            $token = $this->current();
            $bp = $this->getInfixBindingPower($token['type']);

            if ($bp <= $minBp) {
                break;
            }

            if ($token['type'] === self::T_AND) {
                $this->advance();
                $right = $this->parseExpression($bp);
                $left = new LogicalNode('AND', $left, $right);
            } elseif ($token['type'] === self::T_OR) {
                $this->advance();
                $right = $this->parseExpression($bp);
                $left = new LogicalNode('OR', $left, $right);
            } else {
                break;
            }
        }

        return $left;
    }

    private function getInfixBindingPower(string $type): int
    {
        return match ($type) {
            self::T_OR => self::BP_OR,
            self::T_AND => self::BP_AND,
            default => self::BP_NONE,
        };
    }

    /**
     * Parse a primary expression (comparison, fuzzy, sounds_like, search_fields, or NOT prefix).
     */
    private function parsePrimary(): ComparisonNode|LogicalNode|FuzzyNode|SoundsLikeNode|SearchFieldsNode
    {
        $token = $this->current();

        // SEARCH_FIELDS(...)
        if ($token['type'] === self::T_SEARCH_FIELDS) {
            return $this->parseSearchFields();
        }

        // NOT prefix
        if ($token['type'] === self::T_NOT) {
            return $this->parseNotPrefix();
        }

        // Field-based expression: field OPERATOR value
        if ($token['type'] === self::T_IDENT || $token['type'] === self::T_STRING) {
            return $this->parseFieldExpression();
        }

        throw new InvalidArgumentException(
            "Unexpected token '{$token['value']}' at position {$token['pos']}"
        );
    }

    /**
     * NOT prefix: can negate LIKE, IN, EXISTS, BETWEEN, FUZZY, SOUNDS_LIKE
     */
    private function parseNotPrefix(): ComparisonNode|FuzzyNode|SoundsLikeNode
    {
        $this->advance(); // consume NOT
        $token = $this->current();

        // NOT is often combined: "field NOT LIKE", but can appear as "NOT EXISTS field"
        // Actually in PQL: field NOT LIKE / field NOT IN, etc.
        // But also: standalone NOT before a keyword

        throw new InvalidArgumentException(
            "Standalone NOT not supported. Use: field NOT LIKE, field NOT IN, etc. At position {$token['pos']}"
        );
    }

    /**
     * Parse a field-based expression:
     *   field = value
     *   field LIKE 'pattern'
     *   field NOT LIKE 'pattern'
     *   field IN ('a', 'b')
     *   field NOT IN ('a', 'b')
     *   field EXISTS
     *   field NOT EXISTS
     *   field BETWEEN x AND y
     *   field NOT BETWEEN x AND y
     *   field FUZZY 'text' [threshold]
     *   field NOT FUZZY 'text' [threshold]
     *   field SOUNDS_LIKE 'text'
     *   field NOT SOUNDS_LIKE 'text'
     */
    private function parseFieldExpression(): ComparisonNode|FuzzyNode|SoundsLikeNode
    {
        $fieldToken = $this->advance();
        $field = $fieldToken['value'];
        $token = $this->current();
        $negated = false;

        // NOT prefix on operator
        if ($token['type'] === self::T_NOT) {
            $negated = true;
            $this->advance();
            $token = $this->current();
        }

        return match ($token['type']) {
            self::T_EQ => $this->parseSimpleComparison($field, '='),
            self::T_NEQ, self::T_NEQ2 => $this->parseSimpleComparison($field, '!='),
            self::T_GT => $this->parseSimpleComparison($field, '>'),
            self::T_LT => $this->parseSimpleComparison($field, '<'),
            self::T_GTE => $this->parseSimpleComparison($field, '>='),
            self::T_LTE => $this->parseSimpleComparison($field, '<='),
            self::T_LIKE => $this->parseLike($field, $negated),
            self::T_IN => $this->parseIn($field, $negated),
            self::T_EXISTS => $this->parseExists($field, $negated),
            self::T_BETWEEN => $this->parseBetween($field, $negated),
            self::T_FUZZY => $this->parseFuzzy($field, $negated),
            self::T_SOUNDS_LIKE => $this->parseSoundsLike($field, $negated),
            default => throw new InvalidArgumentException(
                "Expected operator after field '{$field}', got '{$token['value']}' at position {$token['pos']}"
            ),
        };
    }

    private function parseSimpleComparison(string $field, string $operator): ComparisonNode
    {
        $this->advance(); // consume operator
        $value = $this->parseValue();
        return new ComparisonNode($field, $operator, $value);
    }

    private function parseLike(string $field, bool $negated): ComparisonNode
    {
        $this->advance(); // consume LIKE
        $value = $this->expect(self::T_STRING)['value'];
        $operator = $negated ? 'NOT LIKE' : 'LIKE';
        return new ComparisonNode($field, $operator, $value, $negated);
    }

    private function parseIn(string $field, bool $negated): ComparisonNode
    {
        $this->advance(); // consume IN
        $this->expect(self::T_LPAREN);
        $values = [$this->parseValue()];

        while ($this->match(self::T_COMMA)) {
            $values[] = $this->parseValue();
        }

        $this->expect(self::T_RPAREN);
        $operator = $negated ? 'NOT IN' : 'IN';
        return new ComparisonNode($field, $operator, $values, $negated);
    }

    private function parseExists(string $field, bool $negated): ComparisonNode
    {
        $this->advance(); // consume EXISTS
        $operator = $negated ? 'NOT EXISTS' : 'EXISTS';
        return new ComparisonNode($field, $operator, null, $negated);
    }

    private function parseBetween(string $field, bool $negated): ComparisonNode
    {
        $this->advance(); // consume BETWEEN
        $min = $this->parseValue();
        $this->expect(self::T_AND);
        $max = $this->parseValue();
        $operator = $negated ? 'NOT BETWEEN' : 'BETWEEN';
        return new ComparisonNode($field, $operator, [$min, $max], $negated);
    }

    private function parseFuzzy(string $field, bool $negated): FuzzyNode
    {
        $this->advance(); // consume FUZZY
        $term = $this->expect(self::T_STRING)['value'];
        $threshold = 0.7;

        // Optional threshold value
        if ($this->check(self::T_NUMBER)) {
            $threshold = (float) $this->advance()['value'];
            $threshold = max(0.0, min(1.0, $threshold));
        }

        return new FuzzyNode($field, $term, $threshold, $negated);
    }

    private function parseSoundsLike(string $field, bool $negated): SoundsLikeNode
    {
        $this->advance(); // consume SOUNDS_LIKE
        $term = $this->expect(self::T_STRING)['value'];
        return new SoundsLikeNode($field, $term, $negated);
    }

    /**
     * Parse SEARCH_FIELDS(field1^boost1, field2^boost2, ...) OPERATOR 'value'
     */
    private function parseSearchFields(): SearchFieldsNode
    {
        $this->advance(); // consume SEARCH_FIELDS
        $this->expect(self::T_LPAREN);

        $fields = [];
        $this->parseSearchField($fields);

        while ($this->match(self::T_COMMA)) {
            $this->parseSearchField($fields);
        }

        $this->expect(self::T_RPAREN);

        // Now parse the condition: FUZZY, LIKE, SOUNDS_LIKE, or =
        $token = $this->current();
        $firstField = array_key_first($fields);

        $condition = match ($token['type']) {
            self::T_FUZZY => $this->parseFuzzy($firstField, false),
            self::T_SOUNDS_LIKE => $this->parseSoundsLike($firstField, false),
            self::T_LIKE => $this->parseLike($firstField, false),
            self::T_EQ => $this->parseSimpleComparison($firstField, '='),
            default => throw new InvalidArgumentException(
                "Expected FUZZY, SOUNDS_LIKE, LIKE, or = after SEARCH_FIELDS, got '{$token['value']}' at position {$token['pos']}"
            ),
        };

        return new SearchFieldsNode($fields, $condition);
    }

    /**
     * @param array<string, float> $fields
     */
    private function parseSearchField(array &$fields): void
    {
        $token = $this->current();
        if ($token['type'] !== self::T_IDENT) {
            throw new InvalidArgumentException(
                "Expected field name in SEARCH_FIELDS, got '{$token['value']}' at position {$token['pos']}"
            );
        }
        $fieldName = $this->advance()['value'];
        $boost = 1.0;

        if ($this->match(self::T_CARET)) {
            $boost = (float) $this->expect(self::T_NUMBER)['value'];
        }

        $fields[$fieldName] = $boost;
    }

    /**
     * Parse a literal value: string, number, boolean.
     */
    private function parseValue(): string|int|float|bool
    {
        $token = $this->current();

        return match ($token['type']) {
            self::T_STRING => (function () {
                $v = $this->advance()['value'];
                return $v;
            })(),
            self::T_NUMBER => (function () {
                $v = $this->advance()['value'];
                return str_contains($v, '.') ? (float) $v : (int) $v;
            })(),
            self::T_TRUE => (function () {
                $this->advance();
                return true;
            })(),
            self::T_FALSE => (function () {
                $this->advance();
                return false;
            })(),
            default => throw new InvalidArgumentException(
                "Expected value, got {$token['type']} ('{$token['value']}') at position {$token['pos']}"
            ),
        };
    }

    /**
     * Get all tokens for debugging / explain.
     *
     * @return array<int, array{type: string, value: string, pos: int}>
     */
    public function tokenize_debug(string $pql): array
    {
        return $this->tokenize(trim($pql));
    }
}
