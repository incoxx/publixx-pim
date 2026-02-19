<?php

declare(strict_types=1);

namespace Tests\Feature\Pql;

use App\Services\Pql\Ast\ComparisonNode;
use App\Services\Pql\Ast\FuzzyNode;
use App\Services\Pql\Ast\LogicalNode;
use App\Services\Pql\Ast\SearchFieldsNode;
use App\Services\Pql\Ast\SoundsLikeNode;
use App\Services\Pql\PqlParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PqlParserTest extends TestCase
{
    private PqlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new PqlParser();
    }

    public function test_select_star(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE status = 'active'");
        $this->assertEquals(['*'], $ast->fields);
        $this->assertTrue($ast->hasWildcardSelect());
    }

    public function test_select_specific_fields(): void
    {
        $ast = $this->parser->parse("SELECT sku, name, status WHERE status = 'active'");
        $this->assertEquals(['sku', 'name', 'status'], $ast->fields);
    }

    public function test_from_clause(): void
    {
        $ast = $this->parser->parse("SELECT * FROM data WHERE status = 'active'");
        $this->assertEquals('data', $ast->source);
    }

    public function test_implicit_select(): void
    {
        $ast = $this->parser->parse("WHERE status = 'active'");
        $this->assertEquals(['*'], $ast->fields);
        $this->assertTrue($ast->hasWhereClause());
    }

    public function test_equals(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE status = 'active'");
        $expr = $ast->where->expression;
        $this->assertInstanceOf(ComparisonNode::class, $expr);
        $this->assertEquals('status', $expr->field);
        $this->assertEquals('=', $expr->operator);
        $this->assertEquals('active', $expr->value);
    }

    public function test_not_equals_bang(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE status != 'draft'");
        $this->assertEquals('!=', $ast->where->expression->operator);
    }

    public function test_not_equals_diamond(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE status <> 'draft'");
        $this->assertEquals('!=', $ast->where->expression->operator);
    }

    public function test_numeric_comparisons(): void
    {
        foreach (['>' => 100, '<' => 50, '>=' => 100, '<=' => 99.99] as $op => $val) {
            $ast = $this->parser->parse("SELECT * WHERE price {$op} {$val}");
            $expr = $ast->where->expression;
            $this->assertEquals($op, $expr->operator);
        }
    }

    public function test_like(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE name LIKE '%Bohrer%'");
        $expr = $ast->where->expression;
        $this->assertEquals('LIKE', $expr->operator);
        $this->assertEquals('%Bohrer%', $expr->value);
    }

    public function test_not_like(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE name NOT LIKE '%test%'");
        $expr = $ast->where->expression;
        $this->assertEquals('NOT LIKE', $expr->operator);
        $this->assertTrue($expr->negated);
    }

    public function test_in(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE status IN ('active', 'draft')");
        $expr = $ast->where->expression;
        $this->assertEquals('IN', $expr->operator);
        $this->assertEquals(['active', 'draft'], $expr->value);
    }

    public function test_not_in(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE status NOT IN ('discontinued')");
        $this->assertEquals('NOT IN', $ast->where->expression->operator);
    }

    public function test_exists(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE productImage EXISTS");
        $expr = $ast->where->expression;
        $this->assertEquals('EXISTS', $expr->operator);
        $this->assertNull($expr->value);
    }

    public function test_not_exists(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE productImage NOT EXISTS");
        $this->assertEquals('NOT EXISTS', $ast->where->expression->operator);
    }

    public function test_between(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE price BETWEEN 50 AND 500");
        $expr = $ast->where->expression;
        $this->assertEquals('BETWEEN', $expr->operator);
        $this->assertEquals([50, 500], $expr->value);
    }

    public function test_not_between(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE price NOT BETWEEN 0 AND 10");
        $this->assertEquals('NOT BETWEEN', $ast->where->expression->operator);
        $this->assertTrue($ast->where->expression->negated);
    }

    public function test_fuzzy_default_threshold(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE name FUZZY 'Bohrmaschine'");
        $expr = $ast->where->expression;
        $this->assertInstanceOf(FuzzyNode::class, $expr);
        $this->assertEquals('Bohrmaschine', $expr->term);
        $this->assertSame(0.7, $expr->threshold);
    }

    public function test_fuzzy_custom_threshold(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE name FUZZY 'Bohrmaschine' 0.8");
        $this->assertSame(0.8, $ast->where->expression->threshold);
    }

    public function test_not_fuzzy(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE name NOT FUZZY 'test'");
        $this->assertTrue($ast->where->expression->negated);
    }

    public function test_sounds_like(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE name SOUNDS_LIKE 'Maier'");
        $expr = $ast->where->expression;
        $this->assertInstanceOf(SoundsLikeNode::class, $expr);
        $this->assertEquals('Maier', $expr->term);
    }

    public function test_not_sounds_like(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE name NOT SOUNDS_LIKE 'Schmidt'");
        $this->assertTrue($ast->where->expression->negated);
    }

    public function test_search_fields_fuzzy(): void
    {
        $pql = "SELECT * WHERE SEARCH_FIELDS(productName^3, description) FUZZY 'Bohrmaschine' 0.7";
        $ast = $this->parser->parse($pql);
        $expr = $ast->where->expression;

        $this->assertInstanceOf(SearchFieldsNode::class, $expr);
        $this->assertEquals(['productName' => 3.0, 'description' => 1.0], $expr->fields);
        $this->assertInstanceOf(FuzzyNode::class, $expr->condition);
        $this->assertEquals('Bohrmaschine', $expr->condition->term);
    }

    public function test_search_fields_like(): void
    {
        $pql = "SELECT * WHERE SEARCH_FIELDS(productName^2, description^1) LIKE '%Hammer%'";
        $ast = $this->parser->parse($pql);
        $expr = $ast->where->expression;
        $this->assertInstanceOf(SearchFieldsNode::class, $expr);
        $this->assertInstanceOf(ComparisonNode::class, $expr->condition);
    }

    public function test_logical_and(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE status = 'active' AND price > 100");
        $expr = $ast->where->expression;
        $this->assertInstanceOf(LogicalNode::class, $expr);
        $this->assertEquals('AND', $expr->operator);
        $this->assertInstanceOf(ComparisonNode::class, $expr->left);
        $this->assertInstanceOf(ComparisonNode::class, $expr->right);
    }

    public function test_logical_or(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE status = 'active' OR status = 'draft'");
        $expr = $ast->where->expression;
        $this->assertInstanceOf(LogicalNode::class, $expr);
        $this->assertEquals('OR', $expr->operator);
    }

    public function test_and_binds_tighter_than_or(): void
    {
        // a OR b AND c → a OR (b AND c) because AND has higher precedence
        $ast = $this->parser->parse("SELECT * WHERE status = 'x' OR price > 100 AND sku = 'y'");
        $expr = $ast->where->expression;

        $this->assertInstanceOf(LogicalNode::class, $expr);
        $this->assertEquals('OR', $expr->operator);
        $this->assertInstanceOf(ComparisonNode::class, $expr->left);
        $this->assertInstanceOf(LogicalNode::class, $expr->right);
        $this->assertEquals('AND', $expr->right->operator);
    }

    public function test_complex_query(): void
    {
        $pql = "SELECT * WHERE SEARCH_FIELDS(productName^3, description) FUZZY 'Bohrmaschine' 0.7 AND price BETWEEN 50 AND 500 AND status = 'active' ORDER BY SCORE DESC";
        $ast = $this->parser->parse($pql);

        $this->assertTrue($ast->hasWhereClause());
        $this->assertTrue($ast->hasOrderByScore());
        $this->assertEquals('DESC', $ast->orderByScore->direction);

        // Root should be AND chain
        $root = $ast->where->expression;
        $this->assertInstanceOf(LogicalNode::class, $root);
    }

    public function test_order_by_score_asc(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE name = 'test' ORDER BY SCORE ASC");
        $this->assertTrue($ast->hasOrderByScore());
        $this->assertEquals('ASC', $ast->orderByScore->direction);
    }

    public function test_order_by_score_desc(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE name = 'test' ORDER BY SCORE DESC");
        $this->assertEquals('DESC', $ast->orderByScore->direction);
    }

    public function test_dot_notation_field(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE specs.weight.value > 10");
        $expr = $ast->where->expression;
        $this->assertEquals('specs.weight.value', $expr->field);
    }

    public function test_double_quoted_strings(): void
    {
        $ast = $this->parser->parse('SELECT * WHERE name = "test value"');
        $this->assertEquals('test value', $ast->where->expression->value);
    }

    public function test_boolean_value_true(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE isActive = true");
        $this->assertSame(true, $ast->where->expression->value);
    }

    public function test_boolean_value_false(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE isActive = false");
        $this->assertSame(false, $ast->where->expression->value);
    }

    public function test_negative_number(): void
    {
        $ast = $this->parser->parse("SELECT * WHERE temperature > -10");
        $this->assertSame(-10, $ast->where->expression->value);
    }

    public function test_unterminated_string_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unterminated string');
        $this->parser->parse("SELECT * WHERE name = 'unterminated");
    }

    public function test_unexpected_token_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse("SELECT * WHERE = 'value'");
    }

    public function test_to_array_roundtrip(): void
    {
        $ast = $this->parser->parse("SELECT sku, name WHERE status = 'active' AND price > 50 ORDER BY SCORE DESC");
        $array = $ast->toArray();

        $this->assertEquals('SELECT', $array['type']);
        $this->assertEquals(['sku', 'name'], $array['fields']);
        $this->assertNotNull($array['where']);
        $this->assertNotNull($array['orderByScore']);
    }
}
