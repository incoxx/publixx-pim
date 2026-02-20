<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lang = $this->additional['lang'] ?? $request->query('lang', 'de');

        $name = $lang === 'en' && $this->name_en ? $this->name_en : $this->name_de;
        $description = $this->description_de;

        $imageUrl = null;
        if ($this->primary_image) {
            $imageUrl = '/api/v1/catalog/media/' . $this->primary_image;
        }

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'ean' => $this->ean,
            'name' => $name,
            'description' => $description,
            'category_path' => $this->hierarchy_path,
            'image_url' => $imageUrl,
            'price' => $this->list_price,
            'product_type' => $this->product_type,
        ];
    }
}
