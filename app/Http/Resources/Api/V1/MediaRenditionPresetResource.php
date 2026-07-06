<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaRenditionPresetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'technical_name' => $this->technical_name,
            'name_de' => $this->name_de,
            'name_en' => $this->name_en,
            'channel' => $this->channel,
            'format' => $this->format,
            'colorspace' => $this->colorspace,
            'fit' => $this->fit,
            'max_width' => $this->max_width,
            'max_height' => $this->max_height,
            'dpi' => $this->dpi,
            'quality' => $this->quality,
            'background_color' => $this->background_color,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'requires_imagick' => $this->requiresImagick(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
