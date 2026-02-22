<?php

declare(strict_types=1);

namespace App\Services\Preview;

use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\Inheritance\HierarchyInheritanceService;
use Illuminate\Support\Collection;

class ProductPreviewService
{
    public function __construct(
        protected HierarchyInheritanceService $hierarchyService,
    ) {}

    /**
     * Build a complete, structured preview of a product.
     *
     * @return array{stammdaten: array, attribute_sections: array, relations: array, prices: array, media: array, variants: array}
     */
    public function buildPreviewData(Product $product, string $lang): array
    {
        $product->load([
            'productType',
            'masterHierarchyNode',
            'attributeValues.attribute',
            'attributeValues.valueListEntry',
            'attributeValues.unit',
            'prices.priceType',
            'relations.relationType',
            'relations.targetProduct',
            'media',
            'mediaAssignments.usageType',
            'variants',
            'createdBy',
            'updatedBy',
        ]);

        return [
            'stammdaten' => $this->buildStammdaten($product, $lang),
            'attribute_sections' => $this->buildAttributeSections($product, $lang),
            'relations' => $this->buildRelations($product, $lang),
            'prices' => $this->buildPrices($product, $lang),
            'media' => $this->buildMedia($product, $lang),
            'variants' => $this->buildVariants($product, $lang),
        ];
    }

    private function buildStammdaten(Product $product, string $lang): array
    {
        $breadcrumb = $this->buildBreadcrumb($product, $lang);

        $productTypeName = null;
        if ($product->productType) {
            $productTypeName = $lang === 'en' && $product->productType->name_en
                ? $product->productType->name_en
                : $product->productType->name_de;
        }

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'ean' => $product->ean,
            'name' => $product->name,
            'status' => $product->status,
            'product_type' => $product->productType ? [
                'id' => $product->productType->id,
                'technical_name' => $product->productType->technical_name,
                'name' => $productTypeName,
            ] : null,
            'category_breadcrumb' => $breadcrumb,
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
            'created_by' => $product->createdBy?->name ?? $product->created_by,
            'updated_by' => $product->updatedBy?->name ?? $product->updated_by,
        ];
    }

    /**
     * Build attribute sections grouped by collection_name from hierarchy inheritance.
     */
    private function buildAttributeSections(Product $product, string $lang): array
    {
        $effectiveAttributes = $this->hierarchyService->getProductAttributes($product);

        // Index existing values by attribute_id (+ language)
        $existingValues = $product->attributeValues
            ->groupBy('attribute_id');

        $sections = [];
        $sectionMap = [];

        foreach ($effectiveAttributes as $assignment) {
            // Skip internal attributes in preview output
            if (!empty($assignment->is_internal)) {
                continue;
            }

            $sectionName = $assignment->collection_name ?? ($lang === 'en' ? 'General' : 'Allgemein');
            $sectionSort = $assignment->collection_sort ?? 0;

            if (!isset($sectionMap[$sectionName])) {
                $sectionMap[$sectionName] = count($sections);
                $sections[] = [
                    'section_name' => $sectionName,
                    'section_sort' => $sectionSort,
                    'attributes' => [],
                ];
            }

            $sectionIndex = $sectionMap[$sectionName];

            $label = $lang === 'en' && $assignment->attribute_name_en
                ? $assignment->attribute_name_en
                : $assignment->attribute_name_de;

            // Get attribute values for this attribute
            $attrValues = $existingValues->get($assignment->attribute_id, collect());

            if ($attrValues->isEmpty()) {
                // Attribute exists in hierarchy but has no value
                $sections[$sectionIndex]['attributes'][] = [
                    'attribute_id' => $assignment->attribute_id,
                    'technical_name' => $assignment->attribute_technical_name,
                    'label' => $label,
                    'value' => null,
                    'display_value' => null,
                    'unit' => null,
                    'data_type' => $assignment->data_type,
                    'is_mandatory' => (bool) $assignment->is_mandatory,
                    'language' => null,
                ];
            } else {
                foreach ($attrValues as $attrValue) {
                    $displayValue = $this->resolveDisplayValue($attrValue, $lang);
                    $unit = $attrValue->unit?->abbreviation;

                    $sections[$sectionIndex]['attributes'][] = [
                        'attribute_id' => $assignment->attribute_id,
                        'technical_name' => $assignment->attribute_technical_name,
                        'label' => $label,
                        'value' => $this->resolveRawValue($attrValue),
                        'display_value' => $displayValue,
                        'unit' => $unit,
                        'data_type' => $assignment->data_type,
                        'is_mandatory' => (bool) $assignment->is_mandatory,
                        'language' => $attrValue->language,
                    ];
                }
            }
        }

        // Sort sections by section_sort
        usort($sections, fn ($a, $b) => $a['section_sort'] <=> $b['section_sort']);

        return $sections;
    }

    private function buildRelations(Product $product, string $lang): array
    {
        return $product->relations->map(function ($relation) use ($lang) {
            $typeName = null;
            if ($relation->relationType) {
                $typeName = $lang === 'en' && $relation->relationType->name_en
                    ? $relation->relationType->name_en
                    : $relation->relationType->name_de;
            }

            return [
                'id' => $relation->id,
                'relation_type' => $typeName,
                'relation_type_technical_name' => $relation->relationType?->technical_name,
                'target_product' => $relation->targetProduct ? [
                    'id' => $relation->targetProduct->id,
                    'sku' => $relation->targetProduct->sku,
                    'name' => $relation->targetProduct->name,
                ] : null,
                'sort_order' => $relation->sort_order,
            ];
        })->values()->toArray();
    }

    private function buildPrices(Product $product, string $lang): array
    {
        return $product->prices->map(function ($price) use ($lang) {
            $typeName = null;
            if ($price->priceType) {
                $typeName = $lang === 'en' && $price->priceType->name_en
                    ? $price->priceType->name_en
                    : $price->priceType->name_de;
            }

            return [
                'id' => $price->id,
                'price_type' => $typeName,
                'price_type_technical_name' => $price->priceType?->technical_name,
                'amount' => $price->amount,
                'currency' => $price->currency,
                'valid_from' => $price->valid_from?->format('Y-m-d'),
                'valid_to' => $price->valid_to?->format('Y-m-d'),
                'country' => $price->country,
                'scale_from' => $price->scale_from,
                'scale_to' => $price->scale_to,
            ];
        })->values()->toArray();
    }

    private function buildMedia(Product $product, string $lang): array
    {
        return $product->mediaAssignments->map(function ($assignment) use ($lang) {
            $media = $assignment->media;
            if (!$media) {
                return null;
            }

            return [
                'id' => $media->id,
                'url' => '/api/v1/media/file/' . $media->file_name,
                'file_name' => $media->file_name,
                'alt' => $lang === 'en' && $media->alt_text_en ? $media->alt_text_en : ($media->alt_text_de ?? null),
                'is_primary' => (bool) $assignment->is_primary,
                'usage_type' => $assignment->usageType ? [
                    'id' => $assignment->usageType->id,
                    'technical_name' => $assignment->usageType->technical_name,
                    'name_de' => $assignment->usageType->name_de,
                    'name_en' => $assignment->usageType->name_en,
                ] : null,
                'media_type' => $media->media_type,
                'sort_order' => $assignment->sort_order ?? 0,
            ];
        })->filter()->values()->toArray();
    }

    private function buildVariants(Product $product, string $lang): array
    {
        if ($product->variants->isEmpty()) {
            return [];
        }

        // Get variant attributes (is_variant_attribute = true, not internal)
        $variantAttributes = Attribute::where('is_variant_attribute', true)
            ->where('is_internal', false)
            ->where('status', 'active')
            ->orderBy('position')
            ->get();

        // Pre-load attribute values for all variants
        $variantIds = $product->variants->pluck('id');
        $allVariantValues = ProductAttributeValue::whereIn('product_id', $variantIds)
            ->whereIn('attribute_id', $variantAttributes->pluck('id'))
            ->with(['attribute', 'valueListEntry', 'unit'])
            ->get()
            ->groupBy('product_id');

        return $product->variants->map(function ($variant) use ($variantAttributes, $allVariantValues, $lang) {
            $variantAttrValues = $allVariantValues->get($variant->id, collect());

            $variantAttrsOutput = $variantAttributes->map(function ($attr) use ($variantAttrValues, $lang) {
                $attrValue = $variantAttrValues->firstWhere('attribute_id', $attr->id);
                $label = $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de;

                return [
                    'attribute_id' => $attr->id,
                    'technical_name' => $attr->technical_name,
                    'label' => $label,
                    'value' => $attrValue ? $this->resolveDisplayValue($attrValue, $lang) : null,
                    'unit' => $attrValue?->unit?->abbreviation,
                    'data_type' => $attr->data_type,
                ];
            })->values()->toArray();

            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'ean' => $variant->ean,
                'name' => $variant->name,
                'status' => $variant->status,
                'variant_attributes' => $variantAttrsOutput,
            ];
        })->values()->toArray();
    }

    /**
     * Build breadcrumb from materialized path (pattern from CatalogController).
     */
    private function buildBreadcrumb(Product $product, string $lang): array
    {
        $breadcrumb = [];

        if (!$product->masterHierarchyNode) {
            return $breadcrumb;
        }

        $node = $product->masterHierarchyNode;
        $ancestors = HierarchyNode::ancestorsOf($node->path)
            ->orderBy('depth')
            ->get();

        foreach ($ancestors as $ancestor) {
            $breadcrumb[] = [
                'id' => $ancestor->id,
                'name' => $lang === 'en' && $ancestor->name_en ? $ancestor->name_en : $ancestor->name_de,
            ];
        }

        // Add the current node itself
        $breadcrumb[] = [
            'id' => $node->id,
            'name' => $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de,
        ];

        return $breadcrumb;
    }

    /**
     * Resolve attribute display value (pattern from CatalogProductDetailResource).
     */
    private function resolveDisplayValue(ProductAttributeValue $attrValue, string $lang): ?string
    {
        $attr = $attrValue->attribute;
        if (!$attr) {
            return null;
        }

        return match ($attr->data_type) {
            'String' => $attrValue->value_string,
            'Number', 'Float' => $attrValue->value_number !== null
                ? rtrim(rtrim((string) $attrValue->value_number, '0'), '.')
                : null,
            'Date' => $attrValue->value_date?->format('Y-m-d'),
            'Flag' => $attrValue->value_flag !== null
                ? ($attrValue->value_flag ? ($lang === 'en' ? 'Yes' : 'Ja') : ($lang === 'en' ? 'No' : 'Nein'))
                : null,
            'Selection', 'Dictionary' => $this->resolveSelectionValue($attrValue, $lang),
            default => $attrValue->value_string,
        };
    }

    private function resolveSelectionValue(ProductAttributeValue $attrValue, string $lang): ?string
    {
        $entry = $attrValue->valueListEntry;
        if (!$entry) {
            return null;
        }

        return $lang === 'en' && $entry->display_value_en
            ? $entry->display_value_en
            : $entry->display_value_de;
    }

    /**
     * Get the raw value from an attribute value record.
     */
    private function resolveRawValue(ProductAttributeValue $attrValue): mixed
    {
        return $attrValue->value_string
            ?? $attrValue->value_number
            ?? $attrValue->value_date
            ?? $attrValue->value_flag
            ?? $attrValue->value_selection_id;
    }
}
