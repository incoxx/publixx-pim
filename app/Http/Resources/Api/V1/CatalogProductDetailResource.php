<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Attribute;
use App\Models\ProductAttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lang = $this->additional['lang'] ?? $request->query('lang', 'de');
        $breadcrumb = $this->additional['breadcrumb'] ?? [];
        $allowedAttributeIds = $this->additional['allowed_attribute_ids'] ?? null;

        $name = $this->resource->name;
        $description = $this->resource->searchIndex?->description_de;

        $media = $this->resource->media->map(function ($m) use ($lang) {
            return [
                'url' => url('api/v1/catalog/media/' . rawurlencode($m->file_name)),
                'alt' => $lang === 'en' && $m->alt_text_en ? $m->alt_text_en : $m->alt_text_de,
                'is_primary' => (bool) $m->pivot->is_primary,
                'media_type' => $m->media_type,
                'mime_type' => $m->mime_type,
                'file_name' => $m->file_name,
                'description' => $lang === 'en' && $m->description_en ? $m->description_en : ($m->description_de ?? ''),
            ];
        })->values();

        $prices = $this->resource->prices->map(function ($p) {
            return [
                'amount' => $p->amount,
                'currency' => $p->currency,
                'type_id' => $p->price_type_id,
            ];
        })->values();

        // Build attributes array from EAV values — exclude internal attributes
        $attributes = $this->resource->attributeValues
            ->sortBy(fn ($v) => $v->attribute?->position ?? 999)
            ->map(function (ProductAttributeValue $attrValue) use ($lang, $allowedAttributeIds) {
                $attr = $attrValue->attribute;
                if (!$attr || $attr->is_internal) {
                    return null;
                }

                // Filter by attribute view if configured — but always include link types
                $isLinkType = in_array($attr->data_type, ['Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink']);
                if ($allowedAttributeIds !== null && !in_array($attr->id, $allowedAttributeIds) && !$isLinkType) {
                    return null;
                }

                $label = $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de;
                $displayValue = $this->resolveAttributeDisplayValue($attrValue, $attr, $lang);

                // For link types: try to build link_data even if displayValue is empty
                $linkData = null;
                if ($isLinkType && $attrValue->value_string) {
                    $linkData = json_decode($attrValue->value_string, true);
                    if (!is_array($linkData) || empty($linkData['url'])) {
                        $linkData = null;
                    }
                    // Ensure displayValue has a fallback from link_data
                    if (($displayValue === null || $displayValue === '') && $linkData) {
                        $displayValue = $linkData['title'] ?? $linkData['url'] ?? null;
                    }
                }

                if ($displayValue === null || $displayValue === '') {
                    return null;
                }

                $unit = $attrValue->unit?->abbreviation;

                $entry = [
                    'attribute_id' => $attr->id,
                    'label' => $label,
                    'value' => $displayValue,
                    'unit' => $unit,
                    'data_type' => $attr->data_type,
                    'parent_attribute_id' => $attr->parent_attribute_id,
                    'composite_format' => $attr->data_type === 'Composite' ? $attr->composite_format : null,
                ];

                if ($linkData) {
                    $entry['link_data'] = $linkData;
                }

                return $entry;
            })
            ->filter()
            ->values();

        // Add composite parent entries (they have no own value but aggregate children)
        $attributes = $this->injectCompositeParents($attributes, $lang, $allowedAttributeIds);

        // Build variants with their variant attribute values
        $variants = $this->buildVariants($lang);

        // Build relations (filtered by configured types)
        $relations = $this->buildRelations($lang);

        $descriptionAttributes = $this->additional['description_attributes'] ?? [];

        return [
            'id' => $this->resource->id,
            'sku' => $this->resource->sku,
            'ean' => $this->resource->ean,
            'name' => $name,
            'description' => $description,
            'description_attributes' => $descriptionAttributes,
            'product_type' => $this->resource->searchIndex?->product_type,
            'category_breadcrumb' => $breadcrumb,
            'media' => $media,
            'prices' => $prices,
            'attributes' => $attributes,
            'variants' => $variants,
            'relations' => $relations,
        ];
    }

    private function buildVariants(string $lang): array
    {
        $product = $this->resource;

        if (!$product->relationLoaded('variants') || $product->variants->isEmpty()) {
            return [];
        }

        // Get variant attributes (non-internal, active)
        $variantAttributes = Attribute::where('is_variant_attribute', true)
            ->where('is_internal', false)
            ->where('status', 'active')
            ->orderBy('position')
            ->get();

        // Pre-load attribute values for all variants
        $variantIds = $product->variants->pluck('id');
        $allVariantValues = ProductAttributeValue::whereIn('product_id', $variantIds)
            ->whereIn('attribute_id', $variantAttributes->pluck('id'))
            ->with(['attribute', 'valueListEntry', 'dictionaryEntry', 'unit'])
            ->get()
            ->groupBy('product_id');

        return $product->variants->map(function ($variant) use ($variantAttributes, $allVariantValues, $lang) {
            $variantAttrValues = $allVariantValues->get($variant->id, collect());

            $variantAttrsOutput = $variantAttributes->map(function ($attr) use ($variantAttrValues, $lang) {
                $attrValue = $variantAttrValues->firstWhere('attribute_id', $attr->id);
                $label = $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de;

                return [
                    'label' => $label,
                    'value' => $attrValue ? $this->resolveAttributeDisplayValue($attrValue, $attr, $lang) : null,
                    'unit' => $attrValue?->unit?->abbreviation,
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

    private function buildRelations(string $lang): array
    {
        $product = $this->resource;

        if (!$product->relationLoaded('outgoingRelations') || $product->outgoingRelations->isEmpty()) {
            return [];
        }

        // Eager-load media for target products to build image URLs
        $targetIds = $product->outgoingRelations
            ->pluck('targetProduct')
            ->filter()
            ->pluck('id')
            ->unique()
            ->values()
            ->toArray();

        if (!empty($targetIds)) {
            $mediaByProduct = \App\Models\Product::whereIn('id', $targetIds)
                ->with('media')
                ->get()
                ->keyBy('id');
        } else {
            $mediaByProduct = collect();
        }

        return $product->outgoingRelations->map(function ($relation) use ($lang, $mediaByProduct) {
            $target = $relation->targetProduct;
            if (!$target || $target->status !== 'active') {
                return null;
            }

            $typeName = $relation->relationType
                ? ($lang === 'en' && $relation->relationType->name_en
                    ? $relation->relationType->name_en
                    : $relation->relationType->name_de)
                : null;

            // Find primary image for the related product
            $imageUrl = null;
            $targetWithMedia = $mediaByProduct->get($target->id);
            if ($targetWithMedia && $targetWithMedia->media->isNotEmpty()) {
                $primaryMedia = $targetWithMedia->media->first(fn ($m) => $m->pivot->is_primary && $m->media_type === 'image');
                if (!$primaryMedia) {
                    $primaryMedia = $targetWithMedia->media->first(fn ($m) => $m->media_type === 'image');
                }
                if ($primaryMedia) {
                    $imageUrl = url('api/v1/catalog/media/' . rawurlencode($primaryMedia->file_name));
                }
            }

            return [
                'target_product_id' => $target->id,
                'sku' => $target->sku,
                'name' => $target->name,
                'image_url' => $imageUrl,
                'relation_type' => $typeName,
                'relation_type_id' => $relation->relation_type_id,
            ];
        })->filter()->values()->toArray();
    }

    /**
     * Inject composite parent entries for any child attributes present in the list.
     */
    private function injectCompositeParents($attributes, string $lang, ?array $allowedAttributeIds)
    {
        // Find parent_attribute_ids that are referenced but not present
        $existingIds = $attributes->pluck('attribute_id')->toArray();
        $parentIds = $attributes->pluck('parent_attribute_id')->filter()->unique()->values();
        $missingParentIds = $parentIds->diff($existingIds);

        if ($missingParentIds->isEmpty()) {
            return $attributes;
        }

        $composites = Attribute::whereIn('id', $missingParentIds)
            ->where('data_type', 'Composite')
            ->get();

        $newEntries = [];
        foreach ($composites as $composite) {
            if ($allowedAttributeIds !== null && !in_array($composite->id, $allowedAttributeIds)) {
                continue;
            }

            $label = $lang === 'en' && $composite->name_en ? $composite->name_en : $composite->name_de;

            // Build formatted value from children in the attributes list
            $children = $attributes->where('parent_attribute_id', $composite->id)->values();
            $values = $children->pluck('value')->toArray();
            $formattedValue = null;

            if ($composite->composite_format) {
                $result = $composite->composite_format;
                foreach ($values as $i => $v) {
                    $result = str_replace('{' . $i . '}', $v !== null ? (string) $v : '', $result);
                }
                $formattedValue = trim($result) ?: null;
            } else {
                $filled = array_filter($values, fn ($v) => $v !== null && $v !== '');
                $formattedValue = !empty($filled) ? implode(' × ', $filled) : null;
            }

            $newEntries[] = [
                'attribute_id' => $composite->id,
                'label' => $label,
                'value' => $formattedValue,
                'unit' => null,
                'data_type' => 'Composite',
                'parent_attribute_id' => null,
                'composite_format' => $composite->composite_format,
            ];
        }

        if (empty($newEntries)) {
            return $attributes;
        }

        return collect(array_merge($newEntries, $attributes->toArray()))->values();
    }

    private function resolveAttributeDisplayValue(ProductAttributeValue $attrValue, Attribute $attr, string $lang): ?string
    {
        return match ($attr->data_type) {
            'String' => $attrValue->value_string,
            'Number', 'Float' => $attrValue->value_number !== null ? rtrim(rtrim((string) $attrValue->value_number, '0'), '.') : null,
            'Date' => $attrValue->value_date?->format('Y-m-d'),
            'Flag' => $attrValue->value_flag !== null ? ($attrValue->value_flag ? ($lang === 'en' ? 'Yes' : 'Ja') : ($lang === 'en' ? 'No' : 'Nein')) : null,
            'Selection' => $this->resolveSelectionValue($attrValue, $lang),
            'Dictionary' => $this->resolveDictionaryValue($attrValue, $lang),
            'Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink' => $this->resolveLinkDisplayValue($attrValue->value_string),
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

    private function resolveDictionaryValue(ProductAttributeValue $attrValue, string $lang): ?string
    {
        $entry = $attrValue->dictionaryEntry;
        if (!$entry) {
            return null;
        }

        return $lang === 'en' && $entry->short_text_en
            ? $entry->short_text_en
            : $entry->short_text_de;
    }

    private function resolveLinkDisplayValue(?string $json): ?string
    {
        if ($json === null || $json === '') {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $json;
        }

        return $data['title'] ?? $data['url'] ?? null;
    }
}
