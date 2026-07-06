<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaMotifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media_id' => 'required|uuid|exists:media,id',
            'title_de' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description_de' => 'nullable|string',
            'description_en' => 'nullable|string',
            'focal_point_x' => 'nullable|numeric|min:0|max:1',
            'focal_point_y' => 'nullable|numeric|min:0|max:1',
            'rights_holder' => 'nullable|string|max:255',
            'creator' => 'nullable|string|max:255',
            'credit_line' => 'nullable|string|max:255',
            'license_type' => 'nullable|string|max:100',
            'license_valid_until' => 'nullable|date',
            'copyright_notice' => 'nullable|string|max:500',
            'usage_restrictions' => 'nullable|string',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:100',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'asset_folder_id' => 'nullable|uuid|exists:hierarchy_nodes,id',
        ];
    }
}
