<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Sicherer mathematischer Expression-Evaluator für Composite-Attribute.
 *
 * Unterstützt:
 * - Platzhalter: {0}, {1}, {2}, ...
 * - Operatoren: +, -, *, /
 * - Klammern: (, )
 * - Konstanten: Integer und Float-Zahlen
 *
 * Kein eval()! Nutzt einen rekursiven Descent-Parser mit korrektem
 * Operator-Vorrang (* und / vor + und -).
 */
class CompositeExpressionEvaluator
{
    /** @var list<array{type: string, value: string|float|int}> */
    private array $tokens = [];

    private int $pos = 0;

    /**
     * Evaluiert eine Expression mit den gegebenen Werten.
     *
     * @param  string      $expression z.B. "{0} * {1} * {2}"
     * @param  array<int, float|null> $values     Index → Wert (null = fehlend)
     * @return float|null  Ergebnis oder null bei Fehler/fehlenden Werten
     */
    public function evaluate(string $expression, array $values): ?float
    {
        try {
            $this->tokens = $this->tokenize($expression);
            $this->pos = 0;

            $result = $this->parseExpression($values);

            // Alle Tokens müssen verbraucht sein
            if ($this->pos < count($this->tokens)) {
                return null;
            }

            return $result;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Validiert eine Expression-Syntax (ohne Werte).
     */
    public function validate(string $expression): bool
    {
        try {
            $tokens = $this->tokenize($expression);

            if (empty($tokens)) {
                return false;
            }

            // Probe-Evaluation mit Dummy-Werten
            $maxIndex = -1;
            foreach ($tokens as $token) {
                if ($token['type'] === 'placeholder') {
                    $maxIndex = max($maxIndex, (int) $token['value']);
                }
            }

            $dummyValues = array_fill(0, $maxIndex + 1, 1.0);

            $this->tokens = $tokens;
            $this->pos = 0;
            $this->parseExpression($dummyValues);

            return $this->pos === count($this->tokens);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Tokenizer: Zerlegt die Expression in Tokens.
     *
     * @return list<array{type: string, value: string|float|int}>
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $len = strlen($expression);
        $i = 0;

        while ($i < $len) {
            $ch = $expression[$i];

            // Whitespace überspringen
            if ($ch === ' ' || $ch === "\t") {
                $i++;
                continue;
            }

            // Platzhalter: {0}, {1}, ...
            if ($ch === '{') {
                $end = strpos($expression, '}', $i);
                if ($end === false) {
                    throw new \InvalidArgumentException("Ungeschlossener Platzhalter bei Position {$i}");
                }
                $index = substr($expression, $i + 1, $end - $i - 1);
                if (!ctype_digit($index)) {
                    throw new \InvalidArgumentException("Ungültiger Platzhalter-Index: {$index}");
                }
                $tokens[] = ['type' => 'placeholder', 'value' => (int) $index];
                $i = $end + 1;
                continue;
            }

            // Zahl (Integer oder Float)
            if (ctype_digit($ch) || ($ch === '.' && $i + 1 < $len && ctype_digit($expression[$i + 1]))) {
                $numStr = '';
                while ($i < $len && (ctype_digit($expression[$i]) || $expression[$i] === '.')) {
                    $numStr .= $expression[$i];
                    $i++;
                }
                $tokens[] = ['type' => 'number', 'value' => (float) $numStr];
                continue;
            }

            // Operatoren
            if (in_array($ch, ['+', '-', '*', '/'], true)) {
                $tokens[] = ['type' => 'operator', 'value' => $ch];
                $i++;
                continue;
            }

            // Klammern
            if ($ch === '(') {
                $tokens[] = ['type' => 'lparen', 'value' => '('];
                $i++;
                continue;
            }
            if ($ch === ')') {
                $tokens[] = ['type' => 'rparen', 'value' => ')'];
                $i++;
                continue;
            }

            throw new \InvalidArgumentException("Unerwartetes Zeichen '{$ch}' bei Position {$i}");
        }

        return $tokens;
    }

    /**
     * Parser: Expression → Addition/Subtraktion.
     *
     * expression = term (('+' | '-') term)*
     *
     * @param array<int, float|null> $values
     */
    private function parseExpression(array $values): float
    {
        $result = $this->parseTerm($values);

        while ($this->pos < count($this->tokens)
            && $this->tokens[$this->pos]['type'] === 'operator'
            && in_array($this->tokens[$this->pos]['value'], ['+', '-'], true)
        ) {
            $op = $this->tokens[$this->pos]['value'];
            $this->pos++;
            $right = $this->parseTerm($values);

            $result = $op === '+' ? $result + $right : $result - $right;
        }

        return $result;
    }

    /**
     * Parser: Term → Multiplikation/Division.
     *
     * term = factor (('*' | '/') factor)*
     *
     * @param array<int, float|null> $values
     */
    private function parseTerm(array $values): float
    {
        $result = $this->parseFactor($values);

        while ($this->pos < count($this->tokens)
            && $this->tokens[$this->pos]['type'] === 'operator'
            && in_array($this->tokens[$this->pos]['value'], ['*', '/'], true)
        ) {
            $op = $this->tokens[$this->pos]['value'];
            $this->pos++;
            $right = $this->parseFactor($values);

            if ($op === '/') {
                if ($right == 0.0) {
                    throw new \DivisionByZeroError('Division durch 0');
                }
                $result /= $right;
            } else {
                $result *= $right;
            }
        }

        return $result;
    }

    /**
     * Parser: Faktor → Zahl, Platzhalter oder geklammerte Expression.
     *
     * factor = NUMBER | PLACEHOLDER | '(' expression ')'
     *
     * @param array<int, float|null> $values
     */
    private function parseFactor(array $values): float
    {
        if ($this->pos >= count($this->tokens)) {
            throw new \InvalidArgumentException('Unerwartetes Ende der Expression');
        }

        $token = $this->tokens[$this->pos];

        // Unäres Minus
        if ($token['type'] === 'operator' && $token['value'] === '-') {
            $this->pos++;

            return -$this->parseFactor($values);
        }

        // Zahl
        if ($token['type'] === 'number') {
            $this->pos++;

            return (float) $token['value'];
        }

        // Platzhalter
        if ($token['type'] === 'placeholder') {
            $this->pos++;
            $index = (int) $token['value'];

            if (!array_key_exists($index, $values) || $values[$index] === null) {
                throw new \InvalidArgumentException("Fehlender Wert für Platzhalter {{{$index}}}");
            }

            return (float) $values[$index];
        }

        // Geklammerte Expression
        if ($token['type'] === 'lparen') {
            $this->pos++; // '(' verbrauchen
            $result = $this->parseExpression($values);

            if ($this->pos >= count($this->tokens) || $this->tokens[$this->pos]['type'] !== 'rparen') {
                throw new \InvalidArgumentException('Fehlende schließende Klammer');
            }
            $this->pos++; // ')' verbrauchen

            return $result;
        }

        throw new \InvalidArgumentException("Unerwartetes Token: {$token['type']}");
    }
}
