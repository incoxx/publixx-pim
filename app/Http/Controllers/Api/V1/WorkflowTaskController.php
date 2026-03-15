<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreWorkflowTaskRequest;
use App\Http\Requests\UpdateWorkflowTaskRequest;
use App\Http\Resources\WorkflowTaskResource;
use App\Models\WorkflowTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkflowTaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = WorkflowTask::query()->with([
            'product:id,sku,name',
            'assignee:id,name',
            'creator:id,name',
            'workflowStatus:id,name,color',
            'team:id,name',
            'project:id,name',
        ]);

        // Admin sees all, normal users see only their own tasks
        if (!$user->hasRole('Admin')) {
            $query->forUser($user->id);
        }

        // Filters
        $filters = $request->query('filter', []);
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // Search by SKU or product name
        $search = $request->query('search');
        if ($search) {
            $escaped = addcslashes($search, '%_');
            $query->whereHas('product', function ($q) use ($escaped) {
                $q->where('sku', 'LIKE', "%{$escaped}%")
                    ->orWhere('name', 'LIKE', "%{$escaped}%");
            });
        }

        $this->applySorting($query, $request, 'created_at', 'desc');

        return WorkflowTaskResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreWorkflowTaskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $task = WorkflowTask::create($data);
        $task->load(['product:id,sku,name', 'assignee:id,name', 'creator:id,name']);

        return (new WorkflowTaskResource($task))
            ->response()
            ->setStatusCode(201);
    }

    public function show(WorkflowTask $workflowTask): WorkflowTaskResource
    {
        $workflowTask->load(['product:id,sku,name', 'assignee:id,name', 'creator:id,name']);

        return new WorkflowTaskResource($workflowTask);
    }

    public function update(UpdateWorkflowTaskRequest $request, WorkflowTask $workflowTask): WorkflowTaskResource
    {
        $data = $request->validated();

        // Auto-set closed_at when status changes to closed
        if (($data['status'] ?? null) === 'closed' && $workflowTask->status !== 'closed') {
            $data['closed_at'] = now();
        }

        // Clear closed_at if reopening
        if (isset($data['status']) && $data['status'] !== 'closed' && $workflowTask->status === 'closed') {
            $data['closed_at'] = null;
        }

        $workflowTask->update($data);
        $workflowTask->load(['product:id,sku,name', 'assignee:id,name', 'creator:id,name']);

        return new WorkflowTaskResource($workflowTask);
    }

    public function destroy(WorkflowTask $workflowTask): JsonResponse
    {
        $workflowTask->delete();

        return response()->json(null, 204);
    }
}
