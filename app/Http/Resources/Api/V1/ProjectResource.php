<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'manager_id' => $this->manager_id,
            'parent_project_id' => $this->parent_project_id,
            'status' => $this->status,
            'teams_count' => $this->when(isset($this->teams_count), $this->teams_count),
            'products_count' => $this->when(isset($this->products_count), $this->products_count),
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
            ]),
            'parent_project' => $this->whenLoaded('parentProject', fn () => [
                'id' => $this->parentProject->id,
                'name' => $this->parentProject->name,
            ]),
            'sub_projects' => $this->whenLoaded('subProjects', fn () => $this->subProjects->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status,
            ])),
            'teams' => $this->whenLoaded('teams', fn () => $this->teams->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
            ])),
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
