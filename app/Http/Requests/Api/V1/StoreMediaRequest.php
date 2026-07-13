<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\Media\MediaFileTypes;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:204800', // 200 MB
                function (string $attribute, $value, Closure $fail) {
                    $extension = strtolower((string) $value->getClientOriginalExtension());
                    if (! in_array($extension, MediaFileTypes::ALLOWED_EXTENSIONS, true)) {
                        $fail("Dateityp \".{$extension}\" ist nicht erlaubt.");
                    }
                },
            ],
            'title_de' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description_de' => 'nullable|string',
            'description_en' => 'nullable|string',
            'alt_text_de' => 'nullable|string|max:255',
            'alt_text_en' => 'nullable|string|max:255',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:100',
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
            'asset_folder_id' => 'nullable|uuid|exists:hierarchy_nodes,id',
            'usage_purpose' => 'nullable|in:print,web,both,catalog_logo',
        ];
    }
}
