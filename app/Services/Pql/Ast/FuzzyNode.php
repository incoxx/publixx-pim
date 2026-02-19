<?php

declare(strict_types=1);

namespace App\Services\Pql\Ast;

/**
 * Fuzzy matching: field FUZZY 'text' [threshold]
 *
 * Strategy: FULLTEXT pre-filter → PHP Levenshtein (60%) + Trigram (40%) → threshold filter
 */
final class FuzzyNode
{
    /**
     * @param string $field Field to fuzzy-match against
     * @param string $term Search term
     * @param float $threshold Similarity threshold 0.0–1.0 (default: 0.7)
     * @param bool $negated NOT FUZZY
     */
    public function __construct(
        public readonly string $field,
        public readonly string $term,
        public readonly float $threshold = 0.7,
        public readonly bool $negated = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'FUZZY',
            'field' => $this->field,
            'term' => $this->term,
            'threshold' => $this->threshold,
            'negated' => $this->negated,
        ];
    }
}
