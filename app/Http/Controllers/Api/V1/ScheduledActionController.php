<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ScheduledAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduledActionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ScheduledAction::class);

        $query = ScheduledAction::query()
            ->with(['product:id,name,sku', 'creator:id,name', 'assignee:id,name']);

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->inRange($request->input('date_from'), $request->input('date_to'));
        }

        if ($request->filled('action_type')) {
            $query->where('action_type', $request->input('action_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('product_id')) {
            $query->forProduct($request->input('product_id'));
        }

        $actions = $query->orderBy('scheduled_at')->get();

        return response()->json(['data' => $actions]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ScheduledAction::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'action_type' => 'required|string|in:activate_product,deactivate_product,price_change,data_change,export',
            'scheduled_at' => 'required|date|after:now',
            'product_id' => 'sometimes|string|nullable|exists:products,id',
            'product_ids' => 'sometimes|array|nullable|max:100',
            'product_ids.*' => 'string|exists:products,id',
            'payload' => 'present|array',
            'color' => 'sometimes|string|nullable|max:20',
            'assigned_to' => 'sometimes|string|nullable|exists:users,id',
            'workflow_status' => 'sometimes|string|in:open,in_progress,done',
            ...$this->payloadRules($request->input('action_type')),
        ]);

        $validated['created_by'] = $request->user()?->id;
        $validated['status'] = 'pending';

        $action = ScheduledAction::create($validated);
        $action->load(['product:id,name,sku', 'creator:id,name', 'assignee:id,name']);

        return response()->json(['data' => $action], 201);
    }

    public function show(ScheduledAction $scheduled_action): JsonResponse
    {
        $this->authorize('view', $scheduled_action);

        $scheduled_action->load(['product:id,name,sku', 'creator:id,name', 'assignee:id,name']);

        return response()->json(['data' => $scheduled_action]);
    }

    public function update(Request $request, ScheduledAction $scheduled_action): JsonResponse
    {
        $this->authorize('update', $scheduled_action);

        // Allow workflow_status and assigned_to updates regardless of execution status
        $workflowOnlyFields = ['workflow_status', 'assigned_to'];
        $requestKeys = array_keys($request->except(['_method', '_token']));
        $isWorkflowOnlyUpdate = empty(array_diff($requestKeys, $workflowOnlyFields));

        if (!$isWorkflowOnlyUpdate && $scheduled_action->status !== 'pending') {
            return response()->json(['message' => 'Nur ausstehende Aktionen können bearbeitet werden.'], 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'action_type' => 'sometimes|string|in:activate_product,deactivate_product,price_change,data_change,export',
            'scheduled_at' => 'sometimes|date|after:now',
            'product_id' => 'sometimes|string|nullable|exists:products,id',
            'product_ids' => 'sometimes|array|nullable|max:100',
            'product_ids.*' => 'string|exists:products,id',
            'payload' => 'sometimes|array',
            'color' => 'sometimes|string|nullable|max:20',
            'assigned_to' => 'sometimes|string|nullable|exists:users,id',
            'workflow_status' => 'sometimes|string|in:open,in_progress,done',
            ...$this->payloadRules($request->input('action_type', $scheduled_action->action_type)),
        ]);

        $validated['updated_by'] = $request->user()?->id;

        $scheduled_action->update($validated);
        $scheduled_action->load(['product:id,name,sku', 'creator:id,name', 'assignee:id,name']);

        return response()->json(['data' => $scheduled_action]);
    }

    public function destroy(ScheduledAction $scheduled_action): JsonResponse
    {
        $this->authorize('delete', $scheduled_action);

        if ($scheduled_action->status === 'processing') {
            return response()->json(['message' => 'Aktionen in Bearbeitung können nicht gelöscht werden.'], 422);
        }

        $scheduled_action->delete();

        return response()->json(null, 204);
    }

    public function forProduct(string $product): JsonResponse
    {
        $this->authorize('viewAny', ScheduledAction::class);

        $actions = ScheduledAction::forProduct($product)
            ->with('creator:id,name')
            ->orderBy('scheduled_at')
            ->get();

        return response()->json(['data' => $actions]);
    }

    private function payloadRules(?string $actionType): array
    {
        return match ($actionType) {
            'price_change' => [
                'payload.prices' => 'required|array|min:1',
                'payload.prices.*.price_type_id' => 'required|string',
                'payload.prices.*.amount' => 'required|numeric|min:0',
                'payload.prices.*.currency' => 'sometimes|string|size:3',
            ],
            'data_change' => [
                'payload.attributes' => 'sometimes|array',
                'payload.attributes.*.attribute_id' => 'required_with:payload.attributes|string',
            ],
            'export' => [
                'payload.export_job_id' => 'required|string|exists:export_jobs,id',
            ],
            'activate_product', 'deactivate_product' => [
                'payload.target_status' => 'sometimes|string|in:active,inactive',
            ],
            default => [],
        };
    }
}
