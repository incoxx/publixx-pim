<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Ergebnis eines JSON-Imports.
 */
class JsonImportResult
{
    public function __construct(
        public readonly array $stats,
        public readonly array $affectedProductIds,
        public readonly array $skippedDetails,
        public readonly float $durationSeconds,
    ) {}

    public function toArray(): array
    {
        return [
            'stats' => $this->stats,
            'affected_product_ids' => $this->affectedProductIds,
            'skipped_details' => $this->skippedDetails,
            'duration_seconds' => $this->durationSeconds,
        ];
    }

    public function totalCreated(): int
    {
        return array_sum(array_column($this->stats, 'created'));
    }

    public function totalUpdated(): int
    {
        return array_sum(array_column($this->stats, 'updated'));
    }

    public function totalSkipped(): int
    {
        return array_sum(array_column($this->stats, 'skipped'));
    }

    public function totalErrors(): int
    {
        return array_sum(array_column($this->stats, 'errors'));
    }
}
