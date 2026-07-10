<?php

declare(strict_types=1);

namespace App\Services\Attributes;

use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;

/**
 * Zentrale Auflösung der Anzeige-/Exportwerte für die referenzierenden und
 * mehrwertigen Attribut-Datentypen (HierarchyNodeReference, ProductReference,
 * SimpleMultiSelect).
 *
 * Zweck: Die neue, typspezifische Logik lebt an genau einer Stelle statt in den
 * ~25 duplizierten data_type-Switches. Bestehende Resolver (Preview, Katalog,
 * Export) delegieren über {@see self::handles()} als Guard hierher und lassen
 * ihre Alttyp-Switches unverändert (null Regressionsrisiko).
 *
 * SimpleSelect wird bewusst NICHT behandelt: dessen Wert ist ein reiner
 * value_string und läuft korrekt in den bestehenden default-Zweig der Aufrufer.
 */
class AttributeValuePresenter
{
    /**
     * Identity-Cache für aufgelöste Referenzziele (pro Instanz/Request).
     * Verhindert N+1-Queries, wenn viele Produkte dasselbe Ziel referenzieren
     * (z. B. viele Produkte → gleicher Kategorie-Knoten). Wird eine Instanz
     * über einen Export-/Preview-Lauf wiederverwendet, greift der Cache.
     *
     * @var array<string, array<string, mixed>|null>
     */
    private array $referenceCache = [];

    /**
     * Übernimmt dieser Presenter die Auflösung für den Datentyp?
     * Aufrufer nutzen dies als Guard am Anfang ihres data_type-Switch.
     */
    public function handles(?string $dataType): bool
    {
        return in_array($dataType, Attribute::REFERENCE_TYPES, true)
            || $dataType === 'SimpleMultiSelect';
    }

    /**
     * Guard für menschenlesbare Anzeige (Preview/Katalog): wie {@see handles()},
     * zusätzlich SimpleSelect, damit dort das Label ("Rot (#FF0000)") statt des
     * rohen Werts erscheint. Exporte nutzen weiterhin {@see handles()} und
     * erhalten den Rohwert.
     */
    public function handlesForDisplay(?string $dataType): bool
    {
        return $this->handles($dataType) || $dataType === 'SimpleSelect';
    }

    /**
     * Anzeigewert als String.
     *
     * @param bool $withLabels Bei SimpleSelect/SimpleMultiSelect die in
     *   simple_options hinterlegten Labels auflösen ("Label (Wert)"). Für die
     *   Preview true, für flache Exporte false (Rohwert bleibt exportiert).
     */
    public function displayValue(ProductAttributeValue $pav, string $lang = 'de', bool $withLabels = false): ?string
    {
        $type = $pav->attribute?->data_type;

        if ($type === 'ProductReference') {
            $ref = $this->productReference($pav);

            return $ref === null ? null : ($ref['name'] ?? $ref['sku'] ?? null);
        }

        if ($type === 'HierarchyNodeReference') {
            $ref = $this->hierarchyNodeReference($pav, $lang);

            return $ref === null ? null : ($ref['name'] ?? null);
        }

        if ($type === 'SimpleMultiSelect') {
            $values = $this->simpleMultiValues($pav);
            if ($values === []) {
                return null;
            }
            if ($withLabels) {
                $values = array_map(fn (string $v): string => $this->optionLabel($pav, $v), $values);
            }

            return implode(', ', $values);
        }

        if ($type === 'SimpleSelect') {
            $value = $pav->value_string;
            if ($value === null || $value === '') {
                return null;
            }

            return $withLabels ? $this->optionLabel($pav, $value) : $value;
        }

        // Alles andere: roher value_string.
        return $pav->value_string;
    }

    /**
     * Löst für einen gespeicherten Wert das in simple_options hinterlegte Label
     * auf. Optionen haben die Form "Wert" oder "Label::Wert"; bei Treffer mit
     * Label wird "Label (Wert)" geliefert, sonst der Rohwert.
     */
    private function optionLabel(ProductAttributeValue $pav, string $value): string
    {
        $options = $pav->attribute?->simple_options ?? [];
        if (!is_array($options)) {
            return $value;
        }

        foreach ($options as $option) {
            $option = (string) $option;
            $sep = strpos($option, '::');
            if ($sep === false) {
                if ($option === $value) {
                    return $value;
                }
                continue;
            }

            $label = trim(substr($option, 0, $sep));
            $optionValue = trim(substr($option, $sep + 2));
            if ($optionValue === $value) {
                return $label !== '' ? "{$label} ({$value})" : $value;
            }
        }

        return $value;
    }

    /**
     * Strukturierte Referenz-Daten für Preview-Frontend und strukturierte
     * Exporte (JSON). Liefert null, wenn der Typ keine Referenz ist.
     *
     * @return array<string, mixed>|null
     */
    public function referenceData(ProductAttributeValue $pav, string $lang = 'de'): ?array
    {
        return match ($pav->attribute?->data_type) {
            'ProductReference'       => $this->productReference($pav),
            'HierarchyNodeReference' => $this->hierarchyNodeReference($pav, $lang),
            default                  => null,
        };
    }

    /**
     * Exportwert für strukturierte Formate: Referenz-Objekt, Werte-Array
     * (SimpleMultiSelect) oder Skalar (SimpleSelect/Fallback).
     */
    public function exportValue(ProductAttributeValue $pav, string $lang = 'de'): mixed
    {
        return match ($pav->attribute?->data_type) {
            'ProductReference', 'HierarchyNodeReference' => $this->referenceData($pav, $lang),
            'SimpleMultiSelect'                          => $this->simpleMultiValues($pav),
            default                                      => $pav->value_string,
        };
    }

    /**
     * Dekodiert das in value_string abgelegte JSON-Array von SimpleMultiSelect.
     *
     * @return list<string>
     */
    public function simpleMultiValues(ProductAttributeValue $pav): array
    {
        $raw = $pav->value_string;
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            // Robustheit: einzelner Rohwert ohne JSON-Kodierung.
            return [$raw];
        }

        return array_values(array_map(static fn ($v): string => (string) $v, $decoded));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function productReference(ProductAttributeValue $pav): ?array
    {
        $id = $pav->value_string;
        if ($id === null || $id === '') {
            return null;
        }

        $cacheKey = 'product:' . $id;
        if (array_key_exists($cacheKey, $this->referenceCache)) {
            return $this->referenceCache[$cacheKey];
        }

        $product = Product::query()->find($id, ['id', 'sku', 'name']);
        $result = $product === null
            ? ['id' => $id, 'type' => 'product', 'exists' => false, 'sku' => null, 'name' => null]
            : [
                'id'     => $product->id,
                'type'   => 'product',
                'exists' => true,
                'sku'    => $product->sku,
                'name'   => $product->name,
            ];

        return $this->referenceCache[$cacheKey] = $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hierarchyNodeReference(ProductAttributeValue $pav, string $lang): ?array
    {
        $id = $pav->value_string;
        if ($id === null || $id === '') {
            return null;
        }

        $cacheKey = 'node:' . $lang . ':' . $id;
        if (array_key_exists($cacheKey, $this->referenceCache)) {
            return $this->referenceCache[$cacheKey];
        }

        $node = HierarchyNode::query()->find($id, ['id', 'name_de', 'name_en', 'path', 'hierarchy_id']);
        $result = $node === null
            ? ['id' => $id, 'type' => 'hierarchy_node', 'exists' => false, 'name' => null, 'path' => null]
            : [
                'id'           => $node->id,
                'type'         => 'hierarchy_node',
                'exists'       => true,
                'name'         => $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de,
                'path'         => $node->path,
                'hierarchy_id' => $node->hierarchy_id,
            ];

        return $this->referenceCache[$cacheKey] = $result;
    }
}
