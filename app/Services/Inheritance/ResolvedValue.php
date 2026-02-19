<?php

declare(strict_types=1);

namespace App\Services\Inheritance;

use App\Models\ProductAttributeValue;

/**
 * Data Transfer Object representing a resolved attribute value
 * with full inheritance metadata.
 */
class ResolvedValue
{
    public function __construct(
        public readonly string $attributeId,
        public readonly string $attributeTechnicalName,
        public readonly mixed $value,
        /** Source: 'own', 'variant_inheritance', 'hierarchy_inheritance', 'none' */
        public readonly string $source,
        public readonly ?string $inheritedFromProductId = null,
        public readonly ?string $inheritedFromNodeId = null,
        public readonly ?string $collectionName = null,
        public readonly ?int $collectionSort = null,
        public readonly ?int $attributeSort = null,
        public readonly string $accessProduct = 'editable',
        public readonly string $accessVariant = 'editable',
        public readonly ?ProductAttributeValue $productAttributeValue = null,
    ) {}

    /**
     * Create from a ProductAttributeValue model instance.
     */
    public static function fromProductAttributeValue(
        ProductAttributeValue $pav,
        string $source,
        ?string $inheritedFromProductId = null,
        ?string $inheritedFromNodeId = null,
    ): self {
        return new self(
            attributeId: $pav->attribute_id,
            attributeTechnicalName: $pav->attribute?->technical_name ?? '',
            value: self::extractValue($pav),
            source: $source,
            inheritedFromProductId: $inheritedFromProductId ?? $pav->inherited_from_product_id,
            inheritedFromNodeId: $inheritedFromNodeId ?? $pav->inherited_from_node_id,
            productAttributeValue: $pav,
        );
    }

    /**
     * Whether this value is inherited (not the product's own value).
     */
    public function isInherited(): bool
    {
        return $this->source !== 'own' && $this->source !== 'none';
    }

    /**
     * Whether a value was found.
     */
    public function hasValue(): bool
    {
        return $this->source !== 'none' && $this->value !== null;
    }

    /**
     * Convert to array representation (for API responses).
     */
    public function toArray(): array
    {
        return [
            'attribute_id' => $this->attributeId,
            'attribute_technical_name' => $this->attributeTechnicalName,
            'value' => $this->value,
            'source' => $this->source,
            'is_inherited' => $this->isInherited(),
            'inherited_from_product_id' => $this->inheritedFromProductId,
            'inherited_from_node_id' => $this->inheritedFromNodeId,
            'collection_name' => $this->collectionName,
            'collection_sort' => $this->collectionSort,
            'attribute_sort' => $this->attributeSort,
            'access_product' => $this->accessProduct,
            'access_variant' => $this->accessVariant,
        ];
    }

    /**
     * Extract the typed value from a ProductAttributeValue.
     */
    private static function extractValue(ProductAttributeValue $pav): mixed
    {
        return $pav->value_string
            ?? $pav->value_number
            ?? $pav->value_date
            ?? $pav->value_flag
            ?? $pav->value_selection_id
            ?? null;
    }
}
