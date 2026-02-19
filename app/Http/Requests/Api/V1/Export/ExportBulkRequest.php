<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Export;

use Illuminate\Foundation\Http\FormRequest;

class ExportBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['required', 'array'],
            'filter.status' => ['sometimes', 'string', 'in:draft,active,inactive,discontinued'],
            'filter.hierarchy_node' => ['sometimes', 'string', 'uuid'],
            'filter.hierarchy_path' => ['sometimes', 'string', 'max:1000'],
            'filter.attribute' => ['sometimes', 'array'],
            'filter.updated_after' => ['sometimes', 'date'],
            'mapping_id' => ['sometimes', 'string', 'uuid'],
            'include_media' => ['sometimes', 'boolean'],
            'include_prices' => ['sometimes', 'boolean'],
            'include_relations' => ['sometimes', 'boolean'],
            'lang' => ['sometimes', 'string', 'max:50'],
            'format' => ['sometimes', 'string', 'in:flat,nested,publixx'],
        ];
    }
}
