<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\WorkflowTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('Admin');

        // Stats overview
        $stats = [
            'products_total' => Product::count(),
            'products_active' => Product::where('status', 'active')->count(),
            'products_draft' => Product::where('status', 'draft')->count(),
            'hierarchies_count' => DB::table('hierarchies')->count(),
            'attributes_count' => DB::table('attributes')->count(),
            'media_count' => DB::table('media')->count(),
        ];

        // My tasks (top 10) - admin sees all, user sees own
        $tasksQuery = WorkflowTask::query()
            ->with(['product:id,sku,name', 'assignee:id,name', 'creator:id,name'])
            ->whereIn('status', ['open', 'in_progress'])
            ->orderBy('created_at', 'desc')
            ->limit(10);

        if (!$isAdmin) {
            $tasksQuery->forUser($user->id);
        }

        $myTasks = $tasksQuery->get()->map(fn ($task) => [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'created_at' => $task->created_at?->toIso8601String(),
            'product' => $task->product ? [
                'id' => $task->product->id,
                'sku' => $task->product->sku,
                'name' => $task->product->name,
            ] : null,
            'assignee' => $task->assignee ? [
                'id' => $task->assignee->id,
                'name' => $task->assignee->name,
            ] : null,
            'creator' => $task->creator ? [
                'id' => $task->creator->id,
                'name' => $task->creator->name,
            ] : null,
        ]);

        // Recently edited by this user (top 10)
        $recentlyEdited = Product::where('updated_by', $user->id)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get(['id', 'sku', 'name', 'status', 'updated_at'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'status' => $p->status,
                'updated_at' => $p->updated_at?->toIso8601String(),
            ]);

        // Workflow summary
        $workflowSummary = [
            'editing' => Product::where('workflow_status', 'editing')->count(),
            'review' => Product::where('workflow_status', 'review')->count(),
            'approved' => Product::where('workflow_status', 'approved')->count(),
            'unassigned' => Product::whereNotNull('workflow_status')
                ->whereNull('workflow_assignee_id')
                ->count(),
        ];

        // Completeness summary (lightweight — counts products with/without mandatory attributes filled)
        $totalProducts = $stats['products_total'];
        $productsWithAllMandatory = $this->countCompleteProducts();

        $completenessSummary = [
            'fully_complete' => $productsWithAllMandatory,
            'incomplete' => $totalProducts - $productsWithAllMandatory,
            'total' => $totalProducts,
            'average_percentage' => $totalProducts > 0
                ? (int) round(($productsWithAllMandatory / $totalProducts) * 100)
                : 100,
        ];

        return response()->json([
            'data' => [
                'stats' => $stats,
                'my_tasks' => $myTasks,
                'recently_edited' => $recentlyEdited,
                'workflow_summary' => $workflowSummary,
                'completeness_summary' => $completenessSummary,
            ],
        ]);
    }

    private function countCompleteProducts(): int
    {
        // A product is "complete" if it has: sku, name, status=active, and at least one attribute value
        return Product::where('status', 'active')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->whereHas('attributeValues')
            ->count();
    }
}
