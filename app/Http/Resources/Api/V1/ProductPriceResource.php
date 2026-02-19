<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'price_type_id' => $this->price_type_id,
            'price_type' => new PriceTypeResource($this->whenLoaded('priceType')),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'valid_from' => $this->valid_from,
            'valid_to' => $this->valid_to,
            'country' => $this->country,
            'scale_from' => $this->scale_from,
            'scale_to' => $this->scale_to,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
