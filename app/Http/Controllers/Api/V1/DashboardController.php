<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\ExportJob;
use App\Models\ImportJob;
use App\Models\Product;
use App\Models\Project;
use App\Models\Team;
use App\Models\WorkflowTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        // Team workload: open tasks per team
        $teamWorkload = Team::withCount([
                'workflowTasks as open_tasks_count' => function ($q) {
                    $q->whereIn('status', ['open', 'in_progress']);
                },
            ])
            ->get()
            ->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'open_tasks' => $team->open_tasks_count,
            ])
            ->sortByDesc('open_tasks')
            ->values();

        // Project timeline: all non-completed projects with dates
        $projectTimeline = Project::whereIn('status', ['planning', 'active', 'on_hold'])
            ->withCount(['products'])
            ->orderBy('start_date')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status,
                'start_date' => $p->start_date?->toDateString(),
                'end_date' => $p->end_date?->toDateString(),
                'products_count' => $p->products_count,
            ]);

        // Welcome-Daten (personalisiert)
        $weekAgo = Carbon::now()->subDays(7);
        $userTasksCount = WorkflowTask::whereIn('status', ['open', 'in_progress']);
        if (!$isAdmin) {
            $teamIds = $user->teams()->pluck('teams.id')->toArray();
            $userTasksCount->where(function ($q) use ($user, $teamIds) {
                $q->where('assigned_to', $user->id);
                if (!empty($teamIds)) {
                    $q->orWhereIn('team_id', $teamIds);
                }
            });
        }
        $welcome = [
            'user_name' => $user->name,
            'open_tasks_count' => $userTasksCount->count(),
            'weekly_edits' => Product::where('updated_by', $user->id)
                ->where('updated_at', '>=', $weekAgo)
                ->count(),
        ];

        // Trends: Tägliche Counts der letzten 7 Tage für Produkte und Medien
        $trends = $this->buildTrends($weekAgo);

        // Datenqualität: Multi-Dimensionen
        $dataQuality = $this->buildDataQuality($totalProducts);

        // Activity-Feed: Letzte Aktivitäten aus AuditLog + Import/Export
        $activityFeed = $this->buildActivityFeed();

        // Datenflüsse: Letzte Import/Export-Jobs
        $dataFlows = $this->buildDataFlows();

        return response()->json([
            'data' => [
                'welcome' => $welcome,
                'stats' => $stats,
                'trends' => $trends,
                'data_quality' => $dataQuality,
                'activity_feed' => $activityFeed,
                'data_flows' => $dataFlows,
                'my_tasks' => $myTasks,
                'recently_edited' => $recentlyEdited,
                'workflow_summary' => $workflowSummary,
                'active_projects' => $activeProjects,
                'completeness_summary' => $completenessSummary,
                'team_workload' => $teamWorkload,
                'project_timeline' => $projectTimeline,
            ],
        ]);
    }

    private function countCompleteProducts(): int
    {
        return Product::where('status', 'active')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->whereHas('attributeValues')
            ->count();
    }

    /**
     * 7-Tage-Trend für Produkte und Medien.
     */
    private function buildTrends(Carbon $weekAgo): array
    {
        $productsByDay = Product::where('created_at', '>=', $weekAgo)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as count'))
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day');

        $mediaByDay = DB::table('media')
            ->where('created_at', '>=', $weekAgo)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as count'))
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day');

        // 7 Tage mit 0-Fill
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $days[] = Carbon::now()->subDays($i)->toDateString();
        }

        return [
            'products' => [
                'daily' => array_map(fn ($d) => $productsByDay[$d] ?? 0, $days),
                'total' => array_sum(array_map(fn ($d) => $productsByDay[$d] ?? 0, $days)),
            ],
            'media' => [
                'daily' => array_map(fn ($d) => $mediaByDay[$d] ?? 0, $days),
                'total' => array_sum(array_map(fn ($d) => $mediaByDay[$d] ?? 0, $days)),
            ],
            'days' => $days,
        ];
    }

    /**
     * Multi-dimensionaler Datenqualitäts-Score.
     */
    private function buildDataQuality(int $totalProducts): array
    {
        if ($totalProducts === 0) {
            return [
                'overall' => 100,
                'dimensions' => [],
            ];
        }

        // Stammdaten: SKU + Name vorhanden
        $withMasterData = Product::whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->count();

        // Pflicht-Attribute gefüllt (Produkte mit mindestens 1 Attributwert)
        $withAttributes = Product::whereHas('attributeValues')->count();

        // Medien-Abdeckung
        $withMedia = Product::whereHas('media')->count();

        // Preis-Abdeckung
        $withPrices = Product::whereHas('prices')->count();

        // Übersetzungen (Produkte mit Attributwerten in mehr als 1 Sprache)
        $withTranslations = DB::table('product_attribute_values')
            ->select('product_id')
            ->whereNotNull('language')
            ->groupBy('product_id')
            ->havingRaw('COUNT(DISTINCT language) > 1')
            ->get()
            ->count();

        $dimensions = [
            ['key' => 'master_data', 'label' => 'Stammdaten', 'count' => $withMasterData, 'percentage' => (int) round(($withMasterData / $totalProducts) * 100)],
            ['key' => 'attributes', 'label' => 'Attribute', 'count' => $withAttributes, 'percentage' => (int) round(($withAttributes / $totalProducts) * 100)],
            ['key' => 'media', 'label' => 'Medien', 'count' => $withMedia, 'percentage' => (int) round(($withMedia / $totalProducts) * 100)],
            ['key' => 'prices', 'label' => 'Preise', 'count' => $withPrices, 'percentage' => (int) round(($withPrices / $totalProducts) * 100)],
            ['key' => 'translations', 'label' => 'Übersetzungen', 'count' => $withTranslations, 'percentage' => (int) round(($withTranslations / $totalProducts) * 100)],
        ];

        $overall = (int) round(array_sum(array_column($dimensions, 'percentage')) / count($dimensions));

        return [
            'overall' => $overall,
            'total_products' => $totalProducts,
            'dimensions' => $dimensions,
        ];
    }

    /**
     * Aktivitäts-Feed aus AuditLog + Import/Export.
     */
    private function buildActivityFeed(): array
    {
        $items = collect();

        // AuditLog-Einträge (letzte 15)
        $auditLogs = AuditLog::with('user:id,name')
            ->whereIn('action', ['created', 'updated', 'deleted'])
            ->whereIn('auditable_type', [
                'App\\Models\\Product',
                'App\\Models\\Media',
                'App\\Models\\Attribute',
            ])
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        foreach ($auditLogs as $log) {
            $typeMap = [
                'App\\Models\\Product' => 'product',
                'App\\Models\\Media' => 'media',
                'App\\Models\\Attribute' => 'attribute',
            ];
            $actionMap = [
                'created' => 'erstellt',
                'updated' => 'bearbeitet',
                'deleted' => 'gelöscht',
            ];
            $entityType = $typeMap[$log->auditable_type] ?? 'entity';
            $items->push([
                'type' => 'audit',
                'icon' => $entityType,
                'action' => $log->action,
                'description' => ($log->user?->name ?? 'System') . ' hat ' . $this->auditEntityLabel($entityType) . ' ' . ($actionMap[$log->action] ?? $log->action),
                'user_name' => $log->user?->name,
                'timestamp' => $log->created_at?->toIso8601String(),
            ]);
        }

        // Import-Jobs (letzte 5)
        $importJobs = ImportJob::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($importJobs as $job) {
            $statusIcon = match ($job->status) {
                'completed' => 'success',
                'failed' => 'error',
                'processing', 'running' => 'running',
                default => 'info',
            };
            $items->push([
                'type' => 'import',
                'icon' => $statusIcon,
                'action' => $job->status,
                'description' => 'Import "' . ($job->file_name ?? 'Unbekannt') . '" ' . $this->jobStatusLabel($job->status),
                'user_name' => $job->user?->name,
                'timestamp' => ($job->completed_at ?? $job->started_at ?? $job->created_at)?->toIso8601String(),
            ]);
        }

        // Export-Jobs (letzte 5 Runs)
        $exportLogs = DB::table('export_job_logs')
            ->join('export_jobs', 'export_job_logs.export_job_id', '=', 'export_jobs.id')
            ->select(
                'export_job_logs.status',
                'export_job_logs.started_at',
                'export_job_logs.finished_at',
                'export_job_logs.file_size_bytes',
                'export_jobs.name as job_name',
                'export_jobs.output_format',
            )
            ->orderBy('export_job_logs.started_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($exportLogs as $log) {
            $items->push([
                'type' => 'export',
                'icon' => $log->status === 'success' ? 'success' : ($log->status === 'failed' ? 'error' : 'running'),
                'action' => $log->status,
                'description' => 'Export "' . ($log->job_name ?? $log->output_format ?? 'Export') . '" ' . $this->jobStatusLabel($log->status),
                'user_name' => null,
                'timestamp' => ($log->finished_at ?? $log->started_at),
            ]);
        }

        // Nach Zeitstempel sortieren, neueste zuerst, auf 15 begrenzen
        return $items
            ->sortByDesc('timestamp')
            ->take(15)
            ->values()
            ->toArray();
    }

    /**
     * Letzte Import/Export-Jobs.
     */
    private function buildDataFlows(): array
    {
        $imports = ImportJob::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($job) => [
                'id' => $job->id,
                'type' => 'import',
                'name' => $job->file_name ?? 'Import',
                'status' => $job->status,
                'user_name' => $job->user?->name,
                'summary' => $job->summary,
                'started_at' => $job->started_at?->toIso8601String(),
                'completed_at' => $job->completed_at?->toIso8601String(),
            ]);

        $exports = ExportJob::with('user:id,name')
            ->orderBy('last_run_at', 'desc')
            ->whereNotNull('last_run_at')
            ->limit(5)
            ->get()
            ->map(fn ($job) => [
                'id' => $job->id,
                'type' => 'export',
                'name' => $job->name ?? $job->output_format ?? 'Export',
                'status' => $job->last_status,
                'user_name' => $job->user?->name,
                'format' => $job->output_format,
                'duration_seconds' => $job->last_duration_seconds,
                'last_run_at' => $job->last_run_at?->toIso8601String(),
            ]);

        return [
            'imports' => $imports->toArray(),
            'exports' => $exports->toArray(),
        ];
    }

    private function auditEntityLabel(string $type): string
    {
        return match ($type) {
            'product' => 'ein Produkt',
            'media' => 'ein Medium',
            'attribute' => 'ein Attribut',
            default => 'einen Eintrag',
        };
    }

    private function jobStatusLabel(string $status): string
    {
        return match ($status) {
            'completed', 'success' => 'abgeschlossen',
            'failed' => 'fehlgeschlagen',
            'processing', 'running' => 'läuft',
            'pending', 'queued' => 'wartet',
            default => $status,
        };
    }
}
