<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManufacturerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'street' => $this->street,
            'zip' => $this->zip,
            'city' => $this->city,
            'country' => $this->country,
            'email' => $this->email,
            'website' => $this->website,
            'logo_media_id' => $this->logo_media_id,
            'logo_url' => $this->when($this->logo_media_id, function () {
                return $this->logoMedia ? route('media.serve', ['filename' => $this->logoMedia->filename]) : null;
            }),
            'products_count' => $this->when(isset($this->products_count), $this->products_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
