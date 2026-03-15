<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Requests\Api\V1\UpdateProjectRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    use ChecksDeletionConstraints;

    private const ALLOWED_INCLUDES = ['teams', 'products', 'manager', 'parentProject', 'subProjects'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $query = Project::query()
            ->withCount(['teams', 'products'])
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        // Filters
        $filters = $request->query('filter', []);
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['manager_id'])) {
            $query->where('manager_id', $filters['manager_id']);
        }

        $this->applySorting($query, $request, 'name', 'asc');

        return ProjectResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $project = Project::create($request->safe()->only([
            'name', 'description', 'start_date', 'end_date', 'manager_id', 'parent_project_id', 'status',
        ]));

        if ($request->has('team_ids')) {
            $project->teams()->sync($request->validated('team_ids'));
        }

        if ($request->has('product_ids')) {
            $project->products()->sync($request->validated('product_ids'));
        }

        $project->load(['teams:id,name', 'products:id,sku,name', 'manager:id,name']);

        return (new ProjectResource($project))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        $project->load([
            'teams:id,name',
            'products:id,sku,name',
            'manager:id,name',
            'parentProject:id,name',
            'subProjects:id,name,status',
            ...$this->parseIncludes($request, self::ALLOWED_INCLUDES),
        ]);

        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project->update($request->safe()->only([
            'name', 'description', 'start_date', 'end_date', 'manager_id', 'parent_project_id', 'status',
        ]));

        if ($request->has('team_ids')) {
            $project->teams()->sync($request->validated('team_ids'));
        }

        if ($request->has('product_ids')) {
            $project->products()->sync($request->validated('product_ids'));
        }

        $project->load(['teams:id,name', 'products:id,sku,name', 'manager:id,name']);

        return new ProjectResource($project->fresh());
    }

    public function dependencies(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return $this->dependenciesResponse($project);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        return $this->destroyWithConstraintCheck($request, $project);
    }
}
