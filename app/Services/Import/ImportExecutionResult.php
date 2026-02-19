<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Ergebnis der Import-Ausführung.
 */
readonly class ImportExecutionResult
{
    public function __construct(
        /** @var array<string, array{created:int,updated:int,skipped:int,errors:int}> */
        public array $stats,
        /** @var string[] Betroffene Produkt-IDs. */
        public array $affectedProductIds,
    ) {}

    public function toArray(): array
    {
        return [
            'stats' => $this->stats,
            'affected_product_count' => count($this->affectedProductIds),
        ];
    }
}
