<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreValueListEntryRequest;
use App\Http\Requests\Api\V1\UpdateValueListEntryRequest;
use App\Http\Resources\Api\V1\ValueListEntryResource;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ValueListEntryController extends Controller
{
    public function index(Request $request, ValueList $valueList): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ValueListEntry::class);

        $query = $valueList->entries()
            ->orderBy('sort_order', 'asc');

        $this->applyFilters($query, array_intersect_key(
            $request->query('filter', []),
            array_flip(['is_active'])
        ));

        return ValueListEntryResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreValueListEntryRequest $request, ValueList $valueList): JsonResponse
    {
        $this->authorize('create', ValueListEntry::class);

        $entry = $valueList->entries()->create($request->validated());

        return (new ValueListEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ValueListEntry $entry): ValueListEntryResource
    {
        $this->authorize('view', $entry);

        return new ValueListEntryResource($entry);
    }

    public function update(UpdateValueListEntryRequest $request, ValueListEntry $entry): ValueListEntryResource
    {
        $this->authorize('update', $entry);

        $entry->update($request->validated());

        return new ValueListEntryResource($entry->fresh());
    }

    public function destroy(ValueListEntry $entry): JsonResponse
    {
        $this->authorize('delete', $entry);

        $entry->delete();

        return response()->json(null, 204);
    }
}
