<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CompositeExpressionEvaluator;
use PHPUnit\Framework\TestCase;

class CompositeExpressionEvaluatorTest extends TestCase
{
    private CompositeExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new CompositeExpressionEvaluator();
    }

    // ─── Grundrechenarten ────────────────────────────────────────

    public function test_addition(): void
    {
        $result = $this->evaluator->evaluate('{0} + {1}', [1.0, 2.0]);
        $this->assertSame(3.0, $result);
    }

    public function test_subtraction(): void
    {
        $result = $this->evaluator->evaluate('{0} - {1}', [10.0, 3.0]);
        $this->assertSame(7.0, $result);
    }

    public function test_multiplication(): void
    {
        $result = $this->evaluator->evaluate('{0} * {1}', [4.0, 5.0]);
        $this->assertSame(20.0, $result);
    }

    public function test_division(): void
    {
        $result = $this->evaluator->evaluate('{0} / {1}', [10.0, 4.0]);
        $this->assertSame(2.5, $result);
    }

    // ─── Volumenberechnung ───────────────────────────────────────

    public function test_volume_calculation(): void
    {
        // B × H × T = Volumen
        $result = $this->evaluator->evaluate('{0} * {1} * {2}', [120.0, 80.0, 45.0]);
        $this->assertSame(432000.0, $result);
    }

    // ─── Umrechnungen mit Konstanten ─────────────────────────────

    public function test_metric_to_inch_conversion(): void
    {
        // mm → Inch: value * 0.0393701
        $result = $this->evaluator->evaluate('{0} * 0.0393701', [25.4]);
        $this->assertEqualsWithDelta(1.0, $result, 0.0001);
    }

    public function test_constant_multiplication(): void
    {
        $result = $this->evaluator->evaluate('{0} * 2.54', [10.0]);
        $this->assertEqualsWithDelta(25.4, $result, 0.0001);
    }

    // ─── Klammern ────────────────────────────────────────────────

    public function test_parentheses(): void
    {
        $result = $this->evaluator->evaluate('({0} + {1}) * {2}', [2.0, 3.0, 4.0]);
        $this->assertSame(20.0, $result);
    }

    public function test_nested_parentheses(): void
    {
        $result = $this->evaluator->evaluate('(({0} + {1}) * {2}) / 2', [2.0, 3.0, 4.0]);
        $this->assertSame(10.0, $result);
    }

    // ─── Operator-Vorrang ────────────────────────────────────────

    public function test_operator_precedence(): void
    {
        // 2 + 3 * 4 = 14 (nicht 20)
        $result = $this->evaluator->evaluate('{0} + {1} * {2}', [2.0, 3.0, 4.0]);
        $this->assertSame(14.0, $result);
    }

    // ─── Unäres Minus ────────────────────────────────────────────

    public function test_unary_minus(): void
    {
        $result = $this->evaluator->evaluate('-{0} + {1}', [3.0, 5.0]);
        $this->assertSame(2.0, $result);
    }

    // ─── Fehlerfälle ─────────────────────────────────────────────

    public function test_division_by_zero_returns_null(): void
    {
        $result = $this->evaluator->evaluate('{0} / {1}', [10.0, 0.0]);
        $this->assertNull($result);
    }

    public function test_missing_value_returns_null(): void
    {
        $result = $this->evaluator->evaluate('{0} + {1}', [1.0]);
        $this->assertNull($result);
    }

    public function test_null_value_returns_null(): void
    {
        $result = $this->evaluator->evaluate('{0} + {1}', [1.0, null]);
        $this->assertNull($result);
    }

    public function test_invalid_expression_returns_null(): void
    {
        $result = $this->evaluator->evaluate('{0} +', [1.0]);
        $this->assertNull($result);
    }

    public function test_unclosed_placeholder_returns_null(): void
    {
        $result = $this->evaluator->evaluate('{0 + {1}', [1.0, 2.0]);
        $this->assertNull($result);
    }

    public function test_invalid_characters_returns_null(): void
    {
        $result = $this->evaluator->evaluate('{0} + abc', [1.0]);
        $this->assertNull($result);
    }

    public function test_empty_expression_returns_null(): void
    {
        $result = $this->evaluator->evaluate('', [1.0]);
        $this->assertNull($result);
    }

    // ─── Validierung ─────────────────────────────────────────────

    public function test_validate_valid_expression(): void
    {
        $this->assertTrue($this->evaluator->validate('{0} * {1} * {2}'));
    }

    public function test_validate_expression_with_constant(): void
    {
        $this->assertTrue($this->evaluator->validate('{0} * 0.0393701'));
    }

    public function test_validate_expression_with_parentheses(): void
    {
        $this->assertTrue($this->evaluator->validate('({0} + {1}) * {2}'));
    }

    public function test_validate_invalid_expression(): void
    {
        $this->assertFalse($this->evaluator->validate('{0} +'));
    }

    public function test_validate_empty_expression(): void
    {
        $this->assertFalse($this->evaluator->validate(''));
    }

    public function test_validate_injection_attempt(): void
    {
        $this->assertFalse($this->evaluator->validate('system("ls")'));
    }

    // ─── Nur Konstante ───────────────────────────────────────────

    public function test_constant_only(): void
    {
        $result = $this->evaluator->evaluate('42', []);
        $this->assertSame(42.0, $result);
    }

    // ─── Nur ein Platzhalter ─────────────────────────────────────

    public function test_single_placeholder(): void
    {
        $result = $this->evaluator->evaluate('{0}', [7.5]);
        $this->assertSame(7.5, $result);
    }
}
