<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /**
     * Verhindert "sent" ohne vorheriges Freeze, wenn der Collection-Typ requires_snapshot
     * verlangt -- sonst koennte ein "rechtsverbindliches" Angebot mit noch lebendigem Preis
     * als versendet markiert werden, obwohl SnapshotService (Phase 3) noch nicht existiert.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('status') !== 'sent') {
                return;
            }

            $collection = $this->route('collection');
            if (!$collection) {
                return;
            }

            $collection->loadMissing('collectionType');
            $requiresSnapshot = (bool) ($collection->collectionType?->requires_snapshot ?? false);

            if ($requiresSnapshot && $collection->frozen_at === null) {
                $validator->errors()->add(
                    'status',
                    'Dieser Collection-Typ erfordert ein Freeze vor dem Versand -- Status "sent" ist erst moeglich, nachdem die Collection eingefroren wurde.'
                );
            }
        });
    }
}
