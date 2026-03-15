<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTeamRequest;
use App\Http\Requests\Api\V1\UpdateTeamRequest;
use App\Http\Resources\Api\V1\TeamResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
    use ChecksDeletionConstraints;

    private const ALLOWED_INCLUDES = ['users', 'projects'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Team::class);

        $query = Team::query()
            ->withCount('users')
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applySorting($query, $request, 'name', 'asc');

        return TeamResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $this->authorize('create', Team::class);

        $team = Team::create($request->safe()->only(['name', 'description']));

        if ($request->has('user_ids')) {
            $team->users()->sync($request->validated('user_ids'));
        }

        $team->load('users:id,name,email');

        return (new TeamResource($team))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Team $team): TeamResource
    {
        $this->authorize('view', $team);

        $team->load(['users:id,name,email', ...$this->parseIncludes($request, self::ALLOWED_INCLUDES)]);

        return new TeamResource($team);
    }

    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $this->authorize('update', $team);

        $team->update($request->safe()->only(['name', 'description']));

        if ($request->has('user_ids')) {
            $team->users()->sync($request->validated('user_ids'));
        }

        $team->load('users:id,name,email');

        return new TeamResource($team->fresh());
    }

    public function dependencies(Team $team): JsonResponse
    {
        $this->authorize('view', $team);

        return $this->dependenciesResponse($team);
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        $this->authorize('delete', $team);

        return $this->destroyWithConstraintCheck($request, $team);
    }
}
