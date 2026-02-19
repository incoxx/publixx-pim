<?php

declare(strict_types=1);

namespace App\Services\Pql\Ast;

/**
 * Multi-field weighted search: SEARCH_FIELDS(field1^3, field2^2, field3) OPERATOR 'text'
 *
 * The operator can be FUZZY, LIKE, SOUNDS_LIKE, or = applied across multiple fields
 * with boost factors as score multipliers.
 */
final class SearchFieldsNode
{
    /**
     * @param array<string, float> $fields Map of field => boost factor (e.g. ['productName' => 3.0, 'description' => 1.0])
     * @param FuzzyNode|ComparisonNode|SoundsLikeNode $condition The actual search condition to apply per field
     */
    public function __construct(
        public readonly array $fields,
        public readonly FuzzyNode|ComparisonNode|SoundsLikeNode $condition,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'SEARCH_FIELDS',
            'fields' => $this->fields,
            'condition' => $this->condition->toArray(),
        ];
    }
}
