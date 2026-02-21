<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreValueListRequest;
use App\Http\Requests\Api\V1\UpdateValueListRequest;
use App\Http\Resources\Api\V1\ValueListResource;
use App\Models\ValueList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ValueListController extends Controller
{
    private const ALLOWED_INCLUDES = ['entries', 'attributes'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ValueList::class);

        $query = ValueList::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applySearch($query, $request, ['name_de', 'name_en', 'technical_name']);
        $this->applySorting($query, $request, 'name_de', 'asc');

        return ValueListResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreValueListRequest $request): JsonResponse
    {
        $this->authorize('create', ValueList::class);

        $list = ValueList::create($request->validated());

        return (new ValueListResource($list))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ValueList $valueList): ValueListResource
    {
        $this->authorize('view', $valueList);

        $valueList->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new ValueListResource($valueList);
    }

    public function update(UpdateValueListRequest $request, ValueList $valueList): ValueListResource
    {
        $this->authorize('update', $valueList);

        $valueList->update($request->validated());

        return new ValueListResource($valueList->fresh());
    }

    public function destroy(ValueList $valueList): JsonResponse
    {
        $this->authorize('delete', $valueList);

        $valueList->delete();

        return response()->json(null, 204);
    }
}
