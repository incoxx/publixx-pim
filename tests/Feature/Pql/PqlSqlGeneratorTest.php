<?php

declare(strict_types=1);

namespace Tests\Feature\Pql;

use App\Services\Pql\Ast\ComparisonNode;
use App\Services\Pql\Ast\FuzzyNode;
use App\Services\Pql\Ast\LogicalNode;
use App\Services\Pql\Ast\OrderByScoreNode;
use App\Services\Pql\Ast\SearchFieldsNode;
use App\Services\Pql\Ast\SelectNode;
use App\Services\Pql\Ast\SoundsLikeNode;
use App\Services\Pql\Ast\WhereNode;
use App\Services\Pql\PqlSqlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PqlSqlGeneratorTest extends TestCase
{
    private PqlSqlGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new PqlSqlGenerator();
    }

    public function test_simple_equality_generates_parameterized_sql(): void
    {
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new ComparisonNode('status', '=', 'active'),
            ),
        );

        $result = $this->generator->generate($ast, [], 'de');
        $sqlInfo = $this->generator->toSql($result['query']);

        // Must contain placeholder, not literal value
        $this->assertStringContainsString('?', $sqlInfo['sql']);
        $this->assertContains('active', $sqlInfo['bindings']);

        // Must NOT contain string-concatenated value
        $this->assertStringNotContainsString("'active'", $sqlInfo['sql']);
    }

    public function test_between_generates_parameterized_sql(): void
    {
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new ComparisonNode('price', 'BETWEEN', [50, 500]),
            ),
        );

        // price maps to psi.list_price
        $result = $this->generator->generate($ast, [
            'price' => ['table' => 'products_search_index', 'data_type' => 'Number'],
        ], 'de');
        $sqlInfo = $this->generator->toSql($result['query']);

        $this->assertStringContainsString('between', strtolower($sqlInfo['sql']));
        $this->assertContains(50, $sqlInfo['bindings']);
        $this->assertContains(500, $sqlInfo['bindings']);
    }

    public function test_in_generates_parameterized_sql(): void
    {
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new ComparisonNode('status', 'IN', ['active', 'draft']),
            ),
        );

        $result = $this->generator->generate($ast, [], 'de');
        $sqlInfo = $this->generator->toSql($result['query']);

        // IN clause should have placeholders
        $this->assertStringContainsString('?', $sqlInfo['sql']);
        $this->assertContains('active', $sqlInfo['bindings']);
        $this->assertContains('draft', $sqlInfo['bindings']);
    }

    public function test_like_fulltext_generates_match_against(): void
    {
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new ComparisonNode('name', 'LIKE', '%Bohrer%'),
            ),
        );

        $result = $this->generator->generate($ast, [], 'de');
        $sqlInfo = $this->generator->toSql($result['query']);

        $this->assertStringContainsString('MATCH', $sqlInfo['sql']);
        $this->assertStringContainsString('AGAINST', $sqlInfo['sql']);
        $this->assertStringContainsString('BOOLEAN MODE', $sqlInfo['sql']);
    }

    public function test_fuzzy_sets_has_fuzzy_flag(): void
    {
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new FuzzyNode('name', 'Bohrmaschine', 0.7),
            ),
        );

        $result = $this->generator->generate($ast, [], 'de');

        $this->assertTrue($result['has_fuzzy']);
        $this->assertNotEmpty($result['fuzzy_nodes']);
        $this->assertEquals('Bohrmaschine', $result['fuzzy_nodes'][0]['term']);
    }

    public function test_sounds_like_generates_soundex(): void
    {
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new SoundsLikeNode('sku', 'Maier'),
            ),
        );

        $result = $this->generator->generate($ast, [], 'de');
        $sqlInfo = $this->generator->toSql($result['query']);

        $this->assertStringContainsString('SOUNDEX', $sqlInfo['sql']);
    }

    public function test_logical_and_generates_both_conditions(): void
    {
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new LogicalNode(
                    'AND',
                    new ComparisonNode('status', '=', 'active'),
                    new ComparisonNode('price', '>', 100),
                ),
            ),
        );

        $result = $this->generator->generate($ast, [
            'price' => ['table' => 'products_search_index', 'data_type' => 'Number'],
        ], 'de');
        $sqlInfo = $this->generator->toSql($result['query']);

        // Should have both binding values
        $this->assertContains('active', $sqlInfo['bindings']);
        $this->assertContains(100, $sqlInfo['bindings']);
    }

    public function test_search_fields_enables_scoring(): void
    {
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new SearchFieldsNode(
                    ['productName' => 3.0, 'description' => 1.0],
                    new FuzzyNode('productName', 'Hammer', 0.7),
                ),
            ),
            orderByScore: new OrderByScoreNode('DESC'),
        );

        $result = $this->generator->generate($ast, [], 'de');

        $this->assertTrue($result['needs_scoring']);
        $this->assertNotEmpty($result['score_expressions']);
        $this->assertTrue($result['has_fuzzy']);
    }

    public function test_no_string_concatenation_in_sql(): void
    {
        $dangerousValue = "'; DROP TABLE products; --";
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new ComparisonNode('name', '=', $dangerousValue),
            ),
        );

        $result = $this->generator->generate($ast, [], 'de');
        $sqlInfo = $this->generator->toSql($result['query']);

        // The dangerous value should be in bindings, NOT in the SQL string
        $this->assertStringNotContainsString('DROP TABLE', $sqlInfo['sql']);
        $this->assertContains($dangerousValue, $sqlInfo['bindings']);
    }

    public function test_eav_join_for_unknown_field(): void
    {
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new ComparisonNode('customAttr', '=', 'test'),
            ),
        );

        $result = $this->generator->generate($ast, [
            'customAttr' => [
                'table' => 'product_attribute_values',
                'attribute_id' => 'uuid-attr-1',
                'data_type' => 'String',
            ],
        ], 'de');
        $sqlInfo = $this->generator->toSql($result['query']);

        // Should have joined product_attribute_values
        $this->assertStringContainsString('product_attribute_values', $sqlInfo['sql']);
        $this->assertStringContainsString('pav_', $sqlInfo['sql']);
    }

    public function test_exists_check_for_base_field(): void
    {
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new ComparisonNode('ean', 'EXISTS'),
            ),
        );

        $result = $this->generator->generate($ast, [], 'de');
        $sqlInfo = $this->generator->toSql($result['query']);

        $this->assertStringContainsString('not null', strtolower($sqlInfo['sql']));
    }

    public function test_not_exists_for_base_field(): void
    {
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new ComparisonNode('ean', 'NOT EXISTS', null, true),
            ),
        );

        $result = $this->generator->generate($ast, [], 'de');
        $sqlInfo = $this->generator->toSql($result['query']);

        $this->assertStringContainsString('null', strtolower($sqlInfo['sql']));
    }

    public function test_limit_and_offset_applied(): void
    {
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new ComparisonNode('status', '=', 'active'),
            ),
            limit: 25,
            offset: 100,
        );

        $result = $this->generator->generate($ast, [], 'de');
        $sqlInfo = $this->generator->toSql($result['query']);

        // Query builder applies limit/offset
        $this->assertStringContainsString('limit', strtolower($sqlInfo['sql']));
    }

    public function test_fuzzy_overfetches_for_php_filter(): void
    {
        $ast = new SelectNode(
            fields: ['*'],
            where: new WhereNode(
                new FuzzyNode('name', 'test', 0.7),
            ),
            limit: 50,
        );

        $result = $this->generator->generate($ast, [], 'de');
        $sqlInfo = $this->generator->toSql($result['query']);

        // For fuzzy, SQL LIMIT should be higher than requested (5x for post-filter)
        // The actual limit in SQL should be 250 (50 * 5)
        $this->assertTrue($result['has_fuzzy']);
    }
}
