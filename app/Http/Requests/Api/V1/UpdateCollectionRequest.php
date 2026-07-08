<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collection_type_id' => 'sometimes|uuid|exists:collection_types,id',
            'organization_id' => 'nullable|uuid|exists:organizations,id',
            'organization_snapshot' => 'nullable|array',
            'reference' => 'nullable|string|max:100',
            'name' => 'sometimes|string|max:500',
            // Freeze/Statuswechsel nach 'frozen' laeuft ueber SnapshotService (Phase 3), nicht hier.
            'status' => ['sometimes', 'in:draft,open,sent,archived'],
            'language' => 'sometimes|string|max:5',
            'currency' => 'nullable|string|max:3',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'source_channel' => 'nullable|string|max:50',
        ];
    }
}
