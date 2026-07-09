<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ConfirmCollectionItemMatchRequest;
use App\Http\Resources\Api\V1\CollectionItemResource;
use App\Models\Attribute;
use App\Models\Collection;
use App\Models\CollectionAttributeValue;
use App\Models\CollectionItem;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CollectionItemMatchController extends Controller
{
    /**
     * GET /collections/{collection}/items/needs-review
     *
     * Positionen mit _import_match_status in (unconfirmed, unresolved) -- die konkrete
     * Review-Queue fuer edge cases #1-#3 (human-in-the-loop).
     */
    public function needsReview(Collection $collection): AnonymousResourceCollection
    {
        $this->authorize('view', $collection);

        $statusAttr = Attribute::where('technical_name', '_import_match_status')->first();
        if (!$statusAttr) {
            return CollectionItemResource::collection(collect());
        }

        $itemIds = CollectionAttributeValue::where('owner_type', 'collection_item')
            ->where('attribute_id', $statusAttr->id)
            ->whereIn('value_string', ['unconfirmed', 'unresolved'])
            ->pluck('value_string', 'owner_id');

        $items = $collection->items()
            ->whereIn('id', $itemIds->keys())
            ->with('product', 'unit')
            ->orderBy('position')
            ->get();

        $candidatesAttr = Attribute::where('technical_name', '_import_fuzzy_candidates')->first();
        $candidateValues = $candidatesAttr
            ? CollectionAttributeValue::where('owner_type', 'collection_item')
                ->whereIn('owner_id', $itemIds->keys())
                ->where('attribute_id', $candidatesAttr->id)
                ->pluck('value_string', 'owner_id')
            : collect();

        $items->each(function (CollectionItem $item) use ($itemIds, $candidateValues) {
            $item->import_match_status = $itemIds[$item->id] ?? null;
            $item->import_fuzzy_candidates = isset($candidateValues[$item->id])
                ? json_decode($candidateValues[$item->id], true)
                : [];
        });

        return CollectionItemResource::collection($items);
    }

    /**
     * POST /collections/{collection}/items/{item}/confirm-match
     *
     * Mensch bestaetigt einen vorgeschlagenen (oder manuell gesuchten) Produkt-Treffer.
     */
    public function confirm(ConfirmCollectionItemMatchRequest $request, Collection $collection, CollectionItem $item): CollectionItemResource
    {
        $this->authorize('update', $collection);
        abort_if($item->collection_id !== $collection->id, 404);

        $item->update(['product_id' => $request->validated('product_id')]);

        $statusAttr = Attribute::where('technical_name', '_import_match_status')->first();
        if ($statusAttr) {
            CollectionAttributeValue::updateOrCreate(
                ['owner_type' => 'collection_item', 'owner_id' => $item->id, 'attribute_id' => $statusAttr->id, 'language' => null, 'multiplied_index' => 0],
                ['value_string' => 'matched']
            );
        }

        return new CollectionItemResource($item->fresh(['product']));
    }
}
