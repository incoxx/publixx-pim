<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('content_page')?->id ?? $this->route('content_page');

        return [
            'content_type_id' => 'sometimes|string|exists:content_types,id',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('content_pages', 'slug')->ignore($id)],
            'title' => 'sometimes|string|max:500',
            'status' => 'sometimes|in:draft,active,inactive,archived',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'seo_title_json' => 'nullable|array',
            'seo_description_json' => 'nullable|array',
            'seo_image_id' => 'nullable|string|exists:media,id',
            'workflow_id' => 'nullable|string|exists:workflows,id',
            'current_workflow_status_id' => 'nullable|string|exists:workflow_statuses,id',
            'workflow_assignee_id' => 'nullable|string|exists:users,id',
        ];
    }
}
