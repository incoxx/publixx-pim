<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Media;
use App\Models\MediaUsageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media_id' => 'required|uuid|exists:media,id',
            'usage_type_id' => 'required|uuid|exists:media_usage_types,id',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $usageType = MediaUsageType::find($this->input('usage_type_id'));
                if (!$usageType || $usageType->allowed_extensions === null) {
                    return;
                }

                $media = Media::find($this->input('media_id'));
                if (!$media) {
                    return;
                }

                $ext = strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION));
                $allowed = array_map('strtolower', $usageType->allowed_extensions);

                if (!in_array($ext, $allowed, true)) {
                    $validator->errors()->add(
                        'media_id',
                        "Dateityp .{$ext} ist für den Bildtyp \"{$usageType->name_de}\" nicht erlaubt. Erlaubt: " . implode(', ', $allowed)
                    );
                }
            },
        ];
    }
}
