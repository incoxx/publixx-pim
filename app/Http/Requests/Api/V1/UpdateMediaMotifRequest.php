<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaMotifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title_de' => 'sometimes|nullable|string|max:255',
            'title_en' => 'sometimes|nullable|string|max:255',
            'description_de' => 'sometimes|nullable|string',
            'description_en' => 'sometimes|nullable|string',
            'focal_point_x' => 'sometimes|nullable|numeric|min:0|max:1',
            'focal_point_y' => 'sometimes|nullable|numeric|min:0|max:1',
            'rights_holder' => 'sometimes|nullable|string|max:255',
            'creator' => 'sometimes|nullable|string|max:255',
            'credit_line' => 'sometimes|nullable|string|max:255',
            'license_type' => 'sometimes|nullable|string|max:100',
            'license_valid_until' => 'sometimes|nullable|date',
            'copyright_notice' => 'sometimes|nullable|string|max:500',
            'usage_restrictions' => 'sometimes|nullable|string',
            'keywords' => 'sometimes|nullable|array',
            'keywords.*' => 'string|max:100',
            'valid_from' => 'sometimes|nullable|date',
            'valid_until' => 'sometimes|nullable|date|after_or_equal:valid_from',
            'asset_folder_id' => 'sometimes|nullable|uuid|exists:hierarchy_nodes,id',
        ];
    }
}
