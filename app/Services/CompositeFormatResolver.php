<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Löst benannte und positionelle Platzhalter in composite_format-Strings auf.
 *
 * Unterstützt:
 * - Benannte Platzhalter: {breite}, {hoehe} → Suffix-Match auf child.technical_name
 * - Positionelle Platzhalter: {0}, {1}, {2} → Index-basiert (Rückwärtskompatibilität)
 */
class CompositeFormatResolver
{
    /**
     * Löst Platzhalter im composite_format auf.
     *
     * @param string               $format   z.B. "{breite} × {hoehe} × {tiefe} mm"
     * @param array<object|array>  $children Geordnete Kind-Attribute (mit technical_name)
     * @param array<string>        $values   Geordnete Werte (gleiche Reihenfolge wie $children)
     * @return string                        z.B. "210 × 75 × 230 mm"
     */
    public static function resolve(string $format, array $children, array $values): string
    {
        $result = $format;

        // 1. Benannte Platzhalter auflösen
        if (preg_match_all('/\{([^}]+)\}/', $format, $matches)) {
            foreach ($matches[1] as $placeholder) {
                // Positionelle Platzhalter ({0}, {1}) hier überspringen
                if (is_numeric($placeholder)) {
                    continue;
                }

                foreach ($children as $i => $child) {
                    $techName = is_array($child)
                        ? ($child['technical_name'] ?? '')
                        : ($child->technical_name ?? '');

                    // Exakter Match
                    if ($techName === $placeholder) {
                        $result = str_replace('{' . $placeholder . '}', (string) ($values[$i] ?? ''), $result);
                        break;
                    }

                    // Suffix-Match: dim-breite → {breite}
                    if (str_ends_with($techName, '-' . $placeholder)) {
                        $result = str_replace('{' . $placeholder . '}', (string) ($values[$i] ?? ''), $result);
                        break;
                    }
                }
            }
        }

        // 2. Positionelle Platzhalter auflösen (Fallback)
        foreach ($values as $i => $v) {
            $result = str_replace('{' . $i . '}', (string) ($v ?? ''), $result);
        }

        return trim($result);
    }
}
