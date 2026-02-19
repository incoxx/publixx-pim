<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Ergebnis einer Referenz-Auflösung.
 */
readonly class ResolveResult
{
    public function __construct(
        /** UUID der aufgelösten Entität, oder null. */
        public ?string $id,
        /** True = exakter Match gefunden. */
        public bool $found,
        /** Fuzzy-Vorschlag bei Nicht-Match (z.B. "Gewicht"). */
        public ?string $suggestion,
    ) {}

    public function resolved(): bool
    {
        return $this->id !== null;
    }
}
