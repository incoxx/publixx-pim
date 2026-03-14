<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:open,in_progress,closed'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:5000'],
            'title' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
