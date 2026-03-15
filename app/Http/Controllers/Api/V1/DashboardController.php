<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\Project;
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

        // My tasks (top 10) - admin sees all, user sees own or team tasks
        $tasksQuery = WorkflowTask::query()
            ->with([
                'product:id,sku,name',
                'assignee:id,name',
                'creator:id,name',
                'workflowStatus:id,name,color',
                'team:id,name',
                'project:id,name',
            ])
            ->whereIn('status', ['open', 'in_progress'])
            ->orderBy('created_at', 'desc')
            ->limit(10);

        if (!$isAdmin) {
            $teamIds = $user->teams()->pluck('teams.id')->toArray();
            $tasksQuery->where(function ($q) use ($user, $teamIds) {
                $q->where('assigned_to', $user->id);
                if (!empty($teamIds)) {
                    $q->orWhereIn('team_id', $teamIds);
                }
            });
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
            'workflow_status' => $task->workflowStatus ? [
                'id' => $task->workflowStatus->id,
                'name' => $task->workflowStatus->name,
                'color' => $task->workflowStatus->color,
            ] : null,
            'team' => $task->team ? [
                'id' => $task->team->id,
                'name' => $task->team->name,
            ] : null,
            'project' => $task->project ? [
                'id' => $task->project->id,
                'name' => $task->project->name,
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

        // Workflow summary (grouped by workflow status)
        $workflowSummary = Product::whereNotNull('current_workflow_status_id')
            ->join('workflow_statuses', 'products.current_workflow_status_id', '=', 'workflow_statuses.id')
            ->select('workflow_statuses.id', 'workflow_statuses.name', 'workflow_statuses.color', DB::raw('COUNT(*) as count'))
            ->groupBy('workflow_statuses.id', 'workflow_statuses.name', 'workflow_statuses.color')
            ->get()
            ->map(fn ($row) => [
                'status_id' => $row->id,
                'status_name' => $row->name,
                'color' => $row->color,
                'count' => $row->count,
            ]);

        // Active projects (Enterprise)
        $activeProjects = Project::whereIn('status', ['planning', 'active'])
            ->withCount(['teams', 'products'])
            ->with('manager:id,name')
            ->orderBy('end_date')
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status,
                'start_date' => $p->start_date?->toDateString(),
                'end_date' => $p->end_date?->toDateString(),
                'teams_count' => $p->teams_count,
                'products_count' => $p->products_count,
                'manager' => $p->manager ? ['id' => $p->manager->id, 'name' => $p->manager->name] : null,
            ]);

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
                'active_projects' => $activeProjects,
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
