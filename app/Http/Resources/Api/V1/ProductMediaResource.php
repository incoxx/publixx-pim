<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'media_id' => $this->media_id,
            'media' => new MediaResource($this->whenLoaded('media')),
            'motif_id' => $this->motif_id,
            'motif' => new MediaMotifResource($this->whenLoaded('motif')),
            'preview_thumb_url' => $this->previewThumbUrl(),
            'usage_type_id' => $this->usage_type_id,
            'usage_type' => new MediaUsageTypeResource($this->whenLoaded('usageType')),
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Vorschaubild unabhängig davon, ob eine einzelne Datei oder ein Motiv
     * zugeordnet ist (dann die Master-Rendition).
     */
    private function previewThumbUrl(): ?string
    {
        if ($this->relationLoaded('media') && $this->media) {
            return (new MediaResource($this->media))->toArray(request())['thumb_url'] ?? null;
        }

        if ($this->relationLoaded('motif') && $this->motif?->relationLoaded('masterRendition') && $this->motif->masterRendition) {
            return (new MediaResource($this->motif->masterRendition))->toArray(request())['thumb_url'] ?? null;
        }

        return null;
    }
}
