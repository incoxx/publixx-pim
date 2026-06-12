<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Services\Search\ConstraintExtractor;
use PHPUnit\Framework\TestCase;

class ConstraintExtractorTest extends TestCase
{
    /**
     * Test-Schema: entspricht der Struktur aus SearchSchemaService::schema().
     */
    private function schema(): array
    {
        return [
            'attributes' => [
                'diagonale' => [
                    'field' => 'attr_diagonale',
                    'data_type' => 'Number',
                    'aliases' => ['diagonale', 'diagonal'],
                ],
                'display' => [
                    'field' => 'attr_display',
                    'data_type' => 'Number',
                    'aliases' => ['display'],
                ],
                'gewicht' => [
                    'field' => 'attr_gewicht',
                    'data_type' => 'Number',
                    'aliases' => ['gewicht', 'weight'],
                ],
                'breite' => [
                    'field' => 'attr_breite',
                    'data_type' => 'Number',
                    'aliases' => ['breite', 'width'],
                ],
            ],
            'units' => [
                'zoll' => ['attributes' => ['diagonale'], 'to_base' => 1.0],
                '"' => ['attributes' => ['diagonale'], 'to_base' => 1.0],
                'kg' => ['group_id' => 'mass', 'to_base' => 1.0],
                'g' => ['group_id' => 'mass', 'to_base' => 0.001],
                'mm' => ['group_id' => 'length', 'to_base' => 0.001],
                'cm' => ['group_id' => 'length', 'to_base' => 0.01],
                '€' => ['currency' => true, 'to_base' => 1.0],
                'eur' => ['currency' => true, 'to_base' => 1.0],
                'euro' => ['currency' => true, 'to_base' => 1.0],
            ],
            'unit_attributes' => [
                'mass' => ['gewicht'],
                'length' => ['breite', 'diagonale'],
            ],
            'concepts' => [
                'bildschirm' => ['diagonale', 'display'],
                'breit' => ['breite'],
            ],
        ];
    }

    private function extract(string $query, float $tolerance = 0.05): array
    {
        return (new ConstraintExtractor($this->schema(), $tolerance))->extract($query);
    }

    public function test_bare_unit_value_becomes_tolerance_range(): void
    {
        $result = $this->extract('smartes Telefon mit 8 Zoll Bildschirm');

        $this->assertCount(1, $result['filters']);
        $filter = $result['filters'][0];

        $this->assertSame(['attr_diagonale'], $filter['fields']);
        $this->assertSame('range', $filter['op']);
        $this->assertEqualsWithDelta(7.6, $filter['min'], 0.0001);
        $this->assertEqualsWithDelta(8.4, $filter['max'], 0.0001);

        // Constraint-Text ist aus der Restanfrage entfernt, Semantik bleibt
        $this->assertStringNotContainsString('8', $result['residual']);
        $this->assertStringNotContainsString('Zoll', $result['residual']);
        $this->assertStringContainsString('smartes Telefon', $result['residual']);
        $this->assertStringContainsString('Bildschirm', $result['residual']);
    }

    public function test_zero_tolerance_yields_exact_equality(): void
    {
        $result = $this->extract('8 zoll', 0.0);

        $this->assertSame('eq', $result['filters'][0]['op']);
        $this->assertEqualsWithDelta(8.0, $result['filters'][0]['value'], 0.0001);
    }

    public function test_price_comparator_maps_to_list_price(): void
    {
        $result = $this->extract('Tablet unter 300 €');

        $this->assertCount(1, $result['filters']);
        $filter = $result['filters'][0];

        $this->assertSame(['list_price'], $filter['fields']);
        $this->assertSame('le', $filter['op']);
        $this->assertEqualsWithDelta(300.0, $filter['value'], 0.0001);
        $this->assertSame('Tablet', $result['residual']);
    }

    public function test_bare_price_is_interpreted_as_budget_cap(): void
    {
        $result = $this->extract('Telefon 300 EUR');

        $this->assertSame(['list_price'], $result['filters'][0]['fields']);
        $this->assertSame('le', $result['filters'][0]['op']);
    }

    public function test_unit_value_is_normalized_to_base_unit(): void
    {
        // 500 g → 0.5 (Basiseinheit kg)
        $result = $this->extract('über 500 g');

        $filter = $result['filters'][0];
        $this->assertSame(['attr_gewicht'], $filter['fields']);
        $this->assertSame('ge', $filter['op']);
        $this->assertEqualsWithDelta(0.5, $filter['value'], 0.0001);
    }

    public function test_range_between(): void
    {
        $result = $this->extract('Laptop zwischen 1 und 2 kg');

        $filter = $result['filters'][0];
        $this->assertSame('range', $filter['op']);
        $this->assertEqualsWithDelta(1.0, $filter['min'], 0.0001);
        $this->assertEqualsWithDelta(2.0, $filter['max'], 0.0001);
        $this->assertSame('Laptop', $result['residual']);
    }

    public function test_comma_decimal_values(): void
    {
        $result = $this->extract('höchstens 1,5 kg');

        $this->assertSame('le', $result['filters'][0]['op']);
        $this->assertEqualsWithDelta(1.5, $result['filters'][0]['value'], 0.0001);
    }

    public function test_ambiguous_unit_is_narrowed_by_concept_context(): void
    {
        // mm gehört zur Gruppe length mit Kandidaten breite + diagonale;
        // Kontext „breit" disambiguiert auf breite
        $result = $this->extract('mindestens 50 mm breit');

        $this->assertSame(['attr_breite'], $result['filters'][0]['fields']);
        $this->assertEqualsWithDelta(0.05, $result['filters'][0]['value'], 0.0001);
    }

    public function test_ambiguous_unit_without_context_keeps_all_candidates(): void
    {
        $result = $this->extract('unter 50 mm');

        $this->assertSame(['attr_breite', 'attr_diagonale'], $result['filters'][0]['fields']);
    }

    public function test_unknown_unit_stays_in_residual(): void
    {
        $result = $this->extract('Speicher mit 512 GB');

        $this->assertSame([], $result['filters']);
        $this->assertStringContainsString('512 GB', $result['residual']);
    }

    public function test_multiple_constraints_combined(): void
    {
        $result = $this->extract('Telefon mit 8 Zoll Bildschirm unter 300 Euro');

        $this->assertCount(2, $result['filters']);
        $fields = array_merge(...array_column($result['filters'], 'fields'));
        $this->assertContains('attr_diagonale', $fields);
        $this->assertContains('list_price', $fields);
    }

    public function test_query_without_constraints_is_untouched(): void
    {
        $result = $this->extract('robustes Outdoor-Smartphone');

        $this->assertSame([], $result['filters']);
        $this->assertSame('robustes Outdoor-Smartphone', $result['residual']);
    }

    public function test_inch_quote_symbol(): void
    {
        $result = $this->extract('Monitor 27"');

        $this->assertCount(1, $result['filters']);
        $this->assertSame(['attr_diagonale'], $result['filters'][0]['fields']);
    }
}
