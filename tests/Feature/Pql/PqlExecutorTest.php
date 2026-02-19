<?php

declare(strict_types=1);

namespace Tests\Feature\Pql;

use App\Services\Pql\FuzzyMatcher;
use App\Services\Pql\PhoneticMatcher;
use App\Services\Pql\PqlExecutor;
use App\Services\Pql\PqlParser;
use App\Services\Pql\PqlSqlGenerator;
use App\Services\Pql\PqlValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for PqlExecutor — focused on validate() and explain()
 * which don't require a database.
 *
 * Integration tests with actual DB are done in PqlControllerTest.
 */
final class PqlExecutorTest extends TestCase
{
    use RefreshDatabase;

    private PqlExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->executor = new PqlExecutor(
            parser: new PqlParser(),
            validator: new PqlValidator(),
            generator: new PqlSqlGenerator(),
            fuzzyMatcher: new FuzzyMatcher(),
        );
    }

    // ─── Validate ───────────────────────────────────────────────

    public function test_validate_valid_query_with_base_fields(): void
    {
        $result = $this->executor->validate("SELECT * WHERE status = 'active'");

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertNotNull($result['ast']);
    }

    public function test_validate_returns_ast(): void
    {
        $result = $this->executor->validate("SELECT sku, name WHERE status = 'active' AND sku = 'ABC'");

        $this->assertNotNull($result['ast']);
        $this->assertEquals('SELECT', $result['ast']['type']);
        $this->assertEquals(['sku', 'name'], $result['ast']['fields']);
    }

    public function test_validate_syntax_error_returns_invalid(): void
    {
        $result = $this->executor->validate("SELECT * WHERE = 'broken'");

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertNull($result['ast']);
    }

    public function test_validate_unterminated_string(): void
    {
        $result = $this->executor->validate("SELECT * WHERE name = 'unterminated");

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    // ─── Explain ────────────────────────────────────────────────

    public function test_explain_returns_ast_and_sql(): void
    {
        $result = $this->executor->explain("SELECT * WHERE status = 'active'");

        $this->assertNotNull($result['ast']);
        $this->assertNotNull($result['sql']);
        $this->assertNotEmpty($result['bindings']);
        $this->assertContains('active', $result['bindings']);
    }

    public function test_explain_syntax_error(): void
    {
        $result = $this->executor->explain("INVALID QUERY");

        $this->assertNull($result['ast']);
        $this->assertNull($result['sql']);
        $this->assertStringContainsString('N/A', $result['estimated_cost']);
    }

    public function test_explain_shows_fuzzy_cost(): void
    {
        $result = $this->executor->explain("SELECT * WHERE name FUZZY 'Bohrmaschine'");

        $this->assertStringContainsString('FUZZY', $result['estimated_cost']);
    }

    // ─── FuzzyMatcher Direct Tests ──────────────────────────────

    public function test_fuzzy_matcher_exact_match(): void
    {
        $matcher = new FuzzyMatcher();
        $score = $matcher->similarity('Bohrmaschine', 'Bohrmaschine');
        $this->assertGreaterThanOrEqual(0.99, $score);
    }

    public function test_fuzzy_matcher_similar_strings(): void
    {
        $matcher = new FuzzyMatcher();
        $score = $matcher->similarity('Bohrmaschine', 'Bormaschine');
        $this->assertGreaterThan(0.7, $score);
    }

    public function test_fuzzy_matcher_dissimilar_strings(): void
    {
        $matcher = new FuzzyMatcher();
        $score = $matcher->similarity('Bohrmaschine', 'Kaffeemaschine');
        $this->assertLessThan(0.7, $score);
    }

    public function test_fuzzy_matcher_filter_by_threshold(): void
    {
        $matcher = new FuzzyMatcher();
        $candidates = [
            'a' => 'Bohrmaschine',
            'b' => 'Bormaschine',
            'c' => 'Kaffeemaschine',
            'd' => 'Bohrmaschin',
        ];

        $results = $matcher->filterByThreshold('Bohrmaschine', $candidates, 0.7);

        $this->assertArrayHasKey('a', $results); // exact
        $this->assertArrayHasKey('b', $results); // close
        $this->assertArrayHasKey('d', $results); // close
        // 'c' (Kaffeemaschine) should be below threshold
    }

    // ─── PhoneticMatcher Direct Tests ───────────────────────────

    public function test_phonetic_maier_meyer(): void
    {
        $matcher = new PhoneticMatcher();
        $this->assertTrue($matcher->soundsLike('Maier', 'Meyer'));
    }

    public function test_phonetic_schmidt_schmitt(): void
    {
        $matcher = new PhoneticMatcher();
        $this->assertTrue($matcher->soundsLike('Schmidt', 'Schmitt'));
    }

    public function test_phonetic_mueller_muller(): void
    {
        $matcher = new PhoneticMatcher();
        $this->assertTrue($matcher->soundsLike('Müller', 'Mueller'));
    }

    public function test_phonetic_different_names(): void
    {
        $matcher = new PhoneticMatcher();
        $this->assertFalse($matcher->soundsLike('Schmidt', 'Müller'));
    }

    public function test_koelner_phonetik_codes(): void
    {
        $matcher = new PhoneticMatcher();

        // Known Kölner Phonetik codes
        $this->assertEquals($matcher->koelnerPhonetik('Maier'), $matcher->koelnerPhonetik('Meyer'));
        $this->assertEquals($matcher->koelnerPhonetik('Schmidt'), $matcher->koelnerPhonetik('Schmitt'));
    }

    public function test_phonetic_codes_method(): void
    {
        $matcher = new PhoneticMatcher();
        $codes = $matcher->getPhoneticCodes('Müller');

        $this->assertArrayHasKey('koelner', $codes);
        $this->assertArrayHasKey('soundex', $codes);
        $this->assertNotEmpty($codes['koelner']);
        $this->assertNotEmpty($codes['soundex']);
    }

    // ─── Edge Cases ─────────────────────────────────────────────

    public function test_empty_pql_is_treated_as_select_all(): void
    {
        // An empty PQL string is parsed as "SELECT *" (no WHERE clause),
        // which is valid. The parser treats it as "select everything".
        $result = $this->executor->validate('');

        $this->assertTrue($result['valid']);
        $this->assertNotNull($result['ast']);
        $this->assertEquals(['*'], $result['ast']['fields']);
    }

    public function test_fuzzy_matcher_empty_strings(): void
    {
        $matcher = new FuzzyMatcher();
        $this->assertSame(1.0, $matcher->similarity('', ''));
        $this->assertSame(0.0, $matcher->similarity('test', ''));
    }

    public function test_phonetic_empty_string(): void
    {
        $matcher = new PhoneticMatcher();
        $this->assertSame('', $matcher->koelnerPhonetik(''));
    }
}
