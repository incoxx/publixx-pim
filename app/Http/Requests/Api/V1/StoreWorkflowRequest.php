<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'start_status_id' => 'nullable|uuid|exists:workflow_statuses,id',
            'end_status_id' => 'nullable|uuid|exists:workflow_statuses,id',
            'is_active' => 'nullable|boolean',
            'status_ids' => 'nullable|array',
            'status_ids.*' => 'uuid|exists:workflow_statuses,id',
            'transitions' => 'nullable|array',
            'transitions.*.from_status_id' => 'required|uuid|exists:workflow_statuses,id',
            'transitions.*.to_status_id' => 'required|uuid|exists:workflow_statuses,id',
            'transitions.*.duration_hours' => 'nullable|integer|min:0',
            'transitions.*.name' => 'nullable|string|max:255',
            'product_type_ids' => 'nullable|array',
            'product_type_ids.*' => 'uuid|exists:product_types,id',
        ];
    }
}
