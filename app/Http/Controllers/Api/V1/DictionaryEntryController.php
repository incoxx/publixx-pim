<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreDictionaryEntryRequest;
use App\Http\Requests\Api\V1\UpdateDictionaryEntryRequest;
use App\Http\Resources\Api\V1\DictionaryEntryResource;
use App\Models\DictionaryEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DictionaryEntryController extends Controller
{
    private const ALLOWED_FILTERS = ['status', 'category'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DictionaryEntry::class);

        $query = DictionaryEntry::query();

        $this->applyFilters($query, array_intersect_key(
            $request->query('filter', []),
            array_flip(self::ALLOWED_FILTERS)
        ));
        $this->applySearch($query, $request, ['short_text_de', 'short_text_en', 'long_text_de', 'category']);
        $this->applySorting($query, $request, 'short_text_de', 'asc');

        return DictionaryEntryResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreDictionaryEntryRequest $request): JsonResponse
    {
        $this->authorize('create', DictionaryEntry::class);

        $entry = DictionaryEntry::create($request->validated());

        return (new DictionaryEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, DictionaryEntry $dictionaryEntry): DictionaryEntryResource
    {
        $this->authorize('view', $dictionaryEntry);

        return new DictionaryEntryResource($dictionaryEntry);
    }

    public function update(UpdateDictionaryEntryRequest $request, DictionaryEntry $dictionaryEntry): DictionaryEntryResource
    {
        $this->authorize('update', $dictionaryEntry);

        $dictionaryEntry->update($request->validated());

        return new DictionaryEntryResource($dictionaryEntry->fresh());
    }

    public function destroy(DictionaryEntry $dictionaryEntry): JsonResponse
    {
        $this->authorize('delete', $dictionaryEntry);

        $dictionaryEntry->delete();

        return response()->json(null, 204);
    }
}
