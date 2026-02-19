<?php

declare(strict_types=1);

namespace App\Services\Pql\Ast;

/**
 * ORDER BY SCORE ASC|DESC
 *
 * Orders results by computed _pqlScore (from SEARCH_FIELDS weighted scoring).
 */
final class OrderByScoreNode
{
    public function __construct(
        public readonly string $direction = 'DESC',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'ORDER_BY_SCORE',
            'direction' => $this->direction,
        ];
    }
}
