<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VirtualProductDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'source_type' => $this->source_type,
            'search_profile_id' => $this->search_profile_id,
            'search_profile' => $this->whenLoaded('searchProfile', fn () => [
                'id' => $this->searchProfile->id,
                'name' => $this->searchProfile->name,
            ]),
            'pql_query' => $this->pql_query,
            'manual_product_ids' => $this->manual_product_ids ?? [],
            'relation_type_id' => $this->relation_type_id,
            'relation_type' => new RelationTypeResource($this->whenLoaded('relationType')),
            'language' => $this->language,
            'max_members' => $this->max_members,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
