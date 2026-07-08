<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection_type_id' => $this->collection_type_id,
            'collection_type' => new CollectionTypeResource($this->whenLoaded('collectionType')),
            'organization_id' => $this->organization_id,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'organization_snapshot' => $this->organization_snapshot,
            'reference' => $this->reference,
            'name' => $this->name,
            'status' => $this->status,
            'language' => $this->language,
            'currency' => $this->currency,
            'valid_from' => $this->valid_from,
            'valid_until' => $this->valid_until,
            'frozen_at' => $this->frozen_at,
            'source_channel' => $this->source_channel,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'items_count' => $this->whenCounted('items'),
            'items' => CollectionItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
