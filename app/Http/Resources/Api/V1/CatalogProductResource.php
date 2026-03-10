<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lang = $request->query('lang', 'de');

        $name = $lang === 'en' && $this->name_en ? $this->name_en : $this->name_de;
        $description = $this->description_de;

        $imageUrl = null;
        if ($this->primary_image) {
            $imageUrl = url('api/v1/catalog/media/' . rawurlencode($this->primary_image));
        }

        // Use resolved price if available (from configured price type), fallback to list_price
        $resolvedPrice = $this->resolved_price ?? null;
        $price = $resolvedPrice ? $resolvedPrice['amount'] : $this->list_price;
        $currency = $resolvedPrice ? $resolvedPrice['currency'] : 'EUR';

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'ean' => $this->ean,
            'name' => $name,
            'description' => $description,
            'category_path' => $this->hierarchy_path,
            'image_url' => $imageUrl,
            'price' => $price,
            'currency' => $currency,
            'product_type' => $this->product_type,
            'primary_attribute_value' => $this->primary_attribute_value ?? null,
            'card_attributes' => $this->card_attributes ?? [],
        ];
    }
}
