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
     * Übernimmt dieser Presenter die Auflösung für den Datentyp?
     * Aufrufer nutzen dies als Guard am Anfang ihres data_type-Switch.
     */
    public function handles(?string $dataType): bool
    {
        return in_array($dataType, Attribute::REFERENCE_TYPES, true)
            || $dataType === 'SimpleMultiSelect';
    }

    /**
     * Anzeigewert als String (Preview, Katalog, flache Exporte).
     */
    public function displayValue(ProductAttributeValue $pav, string $lang = 'de'): ?string
    {
        $type = $pav->attribute?->data_type;

        if ($type === 'ProductReference') {
            $ref = $this->productReference($pav);

            return $ref['name'] ?? $ref['sku'] ?? null;
        }

        if ($type === 'HierarchyNodeReference') {
            $ref = $this->hierarchyNodeReference($pav, $lang);

            return $ref['name'] ?? null;
        }

        if ($type === 'SimpleMultiSelect') {
            $values = $this->simpleMultiValues($pav);

            return $values === [] ? null : implode(', ', $values);
        }

        // SimpleSelect und alles andere: roher value_string.
        return $pav->value_string;
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

        $product = Product::query()->find($id, ['id', 'sku', 'name']);
        if ($product === null) {
            return ['id' => $id, 'type' => 'product', 'exists' => false, 'sku' => null, 'name' => null];
        }

        return [
            'id'     => $product->id,
            'type'   => 'product',
            'exists' => true,
            'sku'    => $product->sku,
            'name'   => $product->name,
        ];
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

        $node = HierarchyNode::query()->find($id, ['id', 'name_de', 'name_en', 'path', 'hierarchy_id']);
        if ($node === null) {
            return ['id' => $id, 'type' => 'hierarchy_node', 'exists' => false, 'name' => null, 'path' => null];
        }

        $name = $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de;

        return [
            'id'           => $node->id,
            'type'         => 'hierarchy_node',
            'exists'       => true,
            'name'         => $name,
            'path'         => $node->path,
            'hierarchy_id' => $node->hierarchy_id,
        ];
    }
}
