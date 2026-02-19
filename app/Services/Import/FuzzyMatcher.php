<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Fuzzy-Matching für Attributnamen, technische Namen etc.
 * Levenshtein-Distanz mit 85% Ähnlichkeits-Threshold.
 */
class FuzzyMatcher
{
    public const float DEFAULT_THRESHOLD = 0.85;

    public function __construct(
        private readonly float $threshold = self::DEFAULT_THRESHOLD,
    ) {}

    /**
     * Finde den besten Match für einen Input-String aus einer Liste von Kandidaten.
     *
     * @param string $input    Eingabewert (z.B. "Gwicht")
     * @param array  $candidates  Liste möglicher Werte (z.B. ["Gewicht", "Farbe", ...])
     * @return FuzzyMatch|null  Bester Treffer oder null
     */
    public function findMatch(string $input, array $candidates): ?FuzzyMatch
    {
        $normalizedInput = $this->normalize($input);

        if ($normalizedInput === '') {
            return null;
        }

        $bestMatch = null;
        $bestSimilarity = 0.0;

        foreach ($candidates as $candidate) {
            $normalizedCandidate = $this->normalize((string) $candidate);

            if ($normalizedCandidate === '') {
                continue;
            }

            // Exakter Match (nach Normalisierung) → sofort zurück
            if ($normalizedInput === $normalizedCandidate) {
                return new FuzzyMatch($candidate, 1.0, true);
            }

            $maxLen = max(mb_strlen($normalizedInput), mb_strlen($normalizedCandidate));
            if ($maxLen === 0) {
                continue;
            }

            $distance = levenshtein($normalizedInput, $normalizedCandidate);
            $similarity = 1 - ($distance / $maxLen);

            if ($similarity >= $this->threshold && $similarity > $bestSimilarity) {
                $bestMatch = $candidate;
                $bestSimilarity = $similarity;
            }
        }

        return $bestMatch !== null
            ? new FuzzyMatch($bestMatch, $bestSimilarity, false)
            : null;
    }

    /**
     * Finde alle Matches über dem Threshold, sortiert nach Ähnlichkeit.
     *
     * @return FuzzyMatch[]
     */
    public function findAllMatches(string $input, array $candidates, int $limit = 5): array
    {
        $normalizedInput = $this->normalize($input);
        if ($normalizedInput === '') {
            return [];
        }

        $matches = [];

        foreach ($candidates as $candidate) {
            $normalizedCandidate = $this->normalize((string) $candidate);
            if ($normalizedCandidate === '') {
                continue;
            }

            $maxLen = max(mb_strlen($normalizedInput), mb_strlen($normalizedCandidate));
            if ($maxLen === 0) {
                continue;
            }

            if ($normalizedInput === $normalizedCandidate) {
                $matches[] = new FuzzyMatch($candidate, 1.0, true);
                continue;
            }

            $distance = levenshtein($normalizedInput, $normalizedCandidate);
            $similarity = 1 - ($distance / $maxLen);

            if ($similarity >= $this->threshold) {
                $matches[] = new FuzzyMatch($candidate, $similarity, false);
            }
        }

        usort($matches, fn(FuzzyMatch $a, FuzzyMatch $b) => $b->similarity <=> $a->similarity);

        return array_slice($matches, 0, $limit);
    }

    /**
     * Normalisiert einen String: lowercase, trim, Mehrfach-Leerzeichen entfernen.
     */
    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        // Mehrfache Leerzeichen auf eines reduzieren
        $value = (string) preg_replace('/\s+/', ' ', $value);

        return $value;
    }
}
