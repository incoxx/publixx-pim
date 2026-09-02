<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'technical_name' => $this->technical_name,
            'tag_group_id' => $this->tag_group_id,
            // Nur wenn geladen — die Gruppe wird nicht überall gebraucht
            'group' => $this->whenLoaded('group', fn () => [
                'id' => $this->group->id,
                'technical_name' => $this->group->technical_name,
                'name_de' => $this->group->name_de,
                'name_en' => $this->group->name_en,
                'sort_order' => $this->group->sort_order,
            ]),
            'name_de' => $this->name_de,
            'name_en' => $this->name_en,
            'name_json' => $this->name_json,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            // Nur gesetzt, wenn der Controller withCount() geladen hat (Listenansicht).
            'products_count' => $this->whenCounted('products'),
            'media_count' => $this->whenCounted('media'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
