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

        $name = $this->resource->name;
        $description = $this->resource->searchIndex?->description_de;

        $media = $this->resource->media->map(function ($m) use ($lang) {
            return [
                'url' => '/api/v1/catalog/media/' . $m->file_name,
                'alt' => $lang === 'en' && $m->alt_text_en ? $m->alt_text_en : $m->alt_text_de,
                'is_primary' => (bool) $m->pivot->is_primary,
                'media_type' => $m->media_type,
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
            ->map(function (ProductAttributeValue $attrValue) use ($lang) {
                $attr = $attrValue->attribute;
                if (!$attr || $attr->is_internal) {
                    return null;
                }

                $label = $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de;
                $displayValue = $this->resolveAttributeDisplayValue($attrValue, $attr, $lang);

                if ($displayValue === null || $displayValue === '') {
                    return null;
                }

                $unit = $attrValue->unit?->abbreviation;

                return [
                    'label' => $label,
                    'value' => $displayValue,
                    'unit' => $unit,
                    'data_type' => $attr->data_type,
                ];
            })
            ->filter()
            ->values();

        // Build variants with their variant attribute values
        $variants = $this->buildVariants($lang);

        return [
            'id' => $this->resource->id,
            'sku' => $this->resource->sku,
            'ean' => $this->resource->ean,
            'name' => $name,
            'description' => $description,
            'product_type' => $this->resource->searchIndex?->product_type,
            'category_breadcrumb' => $breadcrumb,
            'media' => $media,
            'prices' => $prices,
            'attributes' => $attributes,
            'variants' => $variants,
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
            ->with(['attribute', 'valueListEntry', 'unit'])
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

    private function resolveAttributeDisplayValue(ProductAttributeValue $attrValue, Attribute $attr, string $lang): ?string
    {
        return match ($attr->data_type) {
            'String' => $attrValue->value_string,
            'Number', 'Float' => $attrValue->value_number !== null ? rtrim(rtrim((string) $attrValue->value_number, '0'), '.') : null,
            'Date' => $attrValue->value_date?->format('Y-m-d'),
            'Flag' => $attrValue->value_flag !== null ? ($attrValue->value_flag ? ($lang === 'en' ? 'Yes' : 'Ja') : ($lang === 'en' ? 'No' : 'Nein')) : null,
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
}
