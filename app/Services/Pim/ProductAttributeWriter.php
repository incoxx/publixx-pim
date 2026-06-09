<?php

declare(strict_types=1);

namespace App\Services\Pim;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Unit;

/**
 * Setzt einzelne Produktattribut-Werte — gemeinsame Schreiblogik für MCP-Tool
 * und Copilot. Spiegelt die Spalten-Zuordnung von ProductAttributeValueController
 * (data_type → value_*-Spalte) und respektiert die translatable-Regel.
 *
 * Unterstützt skalare Attributtypen; Composite/Collection werden abgelehnt
 * (diese sind Container ohne eigenen Wert).
 */
final class ProductAttributeWriter
{
    /**
     * @param  string       $productRef    Produkt-UUID oder SKU
     * @param  string       $attributeRef  Attribut-UUID oder technical_name
     * @param  mixed        $value         Neuer Wert (String/Zahl/Bool/Datum)
     * @param  string|null  $language      Sprachcode (Pflicht bei übersetzbaren Attributen)
     * @param  string|null  $valueSelectionId  ValueListEntry-UUID bei Selection
     * @param  string|null  $unit          Einheit (technical_name oder Abkürzung) bei Einheiten-Attributen
     * @return array<string, mixed>
     */
    public function update(
        string $productRef,
        string $attributeRef,
        mixed $value,
        ?string $language = null,
        ?string $valueSelectionId = null,
        ?string $unit = null,
    ): array {
        $product   = $this->resolveProduct($productRef);
        $attribute = $this->resolveAttribute($attributeRef);

        if (in_array($attribute->data_type, ['Composite', 'Collection'], true)) {
            throw new \RuntimeException(
                "Attribut \"{$attribute->technical_name}\" ist vom Typ {$attribute->data_type} und kann hier nicht gesetzt werden."
            );
        }

        if ($attribute->is_readonly ?? false) {
            throw new \RuntimeException("Attribut \"{$attribute->technical_name}\" ist schreibgeschützt.");
        }

        // translatable-Regel wie im REST-Controller: übersetzbar → Sprache Pflicht,
        // nicht übersetzbar → Sprache muss null sein.
        if ($attribute->is_translatable) {
            $language = $language ?: 'de';
        } else {
            $language = null;
        }

        [$unitId, $unitLabel] = $this->resolveUnit($attribute, $unit);

        $columns = $this->resolveValueColumns($attribute, $value, $valueSelectionId);

        $pav = ProductAttributeValue::updateOrCreate(
            [
                'product_id'          => $product->id,
                'attribute_id'        => $attribute->id,
                'language'            => $language,
                'multiplied_index'    => 0,
                'output_hierarchy_id' => null,
            ],
            array_merge($columns, [
                'unit_id'                   => $unitId,
                'is_inherited'              => false,
                'inherited_from_node_id'    => null,
                'inherited_from_product_id' => null,
            ]),
        );

        return [
            'ok'        => true,
            // "created" = neuer Datensatz angelegt (z.B. Sprach-/Scope-Mismatch),
            // "updated" = bestehender Wert geändert. Diagnostisch für den Debug.
            'action'    => $pav->wasRecentlyCreated ? 'created' : 'updated',
            'value_id'  => $pav->id,
            'product'   => ['id' => $product->id, 'sku' => $product->sku, 'name' => $product->name],
            'attribute' => [
                'id'              => $attribute->id,
                'technical_name'  => $attribute->technical_name,
                'data_type'       => $attribute->data_type,
                'is_translatable' => (bool) $attribute->is_translatable,
            ],
            'language'  => $language,
            'value'     => $value,
            'unit'      => $unitLabel,
        ];
    }

    /**
     * Löst die Einheit auf und erzwingt Klarheit:
     *  - Attribut ohne Einheitengruppe: "unit" darf nicht gesetzt sein.
     *  - Attribut mit Einheitengruppe: gegebene Einheit muss zur Gruppe gehören;
     *    ohne Angabe wird die Standardeinheit verwendet, sonst Fehler.
     *
     * @return array{0: ?string, 1: ?string}  [unit_id, label]
     */
    private function resolveUnit(Attribute $attribute, ?string $unit): array
    {
        if (empty($attribute->unit_group_id)) {
            if ($unit !== null && $unit !== '') {
                throw new \RuntimeException(
                    "Attribut \"{$attribute->technical_name}\" hat keine Einheit — Parameter \"unit\" darf nicht gesetzt werden."
                );
            }

            return [null, null];
        }

        if ($unit !== null && $unit !== '') {
            $resolved = Unit::where('unit_group_id', $attribute->unit_group_id)
                ->where(fn ($q) => $q->where('technical_name', $unit)->orWhere('abbreviation', $unit))
                ->first();

            if (!$resolved) {
                $valid = Unit::where('unit_group_id', $attribute->unit_group_id)
                    ->get(['technical_name', 'abbreviation'])
                    ->map(fn ($u) => $u->abbreviation ?: $u->technical_name)
                    ->filter()->implode(', ');

                throw new \RuntimeException(
                    "Einheit \"{$unit}\" ist für Attribut \"{$attribute->technical_name}\" nicht gültig. Erlaubte Einheiten: {$valid}."
                );
            }

            return [$resolved->id, $resolved->abbreviation ?: $resolved->technical_name];
        }

        // Keine Einheit angegeben → Standardeinheit verwenden, sonst Klärung verlangen.
        if (!empty($attribute->default_unit_id)) {
            $default = Unit::find($attribute->default_unit_id);

            return [$attribute->default_unit_id, $default?->abbreviation ?: $default?->technical_name];
        }

        throw new \RuntimeException(
            "Attribut \"{$attribute->technical_name}\" erwartet eine Einheit, aber es wurde keine angegeben und es gibt keine Standardeinheit. Bitte \"unit\" setzen."
        );
    }

    private function resolveProduct(string $ref): Product
    {
        $product = Product::where('id', $ref)->orWhere('sku', $ref)->first();

        if (!$product) {
            throw new \RuntimeException("Produkt \"{$ref}\" nicht gefunden (weder UUID noch SKU).");
        }

        return $product;
    }

    private function resolveAttribute(string $ref): Attribute
    {
        $attribute = Attribute::where('id', $ref)->orWhere('technical_name', $ref)->first();

        if (!$attribute) {
            throw new \RuntimeException("Attribut \"{$ref}\" nicht gefunden (weder UUID noch technical_name).");
        }

        return $attribute;
    }

    /**
     * Wert auf die passende value_*-Spalte mappen (data_type-abhängig).
     *
     * @return array<string, mixed>
     */
    private function resolveValueColumns(Attribute $attribute, mixed $value, ?string $valueSelectionId): array
    {
        $columns = [
            'value_string'       => null,
            'value_number'       => null,
            'value_date'         => null,
            'value_flag'         => null,
            'value_selection_id' => null,
        ];

        return match ($attribute->data_type) {
            'Number', 'Float' => array_merge($columns, ['value_number' => $value !== null ? (float) $value : null]),
            'Date'            => array_merge($columns, ['value_date' => $value]),
            'Flag'            => array_merge($columns, ['value_flag' => (bool) $value]),
            'Selection', 'Dictionary' => array_merge($columns, [
                'value_string'       => $value !== null ? (string) $value : null,
                'value_selection_id' => $valueSelectionId,
            ]),
            'MultiSelection'  => array_merge($columns, [
                'value_string' => is_array($value) ? (string) json_encode($value) : (string) $value,
            ]),
            default           => array_merge($columns, ['value_string' => $value !== null ? (string) $value : null]),
        };
    }
}
