<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_attribute_id' => 'required|uuid|exists:attributes,id',
            'target_attribute_id' => 'required|uuid|exists:attributes,id',
            'output_hierarchy_id' => 'required|uuid|exists:hierarchies,id',
            'transform_type' => 'sometimes|string|in:direct,unit_convert,value_map',
            'transform_config' => 'nullable|array',
            'ai_suggested' => 'sometimes|boolean',
            'ai_confidence' => 'nullable|numeric|min:0|max:1',
        ];
    }
}
