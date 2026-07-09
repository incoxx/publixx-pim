<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ImportCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collection_type_id' => 'required|uuid|exists:collection_types,id',
            'adapter' => 'required|in:json,csv,opentrans',
            'file' => 'required_without:payload|file|max:10240',
            'payload' => 'required_without:file|string',
            'organization' => 'nullable|array',
            'organization.external_ref' => 'nullable|string|max:191',
            'organization.name' => 'nullable|string|max:500',
            'organization.language' => 'nullable|string|max:5',
            'organization.price_list_ref' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:3',
        ];
    }
}
