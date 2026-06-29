<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NavigationNodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'navigation_id' => $this->navigation_id,
            'parent_node_id' => $this->parent_node_id,
            'path' => $this->path,
            'depth' => $this->depth,
            'sort_order' => $this->sort_order,
            'label_de' => $this->label_de,
            'label_en' => $this->label_en,
            'label_json' => $this->label_json,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'is_visible' => $this->is_visible,
            'target_type' => $this->target_type,
            'content_page_id' => $this->content_page_id,
            'hierarchy_node_id' => $this->hierarchy_node_id,
            'product_id' => $this->product_id,
            'external_url' => $this->external_url,
            'auto_expand' => $this->auto_expand,
            'expand_depth' => $this->expand_depth,
            // aufgelöste Ziel-Beschriftung (nur wenn geladen)
            'content_page' => $this->whenLoaded('contentPage', fn () => [
                'id' => $this->contentPage?->id,
                'title' => $this->contentPage?->title,
                'slug' => $this->contentPage?->slug,
            ]),
            'children' => NavigationNodeResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
