<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreValueListEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_entry_id' => 'nullable|uuid|exists:value_list_entries,id',
            'technical_name' => 'required|string|max:100',
            'display_value_de' => 'required|string|max:255',
            'display_value_en' => 'nullable|string|max:255',
            'display_value_json' => 'nullable|array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
