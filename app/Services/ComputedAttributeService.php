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
 */
class ComputedAttributeService
{
    public function __construct(
        private readonly CompositeExpressionEvaluator $evaluator,
    ) {}

    /**
     * Findet alle Composite-Attribute mit Expression, die von den
     * geänderten Attribut-IDs abhängen, und berechnet deren Werte neu.
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

        // Finde alle Composite-Eltern der geänderten Attribute
        $parentIds = Attribute::whereIn('id', $changedAttributeIds)
            ->whereNotNull('parent_attribute_id')
            ->pluck('parent_attribute_id')
            ->unique()
            ->all();

        if (empty($parentIds)) {
            return;
        }

        // Lade Composite-Attribute mit Expression
        $composites = Attribute::whereIn('id', $parentIds)
            ->where('data_type', 'Composite')
            ->whereNotNull('composite_expression')
            ->get();

        foreach ($composites as $composite) {
            $this->computeAndStore($product, $composite);
        }
    }

    /**
     * Berechnet und speichert den Wert eines einzelnen Composite-Attributs.
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

        // Kind-Werte laden (nur value_number, da Expression numerisch ist)
        $values = [];
        foreach ($children as $index => $child) {
            $pav = ProductAttributeValue::where('product_id', $product->id)
                ->where('attribute_id', $child->id)
                ->where('is_inherited', false)
                ->whereNull('language')
                ->where('multiplied_index', 0)
                ->first();

            $values[$index] = $pav?->value_number !== null ? (float) $pav->value_number : null;
        }

        // Expression evaluieren
        $result = $this->evaluator->evaluate($composite->composite_expression, $values);

        if ($result === null) {
            // Ergebnis nicht berechenbar → vorhandenen berechneten Wert löschen
            ProductAttributeValue::where('product_id', $product->id)
                ->where('attribute_id', $composite->id)
                ->whereNull('language')
                ->where('multiplied_index', 0)
                ->delete();

            return;
        }

        // Berechneten Wert persistieren
        ProductAttributeValue::updateOrCreate(
            [
                'product_id' => $product->id,
                'attribute_id' => $composite->id,
                'language' => null,
                'multiplied_index' => 0,
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
            'expression' => $composite->composite_expression,
            'result' => $result,
        ]);
    }
}
