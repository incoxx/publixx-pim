<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_type_id' => 'sometimes|uuid|exists:product_types,id',
            'sku' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('products', 'sku')->ignore($this->route('product')),
            ],
            'ean' => 'nullable|string|max:20',
            'name' => 'sometimes|string|max:500',
            'status' => 'in:draft,active,inactive,discontinued',
            'master_hierarchy_node_id' => 'nullable|uuid|exists:hierarchy_nodes,id',
            'manufacturer_id' => 'nullable|uuid|exists:manufacturers,id',
            'workflow_id' => 'nullable|uuid|exists:workflows,id',
            'current_workflow_status_id' => 'nullable|uuid|exists:workflow_statuses,id',
            'workflow_assignee_id' => 'nullable|uuid|exists:users,id',
            'workflow_team_id' => 'nullable|uuid|exists:teams,id',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'uuid|exists:projects,id',
        ];
    }
}
