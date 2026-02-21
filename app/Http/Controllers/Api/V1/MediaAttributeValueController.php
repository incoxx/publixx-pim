<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BulkUpdateAttributeValuesRequest;
use App\Http\Resources\Api\V1\MediaAttributeValueResource;
use App\Models\Attribute;
use App\Models\Media;
use App\Models\MediaAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MediaAttributeValueController extends Controller
{
    /**
     * GET /media/{medium}/attribute-values
     */
    public function index(Request $request, Media $medium): AnonymousResourceCollection
    {
        $this->authorize('view', $medium);

        $languages = $this->getRequestedLanguages($request);

        $query = $medium->attributeValues()
            ->with(['attribute', 'attribute.unitGroup', 'unit', 'valueListEntry'])
            ->where(function ($q) use ($languages) {
                $q->whereNull('language')
                    ->orWhereIn('language', $languages);
            })
            ->orderBy('attribute_id')
            ->orderBy('multiplied_index');

        return MediaAttributeValueResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * PUT /media/{medium}/attribute-values — bulk save values.
     */
    public function bulkUpdate(BulkUpdateAttributeValuesRequest $request, Media $medium): JsonResponse
    {
        $this->authorize('update', $medium);

        $values = $request->validated('values');

        DB::transaction(function () use ($medium, $values) {
            foreach ($values as $entry) {
                $attribute = Attribute::findOrFail($entry['attribute_id']);
                $language = $entry['language'] ?? null;
                $multipliedIndex = $entry['multiplied_index'] ?? 0;

                if ($attribute->is_translatable && $language === null) {
                    abort(422, "Attribute '{$attribute->technical_name}' is translatable — 'language' is required.");
                }
                if (!$attribute->is_translatable && $language !== null) {
                    abort(422, "Attribute '{$attribute->technical_name}' is not translatable — 'language' must be omitted.");
                }

                $valueData = $this->resolveValueColumns($attribute, $entry);

                MediaAttributeValue::updateOrCreate(
                    [
                        'media_id' => $medium->id,
                        'attribute_id' => $attribute->id,
                        'language' => $language,
                        'multiplied_index' => $multipliedIndex,
                    ],
                    array_merge($valueData, [
                        'unit_id' => $entry['unit_id'] ?? null,
                    ])
                );
            }
        });

        return response()->json(['message' => 'Attribute values updated.', 'count' => count($values)]);
    }

    private function resolveValueColumns(Attribute $attribute, array $entry): array
    {
        $columns = [
            'value_string' => null,
            'value_number' => null,
            'value_date' => null,
            'value_flag' => null,
            'value_selection_id' => null,
        ];

        $value = $entry['value'] ?? null;

        return match ($attribute->data_type) {
            'String' => array_merge($columns, ['value_string' => (string) $value]),
            'Number', 'Float' => array_merge($columns, ['value_number' => $value !== null ? (float) $value : null]),
            'Date' => array_merge($columns, ['value_date' => $value]),
            'Flag' => array_merge($columns, ['value_flag' => (bool) $value]),
            'Selection', 'Dictionary' => array_merge($columns, [
                'value_string' => $value,
                'value_selection_id' => $entry['value_selection_id'] ?? null,
            ]),
            default => array_merge($columns, ['value_string' => (string) $value]),
        };
    }
}
