<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\Log;

/**
 * ComputedAttributeService – Berechnet und persistiert Werte
 * von Composite-Attributen mit composite_expression.
 *
 * Wird aufgerufen wenn sich Kind-Attributwerte ändern.
 * Speichert das Ergebnis als value_number in product_attribute_values.
 *
 * Unterstützt:
 * - Vermehrbare Composites: Berechnung pro multiplied_index
 * - Geschachtelte Composites: Kaskade Sub-Composite → Root-Composite (max. Tiefe 2)
 */
class ComputedAttributeService
{
    public function __construct(
        private readonly CompositeExpressionEvaluator $evaluator,
    ) {}

    /**
     * Findet alle Composite-Attribute mit Expression, die von den
     * geänderten Attribut-IDs abhängen, und berechnet deren Werte neu.
     * Unterstützt 2-Ebenen-Kaskade (Kind → Parent → Grandparent).
     *
     * @param string   $productId          Produkt-ID
     * @param string[] $changedAttributeIds IDs der geänderten Attribute
     */
    public function recomputeForChangedAttributes(string $productId, array $changedAttributeIds): void
    {
        $product = Product::find($productId);
        if (!$product) {
            return;
        }

        // Ebene 1: Finde direkte Composite-Eltern der geänderten Attribute
        $parentIds = Attribute::whereIn('id', $changedAttributeIds)
            ->whereNotNull('parent_attribute_id')
            ->pluck('parent_attribute_id')
            ->unique()
            ->all();

        if (empty($parentIds)) {
            return;
        }

        // Lade Composite-Attribute mit Expression (Ebene 1)
        $composites = Attribute::whereIn('id', $parentIds)
            ->where('data_type', 'Composite')
            ->whereNotNull('composite_expression')
            ->get();

        foreach ($composites as $composite) {
            $this->computeAndStore($product, $composite);
        }

        // Ebene 2: Falls die berechneten Composites selbst Kinder eines Root-Composites sind
        $grandparentIds = Attribute::whereIn('id', $parentIds)
            ->whereNotNull('parent_attribute_id')
            ->pluck('parent_attribute_id')
            ->unique()
            ->all();

        if (!empty($grandparentIds)) {
            $rootComposites = Attribute::whereIn('id', $grandparentIds)
                ->where('data_type', 'Composite')
                ->whereNotNull('composite_expression')
                ->get();

            foreach ($rootComposites as $root) {
                $this->computeAndStore($product, $root);
            }
        }
    }

    /**
     * Berechnet und speichert den Wert eines einzelnen Composite-Attributs.
     * Unterstützt vermehrbare Composites: berechnet pro multiplied_index.
     */
    public function computeAndStore(Product $product, Attribute $composite): void
    {
        if (!$composite->composite_expression) {
            return;
        }

        $children = $composite->childAttributes()->orderBy('position')->get();

        if ($children->isEmpty()) {
            return;
        }

        if ($composite->is_multipliable) {
            $this->computeMultiplied($product, $composite, $children);
        } else {
            $this->computeSingle($product, $composite, $children, 0);
        }
    }

    /**
     * Berechnet Expression für einen einzelnen multiplied_index.
     */
    private function computeSingle(Product $product, Attribute $composite, $children, int $multipliedIndex): void
    {
        $values = [];
        foreach ($children as $index => $child) {
            $pav = ProductAttributeValue::where('product_id', $product->id)
                ->where('attribute_id', $child->id)
                ->where('is_inherited', false)
                ->whereNull('language')
                ->where('multiplied_index', $multipliedIndex)
                ->first();

            $values[$index] = $pav?->value_number !== null ? (float) $pav->value_number : null;
        }

        $result = $this->evaluator->evaluate($composite->composite_expression, $values);

        if ($result === null) {
            ProductAttributeValue::where('product_id', $product->id)
                ->where('attribute_id', $composite->id)
                ->whereNull('language')
                ->where('multiplied_index', $multipliedIndex)
                ->delete();

            return;
        }

        ProductAttributeValue::updateOrCreate(
            [
                'product_id' => $product->id,
                'attribute_id' => $composite->id,
                'language' => null,
                'multiplied_index' => $multipliedIndex,
            ],
            [
                'value_string' => null,
                'value_number' => $result,
                'value_date' => null,
                'value_flag' => null,
                'value_selection_id' => null,
                'unit_id' => null,
                'comparison_operator_id' => null,
                'is_inherited' => false,
                'inherited_from_node_id' => null,
                'inherited_from_product_id' => null,
            ]
        );

        Log::debug('ComputedAttributeService: Value computed', [
            'product_id' => $product->id,
            'composite_id' => $composite->id,
            'multiplied_index' => $multipliedIndex,
            'expression' => $composite->composite_expression,
            'result' => $result,
        ]);
    }

    /**
     * Berechnet Expression für alle multiplied_index Instanzen eines vermehrbaren Composites.
     */
    private function computeMultiplied(Product $product, Attribute $composite, $children): void
    {
        // Finde alle existierenden multiplied_index Werte der Kind-Attribute
        $childIds = $children->pluck('id')->all();
        $allIndices = ProductAttributeValue::where('product_id', $product->id)
            ->whereIn('attribute_id', $childIds)
            ->whereNull('language')
            ->distinct()
            ->pluck('multiplied_index')
            ->sort()
            ->values()
            ->all();

        if (empty($allIndices)) {
            // Alle berechneten Werte des Composites löschen
            ProductAttributeValue::where('product_id', $product->id)
                ->where('attribute_id', $composite->id)
                ->whereNull('language')
                ->delete();
            return;
        }

        foreach ($allIndices as $idx) {
            $this->computeSingle($product, $composite, $children, $idx);
        }

        // Berechnete Werte für nicht mehr existierende Indizes löschen
        ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $composite->id)
            ->whereNull('language')
            ->whereNotIn('multiplied_index', $allIndices)
            ->delete();
    }
}
