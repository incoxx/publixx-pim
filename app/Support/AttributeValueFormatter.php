<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formatiert Attributwerte für die tabellarische Anzeige (Produktliste,
 * Profisuche, Merkliste).
 *
 * Gemeinsame Logik, damit numerische Attributwerte in allen Listen-Ansichten
 * identisch und sauber dargestellt werden.
 */
final class AttributeValueFormatter
{
    /**
     * Entfernt bei Dezimalwerten die nachlaufenden Nullen (und den Dezimalpunkt
     * bei Ganzzahlen). Der Spalten-Cast `decimal:6` liefert z. B. "82.000000",
     * was in der Tabelle als "82" erscheinen soll.
     *
     *   "82.000000" → "82"
     *   "1.800000"  → "1.8"
     *   "0.000000"  → "0"
     */
    public static function number(int|float|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $str = (string) $value;

        if ($str === '' || !str_contains($str, '.')) {
            return $str;
        }

        $trimmed = rtrim(rtrim($str, '0'), '.');

        return ($trimmed === '' || $trimmed === '-') ? '0' : $trimmed;
    }
}
