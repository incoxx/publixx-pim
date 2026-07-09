<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BulkStoreCollectionItemsRequest;
use App\Http\Requests\Api\V1\ReorderCollectionItemsRequest;
use App\Http\Requests\Api\V1\StoreCollectionItemRequest;
use App\Http\Requests\Api\V1\UpdateCollectionItemRequest;
use App\Http\Resources\Api\V1\CollectionItemResource;
use App\Models\Collection;
use App\Models\CollectionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CollectionItemController extends Controller
{
    private const ALLOWED_INCLUDES = ['product', 'unit'];

    public function index(Request $request, Collection $collection): AnonymousResourceCollection
    {
        $this->authorize('view', $collection);

        $items = $collection->items()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES))
            ->get();

        return CollectionItemResource::collection($items);
    }

    public function store(StoreCollectionItemRequest $request, Collection $collection): JsonResponse
    {
        $this->authorize('update', $collection);

        $data = $request->validated();

        // Ohne explizite Position ans Ende anhaengen, 10er-Schritte fuer manuelle
        // Einfuegungen ohne vollstaendige Neuindizierung.
        if (!isset($data['position'])) {
            $maxPosition = (int) $collection->items()->max('position');
            $data['position'] = $maxPosition + 10;
        }

        $item = $collection->items()->create($data);

        return (new CollectionItemResource($item))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /collections/{collection}/items/bulk
     *
     * Fuegt mehrere Produkte auf einmal hinzu (z.B. Uebertragung aus der internen
     * Merkliste) -- Body: { "product_ids": [...] }, analog zu WatchlistController::
     * bulkStore()/ProjectController::bulkAddProducts(). Bereits vorhandene Produkte
     * in dieser Collection werden uebersprungen statt dupliziert.
     */
    public function bulkStore(BulkStoreCollectionItemsRequest $request, Collection $collection): JsonResponse
    {
        $this->authorize('update', $collection);

        $productIds = $request->validated('product_ids');
        $existingProductIds = $collection->items()->whereIn('product_id', $productIds)->pluck('product_id')->all();
        $newProductIds = array_diff($productIds, $existingProductIds);

        $position = (int) $collection->items()->max('position');
        $added = 0;

        foreach ($newProductIds as $productId) {
            $position += 10;
            $collection->items()->create(['product_id' => $productId, 'position' => $position]);
            $added++;
        }

        return response()->json([
            'message' => "{$added} Produkt(e) zur Collection hinzugefuegt",
            'added' => $added,
            'skipped' => count($existingProductIds),
        ], 201);
    }

    public function show(Request $request, Collection $collection, CollectionItem $item): CollectionItemResource
    {
        $this->authorize('view', $collection);
        $this->assertBelongsToCollection($collection, $item);

        $item->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new CollectionItemResource($item);
    }

    public function update(UpdateCollectionItemRequest $request, Collection $collection, CollectionItem $item): CollectionItemResource
    {
        $this->authorize('update', $collection);
        $this->assertBelongsToCollection($collection, $item);

        $item->update($request->validated());

        return new CollectionItemResource($item->fresh());
    }

    public function destroy(Collection $collection, CollectionItem $item): JsonResponse
    {
        $this->authorize('update', $collection);
        $this->assertBelongsToCollection($collection, $item);

        $item->delete();

        return response()->json(null, 204);
    }

    /**
     * PATCH /collections/{collection}/items/reorder
     *
     * Body: { "item_ids": [...] } -- vollstaendige Reihenfolge, Position aus dem Array-Index
     * abgeleitet (10er-Schritte), analog zu ProductMediaController::reorder().
     */
    public function reorder(ReorderCollectionItemsRequest $request, Collection $collection): JsonResponse
    {
        $this->authorize('update', $collection);

        $itemIds = $request->validated('item_ids');
        $items = $collection->items()->whereIn('id', $itemIds)->get()->keyBy('id');

        foreach (array_values($itemIds) as $index => $itemId) {
            $items->get($itemId)?->update(['position' => ($index + 1) * 10]);
        }

        return response()->json(null, 204);
    }

    private function assertBelongsToCollection(Collection $collection, CollectionItem $item): void
    {
        abort_if($item->collection_id !== $collection->id, 404);
    }
}
