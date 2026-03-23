<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttributeMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_hierarchy_id' => $this->source_hierarchy_id,
            'source_attribute_id' => $this->source_attribute_id,
            'target_hierarchy_id' => $this->target_hierarchy_id,
            'target_attribute_id' => $this->target_attribute_id,
            'transform_type' => $this->transform_type,
            'transform_config' => $this->transform_config,
            'ai_suggested' => $this->ai_suggested,
            'ai_confidence' => $this->ai_confidence,
            'ai_confirmed_by' => $this->ai_confirmed_by,
            'ai_confirmed_at' => $this->ai_confirmed_at,
            'source_hierarchy' => $this->whenLoaded('sourceHierarchy', fn () => [
                'id' => $this->sourceHierarchy->id,
                'technical_name' => $this->sourceHierarchy->technical_name,
                'name_de' => $this->sourceHierarchy->name_de,
                'hierarchy_type' => $this->sourceHierarchy->hierarchy_type,
            ]),
            'source_attribute' => $this->whenLoaded('sourceAttribute', fn () => [
                'id' => $this->sourceAttribute->id,
                'technical_name' => $this->sourceAttribute->technical_name,
                'name_de' => $this->sourceAttribute->name_de,
                'data_type' => $this->sourceAttribute->data_type,
                'source_system' => $this->sourceAttribute->source_system,
            ]),
            'target_hierarchy' => $this->whenLoaded('targetHierarchy', fn () => [
                'id' => $this->targetHierarchy->id,
                'technical_name' => $this->targetHierarchy->technical_name,
                'name_de' => $this->targetHierarchy->name_de,
                'hierarchy_type' => $this->targetHierarchy->hierarchy_type,
            ]),
            'target_attribute' => $this->whenLoaded('targetAttribute', fn () => [
                'id' => $this->targetAttribute->id,
                'technical_name' => $this->targetAttribute->technical_name,
                'name_de' => $this->targetAttribute->name_de,
                'data_type' => $this->targetAttribute->data_type,
                'source_system' => $this->targetAttribute->source_system,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
