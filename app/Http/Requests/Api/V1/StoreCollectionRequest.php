<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collection_type_id' => 'required|uuid|exists:collection_types,id',
            'organization_id' => 'nullable|uuid|exists:organizations,id',
            'organization_snapshot' => 'nullable|array',
            'address' => 'nullable|array',
            'address.company' => 'nullable|string|max:255',
            'address.contact_name' => 'nullable|string|max:255',
            'address.street' => 'nullable|string|max:255',
            'address.postal_code' => 'nullable|string|max:20',
            'address.city' => 'nullable|string|max:100',
            'address.country' => 'nullable|string|max:100',
            'address.email' => 'nullable|string|max:255',
            'address.phone' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'name' => 'required|string|max:500',
            'status' => 'sometimes|in:draft,open,frozen,sent,archived',
            'language' => 'sometimes|string|max:5',
            'currency' => 'nullable|string|max:3',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'source_channel' => 'nullable|string|max:50',
        ];
    }

    /**
     * Eine neu erstellte Collection kann per definitionem nie bereits eingefroren sein
     * (frozen_at ist hier ueberhaupt nicht setzbar) -- "frozen"/"sent" bei der Erstellung
     * waeren daher immer ein widerspruechlicher Zustand fuer Typen mit requires_snapshot.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!in_array($this->input('status'), ['frozen', 'sent'], true)) {
                return;
            }

            $collectionTypeId = $this->input('collection_type_id');
            if (!$collectionTypeId) {
                return;
            }

            $requiresSnapshot = (bool) \App\Models\CollectionType::where('id', $collectionTypeId)
                ->value('requires_snapshot');

            if ($requiresSnapshot) {
                $validator->errors()->add(
                    'status',
                    'Dieser Collection-Typ erfordert ein Freeze vor "frozen"/"sent" -- eine neu erstellte Collection kann diesen Status noch nicht haben.'
                );
            }
        });
    }
}
