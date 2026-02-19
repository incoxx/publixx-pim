<?php

declare(strict_types=1);

namespace App\Services\Pql\Ast;

/**
 * Logical combination of two expressions: left AND|OR right
 */
final class LogicalNode
{
    /**
     * @param string $operator 'AND' or 'OR'
     * @param ComparisonNode|LogicalNode|FuzzyNode|SoundsLikeNode|SearchFieldsNode $left
     * @param ComparisonNode|LogicalNode|FuzzyNode|SoundsLikeNode|SearchFieldsNode $right
     */
    public function __construct(
        public readonly string $operator,
        public readonly ComparisonNode|LogicalNode|FuzzyNode|SoundsLikeNode|SearchFieldsNode $left,
        public readonly ComparisonNode|LogicalNode|FuzzyNode|SoundsLikeNode|SearchFieldsNode $right,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'LOGICAL',
            'operator' => $this->operator,
            'left' => $this->left->toArray(),
            'right' => $this->right->toArray(),
        ];
    }
}
