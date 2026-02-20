<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

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
        ];
    }
}
