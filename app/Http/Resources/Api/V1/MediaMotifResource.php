<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaMotifResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title_de' => $this->title_de,
            'title_en' => $this->title_en,
            'description_de' => $this->description_de,
            'description_en' => $this->description_en,
            'focal_point_x' => $this->focal_point_x !== null ? (float) $this->focal_point_x : null,
            'focal_point_y' => $this->focal_point_y !== null ? (float) $this->focal_point_y : null,
            'rights_holder' => $this->rights_holder,
            'creator' => $this->creator,
            'credit_line' => $this->credit_line,
            'license_type' => $this->license_type,
            'license_valid_until' => $this->license_valid_until?->toDateString(),
            'license_expired' => $this->isLicenseExpired(),
            'copyright_notice' => $this->copyright_notice,
            'usage_restrictions' => $this->usage_restrictions,
            'keywords' => $this->keywords ?? [],
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'is_currently_valid' => $this->isCurrentlyValid(),
            'asset_folder_id' => $this->asset_folder_id,
            'master_rendition' => new MediaResource($this->whenLoaded('masterRendition')),
            'renditions' => MediaResource::collection($this->whenLoaded('renditions')),
            'rendition_count' => $this->renditions_count
                ?? ($this->relationLoaded('renditions') ? $this->renditions->count() : null),
            'is_used' => (bool) ($this->has_direct_assignment ?? false) || (bool) ($this->has_rendition_assignment ?? false),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
