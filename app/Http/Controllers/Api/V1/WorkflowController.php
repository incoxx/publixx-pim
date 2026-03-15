<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreWorkflowRequest;
use App\Http\Requests\Api\V1\UpdateWorkflowRequest;
use App\Http\Resources\Api\V1\WorkflowResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\ProductType;
use App\Models\Workflow;
use App\Models\WorkflowStatusAssignment;
use App\Models\WorkflowTransition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    use ChecksDeletionConstraints;

    private const ALLOWED_INCLUDES = ['statuses', 'transitions', 'startStatus', 'endStatus', 'productTypes'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Workflow::class);

        $query = Workflow::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applySorting($query, $request, 'name', 'asc');

        return WorkflowResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        $this->authorize('create', Workflow::class);

        $data = $request->validated();

        $workflow = DB::transaction(function () use ($data) {
            $workflow = Workflow::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'start_status_id' => $data['start_status_id'] ?? null,
                'end_status_id' => $data['end_status_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Assign statuses
            if (!empty($data['status_ids'])) {
                foreach ($data['status_ids'] as $index => $statusId) {
                    WorkflowStatusAssignment::create([
                        'workflow_id' => $workflow->id,
                        'workflow_status_id' => $statusId,
                        'sort_order' => $index,
                    ]);
                }
            }

            // Create transitions
            if (!empty($data['transitions'])) {
                foreach ($data['transitions'] as $transition) {
                    WorkflowTransition::create([
                        'workflow_id' => $workflow->id,
                        'from_status_id' => $transition['from_status_id'],
                        'to_status_id' => $transition['to_status_id'],
                        'duration_hours' => $transition['duration_hours'] ?? null,
                        'name' => $transition['name'] ?? null,
                    ]);
                }
            }

            // Assign product types
            if (array_key_exists('product_type_ids', $data)) {
                $productTypeIds = $data['product_type_ids'] ?? [];
                ProductType::whereIn('id', $productTypeIds)->update(['workflow_id' => $workflow->id]);
            }

            return $workflow;
        });

        $workflow->load(['statuses', 'transitions.fromStatus', 'transitions.toStatus', 'startStatus', 'endStatus', 'productTypes']);

        return (new WorkflowResource($workflow))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Workflow $workflow): WorkflowResource
    {
        $this->authorize('view', $workflow);

        $workflow->load(['statuses', 'transitions.fromStatus', 'transitions.toStatus', 'startStatus', 'endStatus', 'productTypes']);

        return new WorkflowResource($workflow);
    }

    public function update(UpdateWorkflowRequest $request, Workflow $workflow): WorkflowResource
    {
        $this->authorize('update', $workflow);

        $data = $request->validated();

        DB::transaction(function () use ($workflow, $data) {
            $workflow->update(collect($data)->only(['name', 'description', 'start_status_id', 'end_status_id', 'is_active'])->toArray());

            // Replace statuses if provided
            if (array_key_exists('status_ids', $data)) {
                $workflow->statusAssignments()->delete();
                foreach (($data['status_ids'] ?? []) as $index => $statusId) {
                    WorkflowStatusAssignment::create([
                        'workflow_id' => $workflow->id,
                        'workflow_status_id' => $statusId,
                        'sort_order' => $index,
                    ]);
                }
            }

            // Replace transitions if provided
            if (array_key_exists('transitions', $data)) {
                $workflow->transitions()->delete();
                foreach (($data['transitions'] ?? []) as $transition) {
                    WorkflowTransition::create([
                        'workflow_id' => $workflow->id,
                        'from_status_id' => $transition['from_status_id'],
                        'to_status_id' => $transition['to_status_id'],
                        'duration_hours' => $transition['duration_hours'] ?? null,
                        'name' => $transition['name'] ?? null,
                    ]);
                }
            }

            // Sync product types
            if (array_key_exists('product_type_ids', $data)) {
                $productTypeIds = $data['product_type_ids'] ?? [];
                // Remove workflow from product types no longer assigned
                ProductType::where('workflow_id', $workflow->id)
                    ->whereNotIn('id', $productTypeIds)
                    ->update(['workflow_id' => null]);
                // Assign workflow to selected product types
                if (!empty($productTypeIds)) {
                    ProductType::whereIn('id', $productTypeIds)
                        ->update(['workflow_id' => $workflow->id]);
                }
            }
        });

        $workflow->load(['statuses', 'transitions.fromStatus', 'transitions.toStatus', 'startStatus', 'endStatus', 'productTypes']);

        return new WorkflowResource($workflow->fresh());
    }

    public function dependencies(Workflow $workflow): JsonResponse
    {
        $this->authorize('view', $workflow);

        return $this->dependenciesResponse($workflow);
    }

    public function destroy(Request $request, Workflow $workflow): JsonResponse
    {
        $this->authorize('delete', $workflow);

        return $this->destroyWithConstraintCheck($request, $workflow);
    }
}
