<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content_type_id' => 'required|string|exists:content_types,id',
            'slug' => 'required|string|max:255',
            'title' => 'required|string|max:500',
            'status' => 'sometimes|in:draft,active,inactive,archived',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'seo_title_json' => 'nullable|array',
            'seo_description_json' => 'nullable|array',
            'seo_image_id' => 'nullable|string|exists:media,id',
            'workflow_id' => 'nullable|string|exists:workflows,id',
        ];
    }
}
