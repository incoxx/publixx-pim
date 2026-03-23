<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttributeMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_hierarchy_id' => 'sometimes|uuid|exists:hierarchies,id',
            'source_attribute_id' => 'sometimes|uuid|exists:attributes,id',
            'target_hierarchy_id' => 'sometimes|uuid|exists:hierarchies,id',
            'target_attribute_id' => 'sometimes|uuid|exists:attributes,id',
            'transform_type' => 'sometimes|string|in:direct,unit_convert,value_map',
            'transform_config' => 'nullable|array',
            'ai_suggested' => 'sometimes|boolean',
            'ai_confidence' => 'nullable|numeric|min:0|max:1',
        ];
    }
}
