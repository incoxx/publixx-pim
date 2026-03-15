<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'manager_id' => 'nullable|uuid|exists:users,id',
            'parent_project_id' => 'nullable|uuid|exists:projects,id',
            'status' => 'nullable|in:planning,active,completed,on_hold',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'uuid|exists:teams,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'uuid|exists:products,id',
        ];
    }
}
