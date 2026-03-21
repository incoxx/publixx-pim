<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\HierarchyNode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleEntityRestrictionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $restrictable = $this->whenLoaded('restrictable', function () {
            $model = $this->restrictable;
            if (! $model) {
                return null;
            }

            $data = [
                'id' => $model->id,
                'name_de' => $model->name_de ?? null,
                'name_en' => $model->name_en ?? null,
            ];

            if ($model instanceof HierarchyNode) {
                $data['path'] = $model->path;
                $data['depth'] = $model->depth;
            }

            return $data;
        });

        return [
            'id' => $this->id,
            'role_id' => $this->role_id,
            'restrictable_type' => $this->restrictable_type,
            'restrictable_id' => $this->restrictable_id,
            'access_level' => $this->access_level,
            'restrictable' => $restrictable,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
