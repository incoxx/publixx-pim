<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTagGroupRequest;
use App\Http\Requests\Api\V1\UpdateTagGroupRequest;
use App\Http\Resources\Api\V1\TagGroupResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\TagGroup;
use App\Support\TechnicalName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagGroupController extends Controller
{
    use ChecksDeletionConstraints;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TagGroup::class);

        $query = TagGroup::query()->withCount('tags');
        $this->applySearch($query, $request, ['technical_name', 'name_de', 'name_en']);
        $this->applySorting($query, $request, 'sort_order', 'asc');

        return TagGroupResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreTagGroupRequest $request): JsonResponse
    {
        $this->authorize('create', TagGroup::class);

        $data = $request->validated();
        $data['technical_name'] = TechnicalName::resolve(
            $data['technical_name'] ?? null,
            $data['name_de'],
            fn (string $candidate) => TagGroup::where('technical_name', $candidate)->exists(),
        );

        $group = TagGroup::create($data);

        return (new TagGroupResource($group->loadCount('tags')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(TagGroup $tagGroup): TagGroupResource
    {
        $this->authorize('view', $tagGroup);

        return new TagGroupResource($tagGroup->loadCount('tags'));
    }

    public function update(UpdateTagGroupRequest $request, TagGroup $tagGroup): TagGroupResource
    {
        $this->authorize('update', $tagGroup);

        $tagGroup->update($request->validated());

        return new TagGroupResource($tagGroup->fresh()->loadCount('tags'));
    }

    public function dependencies(TagGroup $tagGroup): JsonResponse
    {
        $this->authorize('view', $tagGroup);

        return $this->dependenciesResponse($tagGroup);
    }

    /**
     * Die Tags der Gruppe werden nicht mitgelöscht, sondern ungruppiert
     * (nullOnDelete) — sie hängen an Produkten und Medien.
     */
    public function destroy(Request $request, TagGroup $tagGroup): JsonResponse
    {
        $this->authorize('delete', $tagGroup);

        return $this->destroyWithConstraintCheck($request, $tagGroup);
    }
}
