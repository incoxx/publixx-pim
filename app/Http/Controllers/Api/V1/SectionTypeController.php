<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreSectionTypeRequest;
use App\Http\Requests\Api\V1\UpdateSectionTypeRequest;
use App\Http\Resources\Api\V1\SectionTypeResource;
use App\Http\Traits\Filterable;
use App\Models\SectionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SectionTypeController extends Controller
{
    use Filterable;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SectionType::class);

        $query = SectionType::query();

        $this->applyFilters($query, array_intersect_key(
            $request->query('filter', []),
            array_flip(['is_active', 'category', 'is_repeatable', 'is_nestable'])
        ));
        $this->applySorting($query, $request, 'sort_order', 'asc');

        return SectionTypeResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreSectionTypeRequest $request): JsonResponse
    {
        $this->authorize('create', SectionType::class);

        $type = SectionType::create($request->validated());

        return (new SectionTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function show(SectionType $sectionType): SectionTypeResource
    {
        $this->authorize('view', $sectionType);

        return new SectionTypeResource($sectionType);
    }

    public function update(UpdateSectionTypeRequest $request, SectionType $sectionType): SectionTypeResource
    {
        $this->authorize('update', $sectionType);

        $sectionType->update($request->validated());

        return new SectionTypeResource($sectionType->fresh());
    }

    public function destroy(SectionType $sectionType): JsonResponse
    {
        $this->authorize('delete', $sectionType);

        // System-Sektionstypen (im Code geseedet) sind nicht löschbar.
        if ($sectionType->is_system) {
            return response()->json([
                'message' => 'System-Sektionstypen können nicht gelöscht werden.',
            ], 422);
        }

        // Nicht löschbar, solange Sektionen diesen Typ verwenden.
        if ($sectionType->sections()->exists()) {
            return response()->json([
                'message' => 'Sektionstyp wird noch von Content-Sektionen verwendet.',
            ], 409);
        }

        $sectionType->delete();

        return response()->json(null, 204);
    }
}
