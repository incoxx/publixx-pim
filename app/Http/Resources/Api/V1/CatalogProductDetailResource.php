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

        // Build attributes array from EAV values
        $attributes = $this->resource->attributeValues
            ->sortBy(fn ($v) => $v->attribute?->position ?? 999)
            ->map(function (ProductAttributeValue $attrValue) use ($lang) {
                $attr = $attrValue->attribute;
                if (!$attr) {
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
        ];
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
