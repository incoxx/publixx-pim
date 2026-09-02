<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Technischer Name als stabiler Schlüssel (Import/Export) aus einem Anzeigenamen.
 *
 * Aus TagController extrahiert, als TagGroup dieselbe Ableitung brauchte — zwei
 * Kopien wären genau die Stelle, an der die Umlaut-Behandlung später auseinanderläuft.
 */
final class TechnicalName
{
    /**
     * @param  callable(string): bool  $exists  Prüft, ob ein Kandidat schon vergeben ist
     */
    public static function resolve(?string $given, string $displayName, callable $exists): string
    {
        if ($given !== null && $given !== '') {
            return $given;
        }

        // Umlaute explizit vor der Transliteration ersetzen (sonst macht Str::slug
        // aus "für" ein "fur") — gleiche Regel wie BmecatFormatImporter::sanitizeTechnicalName().
        $normalized = str_replace(
            ['Ä', 'ä', 'Ö', 'ö', 'Ü', 'ü', 'ß'],
            ['Ae', 'ae', 'Oe', 'oe', 'Ue', 'ue', 'ss'],
            $displayName,
        );

        $base = Str::limit(Str::slug($normalized), 90, '') ?: 'eintrag';
        $candidate = $base;
        $suffix = 2;

        while ($exists($candidate)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
