<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_type_id' => $this->product_type_id,
            'sku' => $this->sku,
            'ean' => $this->ean,
            'name' => $this->name,
            'status' => $this->status,
            'workflow_id' => $this->workflow_id,
            'current_workflow_status_id' => $this->current_workflow_status_id,
            'workflow_assignee_id' => $this->workflow_assignee_id,
            'workflow_team_id' => $this->workflow_team_id,
            'workflow' => $this->whenLoaded('workflow', fn () => new WorkflowResource($this->workflow)),
            'current_workflow_status' => $this->whenLoaded('currentWorkflowStatus', fn () => new WorkflowStatusResource($this->currentWorkflowStatus)),
            'workflow_assignee' => $this->whenLoaded('workflowAssignee', fn () => [
                'id' => $this->workflowAssignee->id,
                'name' => $this->workflowAssignee->name,
            ]),
            'workflow_team' => $this->whenLoaded('workflowTeam', fn () => [
                'id' => $this->workflowTeam->id,
                'name' => $this->workflowTeam->name,
            ]),
            'product_type_ref' => $this->product_type_ref,
            'parent_product_id' => $this->parent_product_id,
            'master_hierarchy_node_id' => $this->master_hierarchy_node_id,
            'manufacturer_id' => $this->manufacturer_id,
            'product_type' => new ProductTypeResource($this->whenLoaded('productType')),
            'manufacturer' => new ManufacturerResource($this->whenLoaded('manufacturer')),
            'attribute_values' => ProductAttributeValueResource::collection($this->whenLoaded('attributeValues')),
            'variants' => ProductResource::collection($this->whenLoaded('variants')),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'prices' => ProductPriceResource::collection($this->whenLoaded('prices')),
            'relations' => ProductRelationResource::collection($this->whenLoaded('relations')),
            'parent_product' => new ProductResource($this->whenLoaded('parentProduct')),
            'master_hierarchy_node' => new HierarchyNodeResource($this->whenLoaded('masterHierarchyNode')),
            'attributes' => $this->when(isset($this->resource->getAttributes()['attributes']), $this->attributes),
            'primary_image' => $this->when(isset($this->resource->getAttributes()['primary_image']), fn () => $this->primary_image),
            'thumbnail_url' => $this->when(
                isset($this->resource->getAttributes()['primary_image']) && $this->primary_image,
                fn () => url('api/v1/media/file/' . rawurlencode($this->primary_image))
            ),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
