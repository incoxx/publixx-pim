<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Ergebnis eines Fuzzy-Matchings.
 */
readonly class FuzzyMatch
{
    public function __construct(
        /** Der gefundene Kandidat (Originalschreibweise). */
        public string $match,
        /** Ähnlichkeit 0.0–1.0. */
        public float $similarity,
        /** True wenn exakter Match nach Normalisierung. */
        public bool $exact = false,
    ) {}

    public function toSuggestion(): string
    {
        return $this->match;
    }
}
