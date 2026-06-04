<?php

declare(strict_types=1);

namespace App\Services\Conformance;

use App\Models\Product;

/**
 * Leitet Vorschläge für Prüfregeln aus einem oder mehreren gepflegten
 * Musterprodukten ab (deterministisch, ohne KI).
 *
 * Die Vorschläge werden NICHT gespeichert, sondern dienen als Startpunkt im
 * Profil-Editor. Der Nutzer verfeinert Severities, Bereiche und Wertelisten.
 */
class RuleSuggester
{
    /**
     * @param array<int, string> $productIds
     * @return array<int, array<string, mixed>>
     */
    public function suggest(array $productIds, float $tolerancePercent = 20.0): array
    {
        $products = Product::whereIn('id', $productIds)
            ->with([
                'attributeValues.attribute:id,technical_name,name_de,data_type,max_characters,min_value,max_value',
                'attributeValues.valueListEntry:id,technical_name',
            ])
            ->get();

        // Pro Attribut die beobachteten Werte über alle Muster sammeln
        $observed = [];
        foreach ($products as $product) {
            foreach ($product->attributeValues as $pav) {
                $attr = $pav->attribute;
                if (!$attr) {
                    continue;
                }
                $name = $attr->technical_name;
                $observed[$name] ??= [
                    'attribute' => $attr,
                    'numbers' => [],
                    'selections' => [],
                    'has_string' => false,
                ];

                if ($pav->value_number !== null) {
                    $observed[$name]['numbers'][] = (float) $pav->value_number;
                } elseif ($pav->value_selection_id !== null) {
                    $tn = $pav->valueListEntry?->technical_name;
                    if ($tn !== null) {
                        $observed[$name]['selections'][$tn] = true;
                    }
                } elseif ($pav->value_string !== null && $pav->value_string !== '') {
                    $observed[$name]['has_string'] = true;
                }
            }
        }

        $rules = [];
        foreach ($observed as $name => $data) {
            $attr = $data['attribute'];

            // 1. Pflichtfeld (default warning – im Editor auf error hochstufbar)
            $rules[] = [
                'attribute' => $name,
                'label' => $attr->name_de,
                'check' => 'required',
                'severity' => 'warning',
                'source' => 'sample',
            ];

            // 2. Numerischer Bereich
            if (!empty($data['numbers'])) {
                [$min, $max] = $this->numericRange($data['numbers'], $tolerancePercent, $attr);
                $rules[] = [
                    'attribute' => $name,
                    'label' => $attr->name_de,
                    'check' => 'between',
                    'min' => $min,
                    'max' => $max,
                    'severity' => 'warning',
                    'source' => 'sample',
                ];
                continue;
            }

            // 3. Auswahl-Werteliste
            if (!empty($data['selections'])) {
                $rules[] = [
                    'attribute' => $name,
                    'label' => $attr->name_de,
                    'check' => 'in_list',
                    'values' => array_keys($data['selections']),
                    'severity' => 'warning',
                    'source' => 'sample',
                ];
                continue;
            }

            // 4. Textlänge (falls am Attribut definiert)
            if ($data['has_string'] && $attr->max_characters) {
                $rules[] = [
                    'attribute' => $name,
                    'label' => $attr->name_de,
                    'check' => 'max_length',
                    'value' => (int) $attr->max_characters,
                    'severity' => 'info',
                    'source' => 'sample',
                ];
            }
        }

        return $rules;
    }

    /**
     * Bereich aus beobachteten Werten ± Toleranz, begrenzt durch harte
     * Attribut-Grenzen (min_value / max_value), falls definiert.
     *
     * @param array<int, float> $numbers
     * @return array{0: float, 1: float}
     */
    private function numericRange(array $numbers, float $tolerancePercent, $attr): array
    {
        $observedMin = min($numbers);
        $observedMax = max($numbers);
        $factor = $tolerancePercent / 100;

        $lower = $observedMin - abs($observedMin) * $factor;
        $upper = $observedMax + abs($observedMax) * $factor;

        // Harte Attribut-Grenzen nicht überschreiten
        if ($attr->min_value !== null) {
            $lower = max($lower, (float) $attr->min_value);
        }
        if ($attr->max_value !== null) {
            $upper = min($upper, (float) $attr->max_value);
        }

        return [round($lower, 2), round($upper, 2)];
    }
}
