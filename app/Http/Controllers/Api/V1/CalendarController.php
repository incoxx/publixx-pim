<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ExportJob;
use App\Models\ProductVersion;
use App\Models\Project;
use App\Models\ScheduledAction;
use Cron\CronExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalendarController extends Controller
{
    private const ACTION_COLORS = [
        'activate_product' => '#22c55e',
        'deactivate_product' => '#ef4444',
        'price_change' => '#3b82f6',
        'data_change' => '#f59e0b',
        'bulk_update' => '#a855f7',
        'export' => '#14b8a6',
        'import' => '#6366f1',
        'version_publish' => '#8b5cf6',
        'project_deadline' => '#ec4899',
    ];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to = Carbon::parse($request->input('to'))->endOfDay();

        $events = collect();

        // 1. Scheduled Actions
        $events = $events->merge($this->getScheduledActionEvents($from, $to));

        // 2. Export-Jobs mit Cron-Schedules
        $events = $events->merge($this->getExportJobEvents($from, $to));

        // 3. Geplante Produktversionen
        $events = $events->merge($this->getProductVersionEvents($from, $to));

        // 4. Projekt-Deadlines (Enterprise: Workflow)
        $events = $events->merge($this->getProjectDeadlineEvents($from, $to));

        return response()->json([
            'data' => $events->sortBy('scheduled_at')->values(),
        ]);
    }

    private function getScheduledActionEvents(Carbon $from, Carbon $to): array
    {
        $actions = ScheduledAction::inRange($from->toDateTimeString(), $to->toDateTimeString())
            ->with(['product:id,name,sku', 'creator:id,name'])
            ->get();

        return $actions->map(fn (ScheduledAction $a) => [
            'id' => $a->id,
            'source' => 'scheduled_action',
            'source_id' => $a->id,
            'title' => $a->title,
            'action_type' => $a->action_type,
            'scheduled_at' => $a->scheduled_at->toIso8601String(),
            'status' => $a->status,
            'color' => $a->color ?? (self::ACTION_COLORS[$a->action_type] ?? '#6b7280'),
            'product_id' => $a->product_id,
            'product_name' => $a->product?->name,
            'editable' => $a->status === 'pending',
        ])->all();
    }

    private function getExportJobEvents(Carbon $from, Carbon $to): array
    {
        $jobs = ExportJob::scheduled()->get();
        $events = [];

        foreach ($jobs as $job) {
            try {
                $cron = new CronExpression($job->cron_expression);
                $next = Carbon::instance($cron->getNextRunDate($from->copy()->subMinute()->toDateTime()));

                while ($next->lte($to)) {
                    $events[] = [
                        'id' => Str::uuid()->toString(),
                        'source' => 'export_job',
                        'source_id' => $job->id,
                        'title' => 'Export: ' . $job->name,
                        'action_type' => 'export',
                        'scheduled_at' => $next->toIso8601String(),
                        'status' => $next->lt(now()) ? 'completed' : 'pending',
                        'color' => self::ACTION_COLORS['export'],
                        'product_id' => null,
                        'product_name' => null,
                        'editable' => false,
                    ];

                    $next = Carbon::instance($cron->getNextRunDate($next->toDateTime()));
                }
            } catch (\Throwable $e) {
                Log::warning('Invalid cron expression for export job', [
                    'export_job_id' => $job->id,
                    'cron_expression' => $job->cron_expression,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $events;
    }

    private function getProductVersionEvents(Carbon $from, Carbon $to): array
    {
        $versions = ProductVersion::where('status', 'scheduled')
            ->whereNotNull('publish_at')
            ->whereBetween('publish_at', [$from, $to])
            ->with('product:id,name,sku')
            ->get();

        return $versions->map(fn (ProductVersion $v) => [
            'id' => $v->id,
            'source' => 'product_version',
            'source_id' => $v->id,
            'title' => 'Version ' . $v->version_number . ': ' . ($v->product?->name ?? 'Produkt'),
            'action_type' => 'version_publish',
            'scheduled_at' => $v->publish_at->toIso8601String(),
            'status' => 'pending',
            'color' => self::ACTION_COLORS['version_publish'],
            'product_id' => $v->product_id,
            'product_name' => $v->product?->name,
            'editable' => false,
        ])->all();
    }

    private function getProjectDeadlineEvents(Carbon $from, Carbon $to): array
    {
        $projects = Project::whereNotNull('end_date')
            ->whereBetween('end_date', [$from, $to])
            ->whereIn('status', ['planning', 'active'])
            ->with('manager:id,name')
            ->get();

        $events = [];

        foreach ($projects as $project) {
            $events[] = [
                'id' => 'project-' . $project->id,
                'source' => 'project',
                'source_id' => $project->id,
                'title' => 'Projekt-Deadline: ' . $project->name,
                'action_type' => 'project_deadline',
                'scheduled_at' => $project->end_date->toIso8601String(),
                'status' => $project->status,
                'color' => self::ACTION_COLORS['project_deadline'],
                'product_id' => null,
                'product_name' => null,
                'editable' => false,
            ];

            // Also show project start date if in range
            if ($project->start_date && $project->start_date->between($from, $to)) {
                $events[] = [
                    'id' => 'project-start-' . $project->id,
                    'source' => 'project',
                    'source_id' => $project->id,
                    'title' => 'Projekt-Start: ' . $project->name,
                    'action_type' => 'project_deadline',
                    'scheduled_at' => $project->start_date->toIso8601String(),
                    'status' => $project->status,
                    'color' => self::ACTION_COLORS['project_deadline'],
                    'product_id' => null,
                    'product_name' => null,
                    'editable' => false,
                ];
            }
        }

        return $events;
    }
}
