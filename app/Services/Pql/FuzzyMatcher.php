<?php

declare(strict_types=1);

namespace App\Services\Pql;

/**
 * Fuzzy string matching: Levenshtein (60%) + Trigram (40%).
 *
 * Used post-FULLTEXT-prefilter in PHP to calculate similarity scores
 * and filter on threshold.
 */
final class FuzzyMatcher
{
    private const LEVENSHTEIN_WEIGHT = 0.6;
    private const TRIGRAM_WEIGHT = 0.4;

    /**
     * Calculate fuzzy similarity score between two strings.
     *
     * @return float Score between 0.0 and 1.0
     */
    public function similarity(string $input, string $candidate): float
    {
        $inputNorm = $this->normalize($input);
        $candidateNorm = $this->normalize($candidate);

        if ($inputNorm === '' || $candidateNorm === '') {
            return $inputNorm === $candidateNorm ? 1.0 : 0.0;
        }

        $levenshteinScore = $this->levenshteinSimilarity($inputNorm, $candidateNorm);
        $trigramScore = $this->trigramSimilarity($inputNorm, $candidateNorm);

        return (self::LEVENSHTEIN_WEIGHT * $levenshteinScore)
             + (self::TRIGRAM_WEIGHT * $trigramScore);
    }

    /**
     * Filter a list of candidates by fuzzy threshold.
     *
     * @param string $term Search term
     * @param array<int|string, string> $candidates Map of id => value
     * @param float $threshold Minimum similarity 0.0–1.0
     * @return array<int|string, array{value: string, score: float}> Filtered results sorted by score DESC
     */
    public function filterByThreshold(string $term, array $candidates, float $threshold = 0.7): array
    {
        $results = [];

        foreach ($candidates as $key => $value) {
            $score = $this->similarity($term, $value);
            if ($score >= $threshold) {
                $results[$key] = [
                    'value' => $value,
                    'score' => round($score, 4),
                ];
            }
        }

        // Sort by score descending
        uasort($results, fn(array $a, array $b) => $b['score'] <=> $a['score']);

        return $results;
    }

    /**
     * Levenshtein-based similarity: 1 - (distance / max_length).
     */
    private function levenshteinSimilarity(string $a, string $b): float
    {
        $maxLen = max(mb_strlen($a), mb_strlen($b));
        if ($maxLen === 0) {
            return 1.0;
        }

        // PHP levenshtein() only works with byte-strings up to 255 chars
        $aBytes = substr($a, 0, 255);
        $bBytes = substr($b, 0, 255);

        $distance = levenshtein($aBytes, $bBytes);
        $maxBytesLen = max(strlen($aBytes), strlen($bBytes));

        return $maxBytesLen > 0 ? 1.0 - ($distance / $maxBytesLen) : 1.0;
    }

    /**
     * Trigram (3-gram) similarity: |intersection| / |union|.
     * Jaccard coefficient over character trigrams.
     */
    private function trigramSimilarity(string $a, string $b): float
    {
        $trigramsA = $this->trigrams($a);
        $trigramsB = $this->trigrams($b);

        if (empty($trigramsA) && empty($trigramsB)) {
            return 1.0;
        }

        $intersection = array_intersect_key($trigramsA, $trigramsB);
        $intersectionCount = 0;
        foreach ($intersection as $key => $_) {
            $intersectionCount += min($trigramsA[$key], $trigramsB[$key]);
        }

        $totalA = array_sum($trigramsA);
        $totalB = array_sum($trigramsB);
        $union = $totalA + $totalB - $intersectionCount;

        return $union > 0 ? $intersectionCount / $union : 0.0;
    }

    /**
     * Generate trigrams with counts.
     *
     * @return array<string, int>
     */
    private function trigrams(string $str): array
    {
        // Pad for edge trigrams
        $padded = '  ' . $str . '  ';
        $len = mb_strlen($padded);
        $trigrams = [];

        for ($i = 0; $i <= $len - 3; $i++) {
            $tri = mb_substr($padded, $i, 3);
            $trigrams[$tri] = ($trigrams[$tri] ?? 0) + 1;
        }

        return $trigrams;
    }

    /**
     * Normalize string for comparison: lowercase, trim, collapse whitespace.
     */
    private function normalize(string $str): string
    {
        $str = mb_strtolower(trim($str));
        $str = (string) preg_replace('/\s+/', ' ', $str);
        return $str;
    }
}
