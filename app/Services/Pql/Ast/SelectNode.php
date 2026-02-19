<?php

declare(strict_types=1);

namespace App\Services\Pql\Ast;

/**
 * Root AST node representing a complete PQL query.
 *
 * SELECT [fields] FROM [source] WHERE [conditions] [ORDER BY SCORE ASC|DESC]
 */
final class SelectNode
{
    /**
     * @param array<string> $fields Field names or ['*'] for all
     * @param string $source Data source (default: 'data')
     * @param WhereNode|null $where WHERE clause root node
     * @param OrderByScoreNode|null $orderByScore ORDER BY SCORE clause
     * @param int $limit Result limit
     * @param int $offset Pagination offset
     */
    public function __construct(
        public readonly array $fields = ['*'],
        public readonly string $source = 'data',
        public readonly ?WhereNode $where = null,
        public readonly ?OrderByScoreNode $orderByScore = null,
        public readonly int $limit = 50,
        public readonly int $offset = 0,
    ) {}

    public function hasWhereClause(): bool
    {
        return $this->where !== null;
    }

    public function hasOrderByScore(): bool
    {
        return $this->orderByScore !== null;
    }

    public function hasWildcardSelect(): bool
    {
        return $this->fields === ['*'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'SELECT',
            'fields' => $this->fields,
            'source' => $this->source,
            'where' => $this->where?->toArray(),
            'orderByScore' => $this->orderByScore?->toArray(),
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];
    }
}
