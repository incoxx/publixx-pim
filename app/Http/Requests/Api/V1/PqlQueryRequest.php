<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation rules for PQL query requests.
 *
 * Used by all PQL endpoints (query, count, validate, explain).
 */
final class PqlQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'pql' => ['required', 'string', 'min:5', 'max:5000'],
            'mapping_id' => ['sometimes', 'nullable', 'string', 'uuid'],
            'lang' => ['sometimes', 'array', 'min:1', 'max:10'],
            'lang.*' => ['string', 'size:2'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pql.required' => 'PQL-Query ist erforderlich.',
            'pql.min' => 'PQL-Query muss mindestens 5 Zeichen lang sein.',
            'pql.max' => 'PQL-Query darf maximal 5000 Zeichen lang sein.',
            'mapping_id.uuid' => 'mapping_id muss eine gültige UUID sein.',
            'lang.*.size' => 'Sprachcodes müssen 2 Zeichen lang sein (z.B. "de", "en").',
            'limit.min' => 'Limit muss mindestens 1 sein.',
            'limit.max' => 'Limit darf maximal 500 sein.',
            'offset.min' => 'Offset darf nicht negativ sein.',
        ];
    }
}
