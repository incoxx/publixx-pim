<?php

declare(strict_types=1);

namespace App\Services\Pql\Ast;

/**
 * Wrapper node for the WHERE clause, holding the root condition expression.
 */
final class WhereNode
{
    public function __construct(
        public readonly ComparisonNode|LogicalNode|FuzzyNode|SoundsLikeNode|SearchFieldsNode $expression,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'WHERE',
            'expression' => $this->expression->toArray(),
        ];
    }
}
