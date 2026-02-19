<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class MoveHierarchyNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_node_id' => 'nullable|uuid|exists:hierarchy_nodes,id',
            'sort_order' => 'integer',
        ];
    }
}
