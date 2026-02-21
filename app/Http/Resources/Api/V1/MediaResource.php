<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'media_type' => $this->media_type,
            'title_de' => $this->title_de,
            'title_en' => $this->title_en,
            'description_de' => $this->description_de,
            'description_en' => $this->description_en,
            'alt_text_de' => $this->alt_text_de,
            'alt_text_en' => $this->alt_text_en,
            'width' => $this->width,
            'height' => $this->height,
            'asset_folder_id' => $this->asset_folder_id,
            'usage_purpose' => $this->usage_purpose,
            'url' => url("api/v1/media/file/{$this->file_name}"),
            'thumb_url' => url("api/v1/media/thumb/{$this->id}?w=300&h=300"),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
