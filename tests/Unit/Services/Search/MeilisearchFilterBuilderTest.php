<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Services\Search\MeilisearchFilterBuilder;
use PHPUnit\Framework\TestCase;

class MeilisearchFilterBuilderTest extends TestCase
{
    public function test_empty_filters_yield_empty_string(): void
    {
        $this->assertSame('', MeilisearchFilterBuilder::build([]));
    }

    public function test_range_filter(): void
    {
        $filter = MeilisearchFilterBuilder::build([
            ['fields' => ['attr_diagonale'], 'op' => 'range', 'min' => 7.6, 'max' => 8.4],
        ]);

        $this->assertSame('(attr_diagonale >= 7.6 AND attr_diagonale <= 8.4)', $filter);
    }

    public function test_multiple_candidate_fields_are_or_combined(): void
    {
        $filter = MeilisearchFilterBuilder::build([
            ['fields' => ['attr_breite', 'attr_diagonale'], 'op' => 'le', 'value' => 0.05],
        ]);

        $this->assertSame('(attr_breite <= 0.05 OR attr_diagonale <= 0.05)', $filter);
    }

    public function test_filters_are_and_combined(): void
    {
        $filter = MeilisearchFilterBuilder::build([
            ['fields' => ['list_price'], 'op' => 'le', 'value' => 300.0],
            ['fields' => ['status'], 'op' => 'eq', 'value' => 'active'],
        ]);

        $this->assertSame('list_price <= 300 AND status = "active"', $filter);
    }

    public function test_string_values_are_escaped(): void
    {
        $filter = MeilisearchFilterBuilder::build([
            ['fields' => ['attr_farbe'], 'op' => 'eq', 'value' => 'Rot "Metallic"'],
        ]);

        $this->assertSame('attr_farbe = "Rot \\"Metallic\\""', $filter);
    }

    public function test_boolean_values(): void
    {
        $filter = MeilisearchFilterBuilder::build([
            ['fields' => ['attr_wifi'], 'op' => 'eq', 'value' => true],
        ]);

        $this->assertSame('attr_wifi = true', $filter);
    }
}
