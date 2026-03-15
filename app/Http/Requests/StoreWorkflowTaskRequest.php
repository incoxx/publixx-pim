<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'title' => ['required', 'string', 'max:255'],
            'workflow_status_id' => ['nullable', 'uuid', 'exists:workflow_statuses,id'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'team_id' => ['nullable', 'uuid', 'exists:teams,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
