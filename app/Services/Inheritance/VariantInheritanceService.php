<?php

declare(strict_types=1);

namespace App\Services\Inheritance;

use App\Events\AttributeValuesChanged;
use App\Models\Product;
use App\Models\VariantInheritanceRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VariantInheritanceService
{
    /**
     * Default inheritance mode when no explicit rule exists.
     */
    public const DEFAULT_MODE = 'inherit';

    /**
     * Get all inheritance rules for a variant product.
     *
     * Returns a collection of rules. For attributes without an explicit rule,
     * the default mode is 'inherit' (value comes from parent product).
     *
     * @return Collection<int, VariantInheritanceRule>
     */
    public function getRules(Product $variant): Collection
    {
        if (!$variant->parent_product_id) {
            return collect();
        }

        return VariantInheritanceRule::where('product_id', $variant->id)->get();
    }

    /**
     * Get the inheritance mode for a specific attribute on a variant.
     *
     * @return string 'inherit' or 'override'
     */
    public function getMode(Product $variant, string $attributeId): string
    {
        if (!$variant->parent_product_id) {
            return 'override'; // Non-variants always use own values
        }

        $rule = VariantInheritanceRule::where('product_id', $variant->id)
            ->where('attribute_id', $attributeId)
            ->first();

        return $rule ? $rule->inheritance_mode : self::DEFAULT_MODE;
    }

    /**
     * Set inheritance rules in bulk for a variant.
     *
     * @param array<string, string> $rules Map of attribute_id => 'inherit'|'override'
     */
    public function setRules(Product $variant, array $rules): void
    {
        if (!$variant->parent_product_id) {
            throw new \InvalidArgumentException(
                "Product {$variant->id} is not a variant (no parent_product_id)."
            );
        }

        $changedAttributeIds = [];

        DB::transaction(function () use ($variant, $rules, &$changedAttributeIds) {
            foreach ($rules as $attributeId => $mode) {
                $this->validateMode($mode);

                $existingRule = VariantInheritanceRule::where('product_id', $variant->id)
                    ->where('attribute_id', $attributeId)
                    ->first();

                $previousMode = $existingRule
                    ? $existingRule->inheritance_mode
                    : self::DEFAULT_MODE;

                if ($previousMode !== $mode) {
                    $changedAttributeIds[] = $attributeId;
                }

                VariantInheritanceRule::updateOrCreate(
                    [
                        'product_id' => $variant->id,
                        'attribute_id' => $attributeId,
                    ],
                    [
                        'inheritance_mode' => $mode,
                    ]
                );
            }
        });

        // Invalidate cache and dispatch events for changed rules
        if (!empty($changedAttributeIds)) {
            $this->invalidateVariantCache($variant);
            event(new AttributeValuesChanged($variant->id, $changedAttributeIds));
        }
    }

    /**
     * Set a single rule for a variant attribute.
     */
    public function setRule(Product $variant, string $attributeId, string $mode): void
    {
        $this->setRules($variant, [$attributeId => $mode]);
    }

    /**
     * Reset a variant attribute to default inheritance (remove explicit rule).
     */
    public function resetRule(Product $variant, string $attributeId): void
    {
        $deleted = VariantInheritanceRule::where('product_id', $variant->id)
            ->where('attribute_id', $attributeId)
            ->delete();

        if ($deleted > 0) {
            $this->invalidateVariantCache($variant);
            event(new AttributeValuesChanged($variant->id, [$attributeId]));
        }
    }

    /**
     * Reset all rules for a variant (back to default: all inherit).
     */
    public function resetAllRules(Product $variant): void
    {
        $attributeIds = VariantInheritanceRule::where('product_id', $variant->id)
            ->pluck('attribute_id')
            ->toArray();

        VariantInheritanceRule::where('product_id', $variant->id)->delete();

        if (!empty($attributeIds)) {
            $this->invalidateVariantCache($variant);
            event(new AttributeValuesChanged($variant->id, $attributeIds));
        }
    }

    /**
     * Get all variant IDs for a parent product.
     *
     * @return Collection<int, string>
     */
    public function getVariantIds(Product $parentProduct): Collection
    {
        return Product::where('parent_product_id', $parentProduct->id)->pluck('id');
    }

    /**
     * Invalidate cache for a variant and dispatch change event.
     */
    public function invalidateVariantCache(Product $variant): void
    {
        Cache::tags(["product:{$variant->id}"])->flush();
    }

    /**
     * Invalidate cache for all variants of a parent product.
     * Called when a parent product's attribute values change.
     */
    public function invalidateAllVariantsCache(Product $parentProduct): void
    {
        $variantIds = $this->getVariantIds($parentProduct);

        foreach ($variantIds as $variantId) {
            Cache::tags(["product:{$variantId}"])->flush();
        }
    }

    /**
     * Validate that the given mode is valid.
     *
     * @throws \InvalidArgumentException
     */
    private function validateMode(string $mode): void
    {
        if (!in_array($mode, ['inherit', 'override'], true)) {
            throw new \InvalidArgumentException(
                "Invalid inheritance mode '{$mode}'. Must be 'inherit' or 'override'."
            );
        }
    }
}
