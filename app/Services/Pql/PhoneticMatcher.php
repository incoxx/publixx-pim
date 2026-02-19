<?php

declare(strict_types=1);

namespace App\Services\Pql;

/**
 * Phonetic matching for SOUNDS_LIKE queries.
 *
 * Primary: Kölner Phonetik (optimized for German)
 * Fallback: Soundex (for English/international)
 *
 * Examples:
 *   "Maier"   → "67"   (Kölner)
 *   "Meyer"   → "67"   (Kölner)
 *   "Schmidt" → "862"  (Kölner)
 *   "Schmitt" → "862"  (Kölner)
 *   "Müller"  → "657"  (Kölner)
 *   "Mueller" → "657"  (Kölner)
 */
final class PhoneticMatcher
{
    /**
     * Check if two strings sound alike using Kölner Phonetik + Soundex fallback.
     */
    public function soundsLike(string $a, string $b): bool
    {
        // Primary: Kölner Phonetik (better for German)
        $codeA = $this->koelnerPhonetik($a);
        $codeB = $this->koelnerPhonetik($b);

        if ($codeA === $codeB) {
            return true;
        }

        // Fallback: Soundex (better for English)
        return soundex($a) === soundex($b);
    }

    /**
     * Generate Kölner Phonetik code for a string.
     *
     * Implementation follows the algorithm by Hans Joachim Postel (1969).
     */
    public function koelnerPhonetik(string $input): string
    {
        $word = $this->prepareForPhonetic($input);
        if ($word === '') {
            return '';
        }

        $len = strlen($word);
        $codes = [];

        for ($i = 0; $i < $len; $i++) {
            $char = $word[$i];
            $prevChar = $i > 0 ? $word[$i - 1] : '';
            $nextChar = ($i + 1 < $len) ? $word[$i + 1] : '';

            $code = $this->getKoelnerCode($char, $prevChar, $nextChar, $i === 0);
            if ($code !== null) {
                $codes[] = $code;
            }
        }

        // Remove consecutive duplicates
        $result = '';
        $prevCode = '';
        foreach ($codes as $code) {
            if ($code !== $prevCode) {
                $result .= $code;
                $prevCode = $code;
            }
        }

        // Remove leading '0' except if it's the only character
        if (strlen($result) > 1) {
            $result = ltrim($result, '0');
            if ($result === '') {
                $result = '0';
            }
        }

        return $result;
    }

    /**
     * Get the phonetic code for a single character in context.
     */
    private function getKoelnerCode(string $char, string $prev, string $next, bool $isFirst): ?string
    {
        return match (true) {
            // Vowels → 0
            in_array($char, ['a', 'e', 'i', 'o', 'u'], true) => '0',

            // H → skip (no code)
            $char === 'h' => null,

            // B → 1
            $char === 'b' => '1',

            // P → 1 (unless followed by H → 3)
            $char === 'p' => $next === 'h' ? '3' : '1',

            // D, T → 8 before C/S/Z, else 2
            $char === 'd' || $char === 't' => in_array($next, ['c', 's', 'z'], true) ? '8' : '2',

            // F, V, W → 3
            $char === 'f' || $char === 'v' || $char === 'w' => '3',

            // G, K, Q → 4
            $char === 'g' || $char === 'k' || $char === 'q' => '4',

            // C at start → 4 if followed by A/H/K/L/O/Q/R/U/X, else 8
            $char === 'c' && $isFirst => in_array($next, ['a', 'h', 'k', 'l', 'o', 'q', 'r', 'u', 'x'], true) ? '4' : '8',

            // C after S/Z → 8
            $char === 'c' && in_array($prev, ['s', 'z'], true) => '8',

            // C after A/H/K/L/O/Q/R/U/X → 4
            $char === 'c' && in_array($prev, ['a', 'h', 'k', 'l', 'o', 'q', 'r', 'u', 'x'], true) => '4',

            // C (other) → 8
            $char === 'c' => '8',

            // X after C/K/Q → 8
            $char === 'x' && in_array($prev, ['c', 'k', 'q'], true) => '8',

            // X → 48
            $char === 'x' => '48',

            // L → 5
            $char === 'l' => '5',

            // M, N → 6
            $char === 'm' || $char === 'n' => '6',

            // R → 7
            $char === 'r' => '7',

            // S, Z → 8
            $char === 's' || $char === 'z' => '8',

            default => null,
        };
    }

    /**
     * Prepare input string for phonetic encoding.
     */
    private function prepareForPhonetic(string $input): string
    {
        // Lowercase
        $str = mb_strtolower(trim($input));

        // German umlauts
        $str = str_replace(
            ['ä', 'ö', 'ü', 'ß', 'é', 'è', 'ê', 'à', 'â', 'ì', 'î', 'ò', 'ô', 'ù', 'û'],
            ['ae', 'oe', 'ue', 'ss', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'o', 'u', 'u'],
            $str
        );

        // Remove non-alpha
        $str = (string) preg_replace('/[^a-z]/', '', $str);

        return $str;
    }

    /**
     * Get Kölner Phonetik code (public API for indexing).
     */
    public function getPhoneticCode(string $input): string
    {
        return $this->koelnerPhonetik($input);
    }

    /**
     * Get both phonetic codes for a string.
     *
     * @return array{koelner: string, soundex: string}
     */
    public function getPhoneticCodes(string $input): array
    {
        return [
            'koelner' => $this->koelnerPhonetik($input),
            'soundex' => soundex($input),
        ];
    }
}
