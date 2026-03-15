<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreWorkflowStatusRequest;
use App\Http\Requests\Api\V1\UpdateWorkflowStatusRequest;
use App\Http\Resources\Api\V1\WorkflowStatusResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\WorkflowStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkflowStatusController extends Controller
{
    use ChecksDeletionConstraints;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WorkflowStatus::class);

        $query = WorkflowStatus::query();

        $this->applySorting($query, $request, 'sort_order', 'asc');

        return WorkflowStatusResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreWorkflowStatusRequest $request): JsonResponse
    {
        $this->authorize('create', WorkflowStatus::class);

        $status = WorkflowStatus::create($request->validated());

        return (new WorkflowStatusResource($status))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, WorkflowStatus $workflowStatus): WorkflowStatusResource
    {
        $this->authorize('view', $workflowStatus);

        return new WorkflowStatusResource($workflowStatus);
    }

    public function update(UpdateWorkflowStatusRequest $request, WorkflowStatus $workflowStatus): WorkflowStatusResource
    {
        $this->authorize('update', $workflowStatus);

        $workflowStatus->update($request->validated());

        return new WorkflowStatusResource($workflowStatus->fresh());
    }

    public function dependencies(WorkflowStatus $workflowStatus): JsonResponse
    {
        $this->authorize('view', $workflowStatus);

        return $this->dependenciesResponse($workflowStatus);
    }

    public function destroy(Request $request, WorkflowStatus $workflowStatus): JsonResponse
    {
        $this->authorize('delete', $workflowStatus);

        return $this->destroyWithConstraintCheck($request, $workflowStatus);
    }
}
