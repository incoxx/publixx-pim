<?php

declare(strict_types=1);

namespace App\Services\Conformance;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductConformanceResult;
use App\Models\ProductReferenceProfile;

/**
 * Deterministische Konformitäts-Engine (Schritt 1, ohne KI).
 *
 * Prüft ein Produkt gegen die (vererbten) Regeln seines Referenz-Profils und
 * erzeugt einen strukturierten Abweichungs-Report. Der Report ist zugleich der
 * Input für die optionale KI-Erklärung (Schritt 2): Die KI muss die Abweichungen
 * nicht selbst finden, sondern nur erläutern.
 */
class ProfileConformanceChecker
{
    /** Maximale Vererbungstiefe (Zyklen-Schutz). */
    private const MAX_INHERITANCE_DEPTH = 20;

    /**
     * Prüft ein Produkt und persistiert das Ergebnis (nur das letzte pro Produkt).
     *
     * @param string $trigger save | manual | batch
     */
    public function check(Product $product, string $trigger = 'manual'): ?ProductConformanceResult
    {
        $profile = $product->referenceProfile;

        // Kein Profil referenziert → keine Prüfung, evtl. veraltetes Ergebnis entfernen
        if (!$profile) {
            ProductConformanceResult::where('product_id', $product->id)->delete();
            return null;
        }

        $rules = $this->effectiveRules($profile);
        $actuals = $this->resolveActualValues($product);

        $deviations = [];
        foreach ($rules as $rule) {
            $attr = $rule['attribute'] ?? null;
            if ($attr === null) {
                continue;
            }
            $deviation = self::evaluateRule($rule, $actuals[$attr] ?? null);
            if ($deviation !== null) {
                $deviations[] = $deviation;
            }
        }

        return $this->persist($product, $profile, $rules, $deviations, $trigger);
    }

    /**
     * Effektive Regeln eines Profils inkl. Vererbung.
     * Reihenfolge Root → Blatt, das spezifischste Profil gewinnt bei gleichem
     * Schlüssel (attribute + check).
     *
     * @return array<int, array<string, mixed>>
     */
    public function effectiveRules(ProductReferenceProfile $profile): array
    {
        // Vererbungskette aufbauen (Blatt → Root), mit Zyklen-/Tiefenschutz
        $chain = [];
        $seen = [];
        $current = $profile;
        $depth = 0;
        while ($current !== null && $depth < self::MAX_INHERITANCE_DEPTH) {
            if (isset($seen[$current->id])) {
                break; // Zyklus
            }
            $seen[$current->id] = true;
            $chain[] = $current;
            $current = $current->parentProfile;
            $depth++;
        }

        // Root zuerst mergen, Blatt überschreibt
        $merged = [];
        foreach (array_reverse($chain) as $prof) {
            foreach (($prof->rules ?? []) as $rule) {
                if (!is_array($rule) || !isset($rule['attribute'])) {
                    continue;
                }
                $key = ($rule['attribute'] ?? '') . '|' . ($rule['check'] ?? '');
                $merged[$key] = $rule;
            }
        }

        return array_values($merged);
    }

    /**
     * Wertet eine einzelne Regel gegen den Ist-Wert aus.
     *
     * @return array<string, mixed>|null  null = erfüllt, sonst Abweichung
     */
    public static function evaluateRule(array $rule, mixed $actual): ?array
    {
        $check = $rule['check'] ?? 'required';
        $severity = $rule['severity'] ?? 'error';
        $isEmpty = $actual === null || $actual === '';

        $fail = static function (string $message, mixed $expected) use ($rule, $check, $severity, $actual): array {
            return [
                'attribute' => $rule['attribute'] ?? null,
                'check' => $check,
                'severity' => $severity,
                'expected' => $expected,
                'actual' => $actual,
                'unit' => $rule['unit'] ?? null,
                'message' => $message,
            ];
        };

        switch ($check) {
            case 'required':
            case 'not_empty':
                if ($isEmpty) {
                    return $fail('Pflichtwert fehlt.', 'nicht leer');
                }
                return null;

            // Wertebereiche: leere Werte werden NICHT hier gemeldet (vermeidet
            // Doppel-Meldung mit "required"); nur vorhandene Werte werden geprüft.
            case 'min':
                if ($isEmpty) {
                    return null;
                }
                if ((float) $actual < (float) ($rule['value'] ?? $rule['min'] ?? 0)) {
                    return $fail('Wert unterschreitet Minimum.', '>= ' . ($rule['value'] ?? $rule['min']));
                }
                return null;

            case 'max':
                if ($isEmpty) {
                    return null;
                }
                if ((float) $actual > (float) ($rule['value'] ?? $rule['max'] ?? 0)) {
                    return $fail('Wert überschreitet Maximum.', '<= ' . ($rule['value'] ?? $rule['max']));
                }
                return null;

            case 'between':
                if ($isEmpty) {
                    return null;
                }
                $min = (float) ($rule['min'] ?? 0);
                $max = (float) ($rule['max'] ?? 0);
                $val = (float) $actual;
                if ($val < $min || $val > $max) {
                    return $fail('Wert außerhalb des plausiblen Bereichs.', $min . ' – ' . $max);
                }
                return null;

            case 'in_list':
                if ($isEmpty) {
                    return null;
                }
                $values = $rule['values'] ?? [];
                if (!in_array((string) $actual, array_map('strval', $values), true)) {
                    return $fail('Wert nicht in zulässiger Werteliste.', $values);
                }
                return null;

            case 'max_length':
                if ($isEmpty) {
                    return null;
                }
                if (mb_strlen((string) $actual) > (int) ($rule['value'] ?? 0)) {
                    return $fail('Text überschreitet maximale Länge.', '<= ' . ($rule['value'] ?? 0) . ' Zeichen');
                }
                return null;

            case 'regex':
                if ($isEmpty) {
                    return null;
                }
                $pattern = $rule['pattern'] ?? null;
                if ($pattern !== null && @preg_match($pattern, (string) $actual) !== 1) {
                    return $fail('Wert entspricht nicht dem erwarteten Format.', $pattern);
                }
                return null;

            default:
                // Unbekannter Check → ignorieren (vorwärtskompatibel)
                return null;
        }
    }

    /**
     * Liest die Attributwerte eines Produkts und liefert eine flache Map
     * technical_name → skalarer Ist-Wert. Pro Attribut wird der erste
     * nicht-leere Wert verwendet.
     *
     * @return array<string, mixed>
     */
    private function resolveActualValues(Product $product): array
    {
        $values = $product->attributeValues()
            ->with(['attribute:id,technical_name', 'valueListEntry:id,technical_name'])
            ->get();

        $map = [];
        foreach ($values as $pav) {
            $name = $pav->attribute?->technical_name;
            if ($name === null || array_key_exists($name, $map)) {
                continue;
            }
            $resolved = $this->resolveScalar($pav);
            if ($resolved !== null && $resolved !== '') {
                $map[$name] = $resolved;
            }
        }

        return $map;
    }

    private function resolveScalar(ProductAttributeValue $pav): mixed
    {
        if ($pav->value_number !== null) {
            return (float) $pav->value_number;
        }
        if ($pav->value_flag !== null) {
            return (bool) $pav->value_flag;
        }
        if ($pav->value_selection_id !== null) {
            // Selektionswerte werden über technical_name verglichen (Werteliste)
            return $pav->valueListEntry?->technical_name ?? $pav->value_selection_id;
        }
        return $pav->value_string;
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @param array<int, array<string, mixed>> $deviations
     */
    private function persist(
        Product $product,
        ProductReferenceProfile $profile,
        array $rules,
        array $deviations,
        string $trigger
    ): ProductConformanceResult {
        $errorCount = 0;
        $warningCount = 0;
        $infoCount = 0;
        foreach ($deviations as $d) {
            match ($d['severity'] ?? 'error') {
                'warning' => $warningCount++,
                'info' => $infoCount++,
                default => $errorCount++,
            };
        }

        if ($errorCount > 0) {
            $status = ProductConformanceResult::STATUS_FAIL;
        } elseif ($warningCount > 0) {
            $status = ProductConformanceResult::STATUS_WARNING;
        } else {
            $status = ProductConformanceResult::STATUS_PASS;
        }

        $total = count($rules);
        $passed = $total - count($deviations);
        $score = $total > 0 ? (int) round(($passed / $total) * 100) : 100;

        return ProductConformanceResult::updateOrCreate(
            ['product_id' => $product->id],
            [
                'profile_id' => $profile->id,
                'profile_version' => $profile->version,
                'status' => $status,
                'score' => max(0, min(100, $score)),
                'deviation_count' => count($deviations),
                'error_count' => $errorCount,
                'warning_count' => $warningCount,
                'info_count' => $infoCount,
                'deviations' => $deviations,
                'trigger' => $trigger,
                'checked_at' => now(),
            ]
        );
    }
}
