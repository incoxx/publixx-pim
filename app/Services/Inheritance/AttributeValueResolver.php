<?php

declare(strict_types=1);

namespace App\Services\Inheritance;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\VariantInheritanceRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AttributeValueResolver
{
    /**
     * Cache TTL in seconds (30 minutes).
     */
    private const CACHE_TTL = 1800;

    public function __construct(
        private readonly HierarchyInheritanceService $hierarchyService,
        private readonly VariantInheritanceService $variantService,
    ) {}

    /**
     * CORE METHOD: Resolve a single attribute value for a product.
     *
     * Resolution cascade (4 stages):
     * 1. Own value (product_attribute_values WHERE product_id = X)
     * 2. Variant inheritance (from parent_product_id, if mode = 'inherit')
     * 3. Hierarchy default (if defined on hierarchy node)
     * 4. NULL (no value found)
     *
     * @param Product   $product   The product (or variant) to resolve for
     * @param Attribute $attribute The attribute to resolve
     * @param string|null $language  Language code (null = language-independent)
     *
     * @return ResolvedValue|null Resolved value with metadata, or null
     */
    public function resolve(
        Product $product,
        Attribute $attribute,
        ?string $language = null,
    ): ?ResolvedValue {
        $cacheKey = "resolved_value:{$product->id}:{$attribute->id}:{$language}";

        return Cache::tags(["product:{$product->id}"])->remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->doResolve($product, $attribute, $language)
        );
    }

    /**
     * Resolve all attribute values for a product (for detail view).
     *
     * Loads all attributes assigned via hierarchy, then resolves each value.
     * Each result is marked with inheritance metadata (is_inherited, inherited_from_*).
     *
     * @return Collection<int, ResolvedValue>
     */
    public function resolveAll(Product $product, ?string $language = null): Collection
    {
        $effectiveProduct = $product;

        // For variants, use parent's hierarchy if variant has no own hierarchy
        if ($product->parent_product_id && !$product->master_hierarchy_node_id) {
            $effectiveProduct = $product->parentProduct ?? $product;
        }

        $hierarchyAttributes = $this->hierarchyService->getProductAttributes($effectiveProduct);

        if ($hierarchyAttributes->isEmpty()) {
            return collect();
        }

        $results = collect();

        foreach ($hierarchyAttributes as $assignment) {
            $attribute = Attribute::find($assignment->attribute_id);

            if (!$attribute) {
                continue;
            }

            $resolved = $this->resolve($product, $attribute, $language);

            $results->push($resolved ?? new ResolvedValue(
                attributeId: $attribute->id,
                attributeTechnicalName: $attribute->technical_name,
                value: null,
                source: 'none',
                inheritedFromProductId: null,
                inheritedFromNodeId: null,
                collectionName: $assignment->collection_name,
                collectionSort: $assignment->collection_sort,
                attributeSort: $assignment->attribute_sort,
                accessProduct: $assignment->access_product ?? 'editable',
                accessVariant: $assignment->access_variant ?? 'editable',
            ));
        }

        return $results->sortBy([
            ['collectionSort', 'asc'],
            ['attributeSort', 'asc'],
        ])->values();
    }

    /**
     * Resolve a value without caching (internal).
     */
    private function doResolve(
        Product $product,
        Attribute $attribute,
        ?string $language,
    ): ?ResolvedValue {
        // Stage 1: Own value
        $ownValue = $this->findOwnValue($product, $attribute, $language);
        if ($ownValue) {
            return ResolvedValue::fromProductAttributeValue(
                pav: $ownValue,
                source: 'own',
            );
        }

        // Stage 2: Variant inheritance (only for variants)
        if ($product->parent_product_id) {
            $inheritedValue = $this->resolveFromParent($product, $attribute, $language);
            if ($inheritedValue) {
                return $inheritedValue;
            }
        }

        // Stage 3: Hierarchy default (from hierarchy node attribute assignment)
        // Note: Hierarchy defaults are stored as product_attribute_values with
        // is_inherited = true on the node level. This stage handles explicit defaults
        // defined on hierarchy nodes.
        $hierarchyDefault = $this->findHierarchyDefault($product, $attribute, $language);
        if ($hierarchyDefault) {
            return $hierarchyDefault;
        }

        // Stage 4: NULL (no value found)
        return null;
    }

    /**
     * Find the product's own value for an attribute.
     */
    private function findOwnValue(
        Product $product,
        Attribute $attribute,
        ?string $language,
    ): ?ProductAttributeValue {
        $query = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attribute->id)
            ->where('is_inherited', false);

        if ($attribute->is_translatable && $language !== null) {
            // For translatable attributes: look for exact language match
            $query->where('language', $language);
        } else {
            // For non-translatable: language should be null
            $query->whereNull('language');
        }

        return $query->first();
    }

    /**
     * Resolve value from parent product (variant inheritance).
     * Checks variant_inheritance_rules to determine if value should be inherited.
     */
    private function resolveFromParent(
        Product $variant,
        Attribute $attribute,
        ?string $language,
    ): ?ResolvedValue {
        $mode = $this->variantService->getMode($variant, $attribute->id);

        if ($mode === 'override') {
            // Variant explicitly overrides this attribute — don't look at parent
            return null;
        }

        // Mode is 'inherit' (default) — resolve from parent recursively
        $parentProduct = $variant->parentProduct;
        if (!$parentProduct) {
            return null;
        }

        $parentResolved = $this->doResolve($parentProduct, $attribute, $language);
        if (!$parentResolved) {
            return null;
        }

        // Re-wrap with variant inheritance metadata
        return new ResolvedValue(
            attributeId: $attribute->id,
            attributeTechnicalName: $attribute->technical_name,
            value: $parentResolved->value,
            source: 'variant_inheritance',
            inheritedFromProductId: $parentProduct->id,
            inheritedFromNodeId: $parentResolved->inheritedFromNodeId,
            collectionName: $parentResolved->collectionName,
            collectionSort: $parentResolved->collectionSort,
            attributeSort: $parentResolved->attributeSort,
            accessProduct: $parentResolved->accessProduct,
            accessVariant: $parentResolved->accessVariant,
            productAttributeValue: $parentResolved->productAttributeValue,
        );
    }

    /**
     * Find a hierarchy default value for an attribute.
     *
     * Looks for default values defined on hierarchy nodes (from the product's
     * master hierarchy node up to the root).
     */
    private function findHierarchyDefault(
        Product $product,
        Attribute $attribute,
        ?string $language,
    ): ?ResolvedValue {
        // Determine the effective hierarchy node
        $nodeId = $product->master_hierarchy_node_id;

        // For variants without own hierarchy node, use parent's
        if (!$nodeId && $product->parent_product_id && $product->parentProduct) {
            $nodeId = $product->parentProduct->master_hierarchy_node_id;
        }

        if (!$nodeId) {
            return null;
        }

        // Look for inherited values (is_inherited = true) on the product
        // These are pre-computed hierarchy defaults
        $hierarchyValue = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attribute->id)
            ->where('is_inherited', true)
            ->whereNotNull('inherited_from_node_id')
            ->when($attribute->is_translatable && $language !== null, function ($q) use ($language) {
                $q->where('language', $language);
            })
            ->when(!$attribute->is_translatable || $language === null, function ($q) {
                $q->whereNull('language');
            })
            ->first();

        if ($hierarchyValue) {
            return ResolvedValue::fromProductAttributeValue(
                pav: $hierarchyValue,
                source: 'hierarchy_inheritance',
                inheritedFromNodeId: $hierarchyValue->inherited_from_node_id,
            );
        }

        return null;
    }
}
