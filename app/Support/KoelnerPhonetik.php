<?php

declare(strict_types=1);

namespace App\Support;

/**
 * KoelnerPhonetik – Phonetischer Algorithmus für deutsche Sprache.
 *
 * Implementiert die Kölner Phonetik nach Hans Joachim Postel (1969).
 * Wird im PIM für die SOUNDS_LIKE-Funktion in PQL verwendet,
 * um phonetisch ähnliche Produktnamen zu finden (z.B. Maier = Meyer = Meier).
 *
 * Der Algorithmus wandelt einen deutschen Text in eine Ziffernfolge um,
 * die den phonetischen Klang repräsentiert. Gleich klingende Wörter
 * erzeugen dieselbe Ziffernfolge.
 *
 * Regelwerk (Zuordnung Buchstabe → Code):
 * 0: A, E, I, J, O, U, Y, Ä, Ö, Ü
 * 1: H (nur initial)
 * 3: F, V, W, P (vor H)
 * 35: PH
 * 4: G, K, Q, C (initial vor A/H/K/L/O/Q/R/U/X), C (vor A/H/K/O/Q/U/X nach S/Z)
 * 45: X (nicht nach C/K/Q)
 * 48: X (nach C/K/Q)
 * 5: L
 * 6: M, N
 * 7: R
 * 8: S, Z, ß, C (sonst), Ç
 *
 * @see https://de.wikipedia.org/wiki/K%C3%B6lner_Phonetik
 */
class KoelnerPhonetik
{
    /**
     * Text in Kölner Phonetik kodieren.
     *
     * @param string $text Eingabetext (deutsch)
     * @return string Phonetischer Code (Ziffernfolge)
     */
    public static function encode(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        // Wörter einzeln verarbeiten und Codes zusammenfügen
        $words = preg_split('/\s+/', trim($text));
        $codes = array_map([self::class, 'encodeWord'], $words);

        return implode(' ', array_filter($codes));
    }

    /**
     * Einzelnes Wort in Kölner Phonetik kodieren.
     */
    public static function encodeWord(string $word): string
    {
        if (trim($word) === '') {
            return '';
        }

        // Normalisierung: Kleinbuchstaben, Umlaute auflösen
        $word = mb_strtolower(trim($word));
        $word = self::normalizeUmlauts($word);

        // Sonderzeichen entfernen (nur Buchstaben behalten)
        $word = preg_replace('/[^a-z]/', '', $word);

        if ($word === '' || $word === null) {
            return '';
        }

        $chars = str_split($word);
        $length = count($chars);
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $chars[$i];
            $prevChar = $i > 0 ? $chars[$i - 1] : '';
            $nextChar = $i < $length - 1 ? $chars[$i + 1] : '';
            $isFirst = $i === 0;

            $digit = self::getCode($char, $prevChar, $nextChar, $isFirst);
            $code .= $digit;
        }

        // Doppelte Codes entfernen (nur aufeinanderfolgende gleiche Ziffern)
        $code = self::removeDuplicates($code);

        // Alle '0' entfernen, AUSSER am Anfang
        if (strlen($code) > 1) {
            $first = $code[0];
            $rest = str_replace('0', '', substr($code, 1));
            $code = $first . $rest;
        }

        return $code;
    }

    /**
     * Code-Zuordnung gemäß Kölner-Phonetik-Regeln.
     */
    private static function getCode(string $char, string $prev, string $next, bool $isFirst): string
    {
        return match (true) {
            // Vokale → 0
            in_array($char, ['a', 'e', 'i', 'j', 'o', 'u', 'y'], true) => '0',

            // H → ignorieren (leerer String)
            $char === 'h' => '',

            // B → 1
            $char === 'b' => '1',

            // P → 3 wenn vor H, sonst 1
            $char === 'p' => $next === 'h' ? '3' : '1',

            // D, T → 8 wenn vor C/S/Z, sonst 2
            in_array($char, ['d', 't'], true) => in_array($next, ['c', 's', 'z'], true) ? '8' : '2',

            // F, V, W → 3
            in_array($char, ['f', 'v', 'w'], true) => '3',

            // C: komplex, abhängig von Position und Kontext
            $char === 'c' => self::getCCode($prev, $next, $isFirst),

            // G, K, Q → 4
            in_array($char, ['g', 'k', 'q'], true) => '4',

            // X → 48 wenn nach C/K/Q, sonst 48 (Doppelcode)
            $char === 'x' => in_array($prev, ['c', 'k', 'q'], true) ? '8' : '48',

            // L → 5
            $char === 'l' => '5',

            // M, N → 6
            in_array($char, ['m', 'n'], true) => '6',

            // R → 7
            $char === 'r' => '7',

            // S, Z → 8
            in_array($char, ['s', 'z'], true) => '8',

            default => '',
        };
    }

    /**
     * Spezialbehandlung für den Buchstaben C.
     *
     * Regel:
     * - C initial vor A, H, K, L, O, Q, R, U, X → 4
     * - C vor A, H, K, O, Q, U, X (nicht nach S, Z) → 4
     * - C nach S, Z → 8
     * - C sonst → 8
     */
    private static function getCCode(string $prev, string $next, bool $isFirst): string
    {
        $vowelsAndSpecial = ['a', 'h', 'k', 'l', 'o', 'q', 'r', 'u', 'x'];

        if ($isFirst && in_array($next, $vowelsAndSpecial, true)) {
            return '4';
        }

        if (in_array($prev, ['s', 'z'], true)) {
            return '8';
        }

        if (in_array($next, ['a', 'h', 'k', 'o', 'q', 'u', 'x'], true)) {
            return '4';
        }

        return '8';
    }

    /**
     * Aufeinanderfolgende gleiche Ziffern entfernen.
     */
    private static function removeDuplicates(string $code): string
    {
        if ($code === '') {
            return '';
        }

        $result = $code[0];
        for ($i = 1, $len = strlen($code); $i < $len; $i++) {
            if ($code[$i] !== $code[$i - 1]) {
                $result .= $code[$i];
            }
        }

        return $result;
    }

    /**
     * Deutsche Umlaute normalisieren.
     * ä → ae, ö → oe, ü → ue, ß → ss
     */
    private static function normalizeUmlauts(string $text): string
    {
        return str_replace(
            ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü', 'ç'],
            ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue', 'c'],
            $text,
        );
    }

    /**
     * Zwei Texte phonetisch vergleichen.
     *
     * @return bool True wenn die phonetischen Codes übereinstimmen
     */
    public static function matches(string $a, string $b): bool
    {
        return self::encode($a) === self::encode($b);
    }

    /**
     * Phonetische Ähnlichkeit als Prozentwert (0–100).
     *
     * Basiert auf Levenshtein-Distanz der phonetischen Codes.
     */
    public static function similarity(string $a, string $b): float
    {
        $codeA = self::encode($a);
        $codeB = self::encode($b);

        if ($codeA === '' && $codeB === '') {
            return 100.0;
        }

        if ($codeA === '' || $codeB === '') {
            return 0.0;
        }

        $maxLen = max(strlen($codeA), strlen($codeB));
        $distance = levenshtein($codeA, $codeB);

        return round((1 - $distance / $maxLen) * 100, 1);
    }
}
