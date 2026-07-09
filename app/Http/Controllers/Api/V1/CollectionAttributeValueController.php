<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BulkUpdateCollectionAttributeValuesRequest;
use App\Http\Resources\Api\V1\CollectionAttributeValueResource;
use App\Models\Attribute;
use App\Models\Collection;
use App\Models\CollectionAttributeValue;
use App\Models\CollectionItem;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CollectionAttributeValueController extends Controller
{
    /**
     * GET /collections/{collection}/attribute-values
     */
    public function indexForCollection(Collection $collection): AnonymousResourceCollection
    {
        $this->authorize('view', $collection);

        return CollectionAttributeValueResource::collection(
            $this->valuesQuery('collection', $collection->id)->get()
        );
    }

    /**
     * PUT /collections/{collection}/attribute-values
     */
    public function bulkUpdateForCollection(BulkUpdateCollectionAttributeValuesRequest $request, Collection $collection): AnonymousResourceCollection
    {
        $this->authorize('update', $collection);

        $this->bulkUpdate('collection', $collection->id, $request->validated('values'));

        return CollectionAttributeValueResource::collection(
            $this->valuesQuery('collection', $collection->id)->get()
        );
    }

    /**
     * GET /collections/{collection}/items/{item}/attribute-values
     */
    public function indexForItem(Collection $collection, CollectionItem $item): AnonymousResourceCollection
    {
        $this->authorize('view', $collection);
        abort_if($item->collection_id !== $collection->id, 404);

        return CollectionAttributeValueResource::collection(
            $this->valuesQuery('collection_item', $item->id)->get()
        );
    }

    /**
     * PUT /collections/{collection}/items/{item}/attribute-values
     */
    public function bulkUpdateForItem(BulkUpdateCollectionAttributeValuesRequest $request, Collection $collection, CollectionItem $item): AnonymousResourceCollection
    {
        $this->authorize('update', $collection);
        abort_if($item->collection_id !== $collection->id, 404);

        $this->bulkUpdate('collection_item', $item->id, $request->validated('values'));

        return CollectionAttributeValueResource::collection(
            $this->valuesQuery('collection_item', $item->id)->get()
        );
    }

    private function valuesQuery(string $ownerType, string $ownerId)
    {
        return CollectionAttributeValue::query()
            ->with(['attribute', 'unit', 'valueListEntry'])
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            // Symmetrisch zu bulkUpdate()'s Schreibsperre auf is_internal-Attribute (z.B.
            // _import_match_status/_import_fuzzy_candidates) -- diese sind systemverwaltet und
            // sollen ausserhalb der dedizierten Review-Queue (CollectionItemMatchController)
            // auch nicht ueber die generische Attribut-Lese-Route sichtbar sein.
            ->whereHas('attribute', fn ($q) => $q->where('is_internal', false))
            ->orderBy('attribute_id')
            ->orderBy('multiplied_index');
    }

    /**
     * @param  array<int, array{attribute_id: string, value: mixed, value_selection_id?: string|null, language?: string|null, multiplied_index?: int, unit_id?: string|null}>  $values
     */
    private function bulkUpdate(string $ownerType, string $ownerId, array $values): void
    {
        DB::transaction(function () use ($ownerType, $ownerId, $values) {
            $multipliedIndices = [];

            foreach ($values as $entry) {
                $attribute = Attribute::findOrFail($entry['attribute_id']);

                if ($attribute->data_type === 'Composite' || $attribute->is_readonly) {
                    continue;
                }

                // _import_match_status/_import_fuzzy_candidates sind system-verwaltet
                // (CollectionFactory, Phase 4) -- kein direkter Client-Schreibzugriff.
                abort_if(
                    $attribute->is_internal,
                    422,
                    "Attribut '{$attribute->technical_name}' ist intern und kann nicht direkt geschrieben werden."
                );

                // Edge case #11: ein Attribut, dessen applies_to den Owner-Scope nicht
                // enthaelt, darf hier nicht beschrieben werden.
                abort_unless(
                    in_array($ownerType === 'collection' ? 'collection' : 'collection_item', $attribute->applies_to ?? [], true),
                    422,
                    "Attribut '{$attribute->technical_name}' ist fuer diese Ebene nicht freigegeben (applies_to)."
                );

                $language = $entry['language'] ?? null;
                $multipliedIndex = $entry['multiplied_index'] ?? 0;

                if ($attribute->is_translatable && $language === null) {
                    abort(422, "Attribut '{$attribute->technical_name}' ist uebersetzbar -- 'language' ist erforderlich.");
                }
                if (!$attribute->is_translatable && $language !== null) {
                    abort(422, "Attribut '{$attribute->technical_name}' ist nicht uebersetzbar -- 'language' darf nicht gesetzt sein.");
                }

                $valueData = $this->resolveValueColumns($attribute, $entry);

                CollectionAttributeValue::updateOrCreate(
                    [
                        'owner_type' => $ownerType,
                        'owner_id' => $ownerId,
                        'attribute_id' => $attribute->id,
                        'language' => $language,
                        'multiplied_index' => $multipliedIndex,
                    ],
                    array_merge($valueData, [
                        'unit_id' => $entry['unit_id'] ?? null,
                    ])
                );

                if ($attribute->is_translatable) {
                    CollectionAttributeValue::where('owner_type', $ownerType)
                        ->where('owner_id', $ownerId)
                        ->where('attribute_id', $attribute->id)
                        ->where('multiplied_index', $multipliedIndex)
                        ->whereNull('language')
                        ->delete();
                }

                $attrId = $attribute->id;
                if (!isset($multipliedIndices[$attrId]) || $multipliedIndex > $multipliedIndices[$attrId]) {
                    $multipliedIndices[$attrId] = $multipliedIndex;
                }
            }

            $multipliableAttrs = Attribute::whereIn('id', array_keys($multipliedIndices))
                ->where('is_multipliable', true)
                ->pluck('id');

            foreach ($multipliableAttrs as $attrId) {
                CollectionAttributeValue::where('owner_type', $ownerType)
                    ->where('owner_id', $ownerId)
                    ->where('attribute_id', $attrId)
                    ->where('multiplied_index', '>', $multipliedIndices[$attrId])
                    ->delete();
            }
        });
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
            'MultiSelection' => array_merge($columns, [
                'value_string' => is_array($value) ? json_encode($value) : $value,
            ]),
            default => array_merge($columns, ['value_string' => (string) $value]),
        };
    }
}
