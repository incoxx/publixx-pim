<?php

declare(strict_types=1);

namespace App\Services\Formatting;

use App\Models\AttributeFormattingRule;

/**
 * Wendet eine AttributeFormattingRule auf einen rohen Attributwert an.
 *
 * Wird ausschließlich für die Ausgabe genutzt (Export, Preview-Katalog, Produkteditor-Vorschau) —
 * der Rohwert in der Datenbank bleibt davon unberührt.
 */
class AttributeFormattingService
{
    public static function apply(mixed $rawValue, ?AttributeFormattingRule $rule): string
    {
        if ($rawValue === null || $rule === null) {
            return (string) $rawValue;
        }

        $value = (string) $rawValue;
        $config = $rule->config ?? [];

        return match ($rule->rule_type) {
            'uppercase' => mb_strtoupper($value, 'UTF-8'),
            'lowercase' => mb_strtolower($value, 'UTF-8'),
            'capitalize' => mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($value, 1, null, 'UTF-8'),
            'regex' => self::applyRegex($value, $config),
            'number_format' => self::applyNumberFormat($rawValue, $config),
            default => $value,
        };
    }

    private static function applyRegex(string $value, array $config): string
    {
        $pattern = $config['pattern'] ?? null;
        if ($pattern === null || $pattern === '') {
            return $value;
        }

        $replacement = $config['replacement'] ?? '';
        $flags = !empty($config['case_insensitive']) ? 'iu' : 'u';
        $delimitedPattern = '/' . str_replace('/', '\/', $pattern) . '/' . $flags;

        $result = @preg_replace($delimitedPattern, $replacement, $value);

        return $result ?? $value;
    }

    private static function applyNumberFormat(mixed $rawValue, array $config): string
    {
        if (!is_numeric($rawValue)) {
            return (string) $rawValue;
        }

        $decimals = (int) ($config['decimals'] ?? 2);
        $decimalSeparator = $config['decimal_separator'] ?? ',';
        $thousandsSeparator = $config['thousands_separator'] ?? '.';
        $prefix = $config['prefix'] ?? '';
        $suffix = $config['suffix'] ?? '';

        $formatted = number_format((float) $rawValue, $decimals, $decimalSeparator, $thousandsSeparator);

        return $prefix . $formatted . $suffix;
    }
}
