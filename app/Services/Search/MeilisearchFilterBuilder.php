<?php

declare(strict_types=1);

namespace App\Services\Search;

/**
 * Übersetzt extrahierte bzw. explizite Constraints in einen
 * Meilisearch-Filter-Ausdruck.
 *
 * Mehrere Kandidaten-Felder eines Constraints (nicht disambiguierbare
 * Einheit) werden OR-verknüpft, Constraints untereinander AND-verknüpft.
 */
final class MeilisearchFilterBuilder
{
    /**
     * @param array $filters Einträge mit fields[], op (le|ge|lt|gt|eq|neq|range), value bzw. min/max
     */
    public static function build(array $filters): string
    {
        $clauses = [];

        foreach ($filters as $filter) {
            $fieldClauses = [];

            foreach ($filter['fields'] as $field) {
                $fieldClauses[] = self::fieldClause($field, $filter);
            }

            if ($fieldClauses === []) {
                continue;
            }

            $clauses[] = count($fieldClauses) > 1
                ? '(' . implode(' OR ', $fieldClauses) . ')'
                : $fieldClauses[0];
        }

        return implode(' AND ', $clauses);
    }

    private static function fieldClause(string $field, array $filter): string
    {
        return match ($filter['op']) {
            'range' => sprintf(
                '(%s >= %s AND %s <= %s)',
                $field,
                self::value($filter['min']),
                $field,
                self::value($filter['max']),
            ),
            'le' => $field . ' <= ' . self::value($filter['value']),
            'ge' => $field . ' >= ' . self::value($filter['value']),
            'lt' => $field . ' < ' . self::value($filter['value']),
            'gt' => $field . ' > ' . self::value($filter['value']),
            'neq' => $field . ' != ' . self::value($filter['value']),
            default => $field . ' = ' . self::value($filter['value']),
        };
    }

    /**
     * Wert für den Filter-Ausdruck serialisieren (Strings quoten/escapen).
     */
    private static function value(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value) . '"';
    }
}
