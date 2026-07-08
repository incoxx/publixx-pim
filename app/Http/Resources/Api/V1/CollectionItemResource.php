<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection_id' => $this->collection_id,
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'position' => $this->position,
            'quantity' => $this->quantity,
            'unit_id' => $this->unit_id,
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'snapshot' => $this->snapshot,
            'snapshot_at' => $this->snapshot_at,
            // Nur bei CollectionItemMatchController::needsReview() dynamisch gesetzt --
            // kein DB-Feld (Match-Status lebt in collection_attribute_values, siehe dort).
            'import_match_status' => $this->when(isset($this->import_match_status), $this->import_match_status),
            'import_fuzzy_candidates' => $this->when(isset($this->import_fuzzy_candidates), $this->import_fuzzy_candidates),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
