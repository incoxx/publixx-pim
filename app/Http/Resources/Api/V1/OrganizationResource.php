<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_ref' => $this->external_ref,
            'name' => $this->name,
            'language' => $this->language,
            'address_block' => $this->address_block,
            'logo_media_id' => $this->logo_media_id,
            'price_list_ref' => $this->price_list_ref,
            'currency' => $this->currency,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
