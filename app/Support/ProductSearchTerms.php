<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Baut MySQL-FULLTEXT-BOOLEAN-Suchterme für die Produktsuche.
 *
 * Gemeinsame Logik für MCP (search_products) und die Schnellsuche, damit beide
 * dieselbe (gute) Trefferqualität liefern:
 *  - jedes Wort wird Pflicht (+) und Präfix (*)  → "kryo sonde" → "+kryo* +sonde*"
 *  - zusätzlich eine umlaut-normalisierte Variante (ä→ae, ö→oe, ü→ue, ß→ss),
 *    damit Schreibweisen-Unterschiede gefunden werden.
 */
final class ProductSearchTerms
{
    /**
     * Baut einen BOOLEAN-MODE-Term: jedes Wort bekommt führendes + (AND) und
     * abschließendes * (Prefix). Einzelzeichen und MySQL-Sonderzeichen werden
     * bereinigt. Fallback: ganzer Begriff als Prefix.
     */
    public static function boolean(string $query): string
    {
        $words = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $terms = [];

        foreach ($words as $word) {
            $clean = preg_replace('/[+\-><()~*"@]/', '', $word);
            if ($clean !== null && mb_strlen($clean) >= 2) {
                $terms[] = '+' . $clean . '*';
            }
        }

        return $terms !== [] ? implode(' ', $terms) : trim($query) . '*';
    }

    /**
     * Wie boolean(), aber umlaut-normalisiert (kollationsresistente Variante).
     */
    public static function booleanAscii(string $query): string
    {
        return self::boolean(self::normalizeUmlauts($query));
    }

    /**
     * Ersetzt deutsche Umlaute durch ASCII-Äquivalente. MySQL-FULLTEXT-Indizes
     * mit utf8mb4_unicode_ci matchen Umlaute oft nicht gegen umlautfreie
     * Indexeinträge und umgekehrt.
     */
    public static function normalizeUmlauts(string $text): string
    {
        return str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
            ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'],
            $text,
        );
    }
}
