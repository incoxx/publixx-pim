<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaRenditionPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technical_name' => 'required|string|max:100|unique:media_rendition_presets,technical_name',
            'name_de' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'channel' => ['required', Rule::in(['print', 'web', 'mobile', 'social'])],
            'format' => ['required', Rule::in(['jpeg', 'png', 'webp', 'gif', 'tiff'])],
            'colorspace' => ['sometimes', Rule::in(['rgb', 'cmyk', 'gray'])],
            'fit' => ['sometimes', Rule::in(['contain', 'cover'])],
            'max_width' => 'nullable|integer|min:1|max:20000',
            'max_height' => 'nullable|integer|min:1|max:20000',
            'dpi' => 'nullable|integer|min:1|max:2400',
            'quality' => 'nullable|integer|min:1|max:100',
            'background_color' => 'nullable|string|max:7',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
