<?php

declare(strict_types=1);

namespace App\Services\Pql\Ast;

/**
 * Binary comparison: field OPERATOR value
 *
 * Supports: =, !=, <>, >, <, >=, <=, LIKE, NOT LIKE, IN, NOT IN,
 *           EXISTS, NOT EXISTS, BETWEEN, NOT BETWEEN
 */
final class ComparisonNode
{
    /**
     * @param string $field Field name (e.g. 'status', 'price', 'specs.weight.value')
     * @param string $operator Comparison operator
     * @param mixed $value Scalar, array (IN), or [min, max] (BETWEEN)
     * @param bool $negated Whether the operator is negated (NOT LIKE, NOT IN, etc.)
     */
    public function __construct(
        public readonly string $field,
        public readonly string $operator,
        public readonly mixed $value = null,
        public readonly bool $negated = false,
    ) {}

    public function isExistenceCheck(): bool
    {
        return in_array($this->operator, ['EXISTS', 'NOT EXISTS'], true);
    }

    public function isBetween(): bool
    {
        return in_array($this->operator, ['BETWEEN', 'NOT BETWEEN'], true);
    }

    public function isIn(): bool
    {
        return in_array($this->operator, ['IN', 'NOT IN'], true);
    }

    public function isLike(): bool
    {
        return in_array($this->operator, ['LIKE', 'NOT LIKE'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'COMPARISON',
            'field' => $this->field,
            'operator' => $this->operator,
            'value' => $this->value,
            'negated' => $this->negated,
        ];
    }
}
