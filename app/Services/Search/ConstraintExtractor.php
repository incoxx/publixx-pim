<?php

declare(strict_types=1);

namespace App\Services\Search;

/**
 * Deterministischer Constraint-Extraktor (Regex, kein LLM im heißen Pfad).
 *
 * Erkennt quantitative Angaben in natürlichsprachigen Suchanfragen und
 * übersetzt sie in harte Filter:
 *
 *   „smartes Telefon mit 8 Zoll Bildschirm"  → attr_diagonale ∈ [7.6, 8.4]
 *   „unter 300 €"                            → list_price <= 300
 *   „zwischen 1 und 2 kg"                    → attr_gewicht ∈ [1, 2]
 *
 * Das Vokabular (Einheiten-Tokens, Attribut-Kandidaten, Konzept-Synonyme)
 * stammt aus dem gecachten PIM-Schema (SearchSchemaService) — nicht aus
 * hartkodierten Tabellen. Werte werden in die Basiseinheit der indexierten
 * Dokumente normalisiert.
 */
final class ConstraintExtractor
{
    private const NUM = '\d+(?:[.,]\d+)?';

    /** Komparator-Wörter → Operator. */
    private const COMPARATORS = [
        'unter' => 'le', 'weniger als' => 'le', 'höchstens' => 'le',
        'maximal' => 'le', 'max' => 'le', 'bis' => 'le', 'bis zu' => 'le',
        'under' => 'le', 'below' => 'le', 'less than' => 'le', 'up to' => 'le',
        'über' => 'ge', 'mehr als' => 'ge', 'mindestens' => 'ge',
        'minimal' => 'ge', 'min' => 'ge', 'ab' => 'ge',
        'over' => 'ge', 'above' => 'ge', 'more than' => 'ge', 'at least' => 'ge',
    ];

    public function __construct(
        private readonly array $schema,
        private readonly float $tolerance = 0.05,
    ) {
    }

    /**
     * Constraints extrahieren.
     *
     * @return array{filters: array, residual: string, unresolved: array}
     */
    public function extract(string $query): array
    {
        $filters = [];
        $unresolved = [];
        $working = $query;

        $unitPattern = $this->unitPattern();
        if ($unitPattern === null) {
            return ['filters' => [], 'residual' => trim($query), 'unresolved' => []];
        }

        $lcQuery = mb_strtolower($query);
        $num = self::NUM;

        // 1) Bereiche: „zwischen 5 und 8 Zoll", „von 1 bis 2 kg"
        $working = preg_replace_callback(
            "/(?<![\\p{L}\\p{N}])(?:zwischen|von|between|from)\\s+({$num})\\s+(?:und|bis|and|to)\\s+({$num})\\s*({$unitPattern})(?![\\p{L}\\p{N}])/iu",
            function (array $m) use (&$filters, &$unresolved, $lcQuery): string {
                $this->addConstraint(
                    $filters,
                    $unresolved,
                    $lcQuery,
                    $m[3],
                    'range',
                    $this->toFloat($m[1]),
                    $this->toFloat($m[2]),
                    $m[0],
                );

                return ' ';
            },
            $working,
        );

        // 2) Komparatoren: „unter 300 €", „mindestens 2 kg"
        $comparatorPattern = implode('|', array_map(
            fn (string $w): string => str_replace(' ', '\\s+', preg_quote($w, '/')),
            array_keys(self::COMPARATORS),
        ));
        $working = preg_replace_callback(
            "/(?<![\\p{L}\\p{N}])({$comparatorPattern})\\.?\\s+({$num})\\s*({$unitPattern})(?![\\p{L}\\p{N}])/iu",
            function (array $m) use (&$filters, &$unresolved, $lcQuery): string {
                $word = preg_replace('/\s+/', ' ', mb_strtolower($m[1]));
                $op = self::COMPARATORS[$word] ?? 'le';
                $value = $this->toFloat($m[2]);
                $this->addConstraint($filters, $unresolved, $lcQuery, $m[3], $op, $value, null, $m[0]);

                return ' ';
            },
            $working,
        );

        // 3) Zahl + Einheit ohne Komparator: „8 Zoll" → Toleranzbereich
        $working = preg_replace_callback(
            "/(?<![\\p{L}\\p{N}])({$num})\\s*({$unitPattern})(?![\\p{L}\\p{N}])/iu",
            function (array $m) use (&$filters, &$unresolved, $lcQuery): string {
                $this->addConstraint(
                    $filters,
                    $unresolved,
                    $lcQuery,
                    $m[2],
                    'eq',
                    $this->toFloat($m[1]),
                    null,
                    $m[0],
                );

                return ' ';
            },
            $working,
        );

        // Unaufgelöste Constraints bleiben Teil der Keyword-Suche
        foreach ($unresolved as $u) {
            $working .= ' ' . $u['text'];
        }

        return [
            'filters' => $filters,
            'residual' => trim((string) preg_replace('/\s+/', ' ', $working)),
            'unresolved' => $unresolved,
        ];
    }

    /**
     * Constraint auflösen (Einheit → Attribut-Kandidaten → Felder) und
     * Wert in die Basiseinheit normalisieren.
     */
    private function addConstraint(
        array &$filters,
        array &$unresolved,
        string $lcQuery,
        string $unitToken,
        string $op,
        float $value,
        ?float $value2,
        string $matchedText,
    ): void {
        $token = mb_strtolower(trim($unitToken));
        $info = $this->schema['units'][$token] ?? null;

        if ($info === null) {
            return;
        }

        $toBase = (float) ($info['to_base'] ?? 1.0);
        $value *= $toBase;
        if ($value2 !== null) {
            $value2 *= $toBase;
        }

        // Währung → direkter Filter auf list_price.
        // Ohne Komparator wird der Betrag als Budget-Obergrenze interpretiert.
        if (!empty($info['currency'])) {
            $fields = ['list_price'];
            $attributeNames = ['list_price'];
            if ($op === 'eq') {
                $op = 'le';
            }
        } else {
            $attributeNames = $this->resolveAttributes($info, $lcQuery);
            if ($attributeNames === []) {
                $unresolved[] = ['unit' => $token, 'text' => trim($matchedText)];

                return;
            }
            $fields = array_map(
                fn (string $name): string => $this->schema['attributes'][$name]['field'],
                $attributeNames,
            );
        }

        // Weiche Gleichheit: „8 Zoll" → Toleranzbereich
        if ($op === 'eq' && $this->tolerance > 0) {
            $op = 'range';
            $value2 = $value * (1 + $this->tolerance);
            $value = $value * (1 - $this->tolerance);
        }

        $filter = [
            'fields' => $fields,
            'attributes' => $attributeNames,
            'op' => $op,
            'unit' => $token,
            'text' => trim($matchedText),
        ];

        if ($op === 'range') {
            $filter['min'] = round(min($value, $value2 ?? $value), 6);
            $filter['max'] = round(max($value, $value2 ?? $value), 6);
        } else {
            $filter['value'] = round($value, 6);
        }

        $filters[] = $filter;
    }

    /**
     * Attribut-Kandidaten für einen Einheiten-Token ermitteln.
     *
     * 1. Direkte Bindung aus Config-Alias ('attributes') oder alle
     *    Number-Attribute der Einheitengruppe.
     * 2. Disambiguierung über Kontext: Konzept-Synonyme und Attribut-Aliase
     *    im Query schränken die Kandidaten ein („8 Zoll Bildschirm" → diagonale).
     *
     * @return string[] technical_names
     */
    private function resolveAttributes(array $unitInfo, string $lcQuery): array
    {
        $candidates = $unitInfo['attributes']
            ?? ($this->schema['unit_attributes'][$unitInfo['group_id'] ?? ''] ?? []);

        $candidates = array_values(array_filter(
            $candidates,
            fn (string $name): bool => isset($this->schema['attributes'][$name]),
        ));

        if (count($candidates) <= 1) {
            return $candidates;
        }

        // Kontext über Konzept-Synonyme
        foreach (($this->schema['concepts'] ?? []) as $word => $names) {
            if (str_contains($lcQuery, (string) $word)) {
                $narrowed = array_values(array_intersect($candidates, $names));
                if ($narrowed !== []) {
                    return $narrowed;
                }
            }
        }

        // Kontext über Attribut-Aliase (Name de/en, technical_name)
        foreach ($candidates as $name) {
            foreach ($this->schema['attributes'][$name]['aliases'] ?? [] as $alias) {
                if ($alias !== '' && str_contains($lcQuery, (string) $alias)) {
                    return [$name];
                }
            }
        }

        // Keine Disambiguierung möglich → alle Kandidaten (OR-verknüpft)
        return $candidates;
    }

    /**
     * Alternation aller bekannten Einheiten-Tokens (längste zuerst).
     */
    private function unitPattern(): ?string
    {
        $tokens = array_map('strval', array_keys($this->schema['units'] ?? []));
        if ($tokens === []) {
            return null;
        }

        usort($tokens, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return implode('|', array_map(fn (string $t): string => preg_quote($t, '/'), $tokens));
    }

    private function toFloat(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}
