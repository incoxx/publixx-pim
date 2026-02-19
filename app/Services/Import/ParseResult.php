<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Ergebnis des Sheet-Parsings.
 */
readonly class ParseResult
{
    public function __construct(
        /** @var string[] Liste der gefundenen Sheet-Keys (z.B. ["05_Attribute", "08_Produkte"]) */
        public array $sheetsFound,
        /** @var array<string, array<int, array<string, mixed>>> Sheet-Key → Zeilen */
        public array $data,
    ) {}

    public function hasSheet(string $key): bool
    {
        return in_array($key, $this->sheetsFound, true);
    }

    public function getSheetData(string $key): array
    {
        return $this->data[$key] ?? [];
    }

    public function totalRows(): int
    {
        $total = 0;
        foreach ($this->data as $rows) {
            $total += count($rows);
        }
        return $total;
    }
}
