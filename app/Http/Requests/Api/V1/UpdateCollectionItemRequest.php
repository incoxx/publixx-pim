<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'nullable|uuid|exists:products,id',
            'position' => 'sometimes|integer|min:0',
            'quantity' => 'sometimes|numeric|min:0',
            'unit_id' => 'nullable|uuid|exists:units,id',
            // Manuell gepflegte Werte fuer Freitextpositionen (kein product_id) -- bei
            // produktgebundenen Positionen ersetzt EnrichmentService diese Felder ohnehin bei
            // jedem Rendern, ein manuelles Ueberschreiben waere irrefuehrend und wird unten blockiert.
            'snapshot' => 'sometimes|nullable|array',
            'snapshot.name' => 'nullable|string|max:500',
            'snapshot.resolved_price' => 'nullable|array',
            'snapshot.resolved_price.amount' => 'nullable|numeric|min:0',
            'snapshot.resolved_price.currency' => 'nullable|string|max:3',
            // Blendet einzelne Attribute der zugeordneten Positions-Attributsicht fuer GENAU
            // diese Position aus (z.B. "Farbe" bei einer Position ohne Farbvariante) -- die
            // Sichtzuordnung/Werte selbst bleiben unangetastet.
            'hidden_attribute_ids' => 'sometimes|nullable|array',
            'hidden_attribute_ids.*' => 'uuid|exists:attributes,id',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->has('snapshot')) {
                return;
            }

            $item = $this->route('item');
            $productId = $this->input('product_id', $item?->product_id);

            if ($productId !== null) {
                $validator->errors()->add(
                    'snapshot',
                    'Snapshot kann nur bei Freitextpositionen (ohne Produktbezug) manuell gesetzt werden.'
                );
            }
        });
    }
}
