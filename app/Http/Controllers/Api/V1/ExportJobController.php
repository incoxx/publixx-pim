<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\ExecuteExportJob;
use App\Models\ExportJob;
use App\Models\ExportJobLog;
use App\Services\Export\ExportDeliveryService;
use App\Services\Export\ExportJobService;
use App\Services\Export\JsonFormatExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * REST-API für Export-Job-Steuerung.
 *
 *   GET    /api/v1/export-jobs              — Alle Jobs auflisten
 *   POST   /api/v1/export-jobs              — Neuen Job anlegen
 *   GET    /api/v1/export-jobs/{id}         — Job-Details
 *   PUT    /api/v1/export-jobs/{id}         — Job aktualisieren
 *   DELETE /api/v1/export-jobs/{id}         — Job löschen
 *   POST   /api/v1/export-jobs/{id}/execute — Job sofort ausführen
 *   GET    /api/v1/export-jobs/{id}/download — Letzte Export-Datei herunterladen
 *   GET    /api/v1/export-jobs/{id}/logs     — Ausführungsprotokoll
 *   GET    /api/v1/export-jobs/{id}/stream   — JSON-Export direkt als API-Response streamen
 */
class ExportJobController extends Controller
{
    public function __construct(
        private readonly ExportJobService $jobService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $jobs = ExportJob::query()
            ->when($userId, fn ($q) => $q->visibleTo($userId))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $jobs]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string|nullable',
            'export_profile_id' => 'sometimes|string|nullable|exists:export_profiles,id',
            'search_profile_id' => 'sometimes|string|nullable|exists:search_profiles,id',
            'format' => 'required|string|in:json,excel,csv,xml',
            'sections' => 'sometimes|array|nullable',
            'sections.*' => 'string',
            'filters' => 'sometimes|array|nullable',
            'cron_expression' => 'sometimes|string|nullable|max:100',
            'delivery_type' => 'sometimes|string|nullable|in:filesystem,sftp,http',
            'delivery_config' => 'sometimes|array|nullable',
            'is_active' => 'sometimes|boolean',
            'is_shared' => 'sometimes|boolean',
        ]);

        $validated['user_id'] = $request->user()?->id;

        // Credentials verschlüsseln
        if (!empty($validated['delivery_config']) && !empty($validated['delivery_type'])) {
            $validated['delivery_config'] = ExportDeliveryService::encryptCredentials(
                $validated['delivery_config'],
                $validated['delivery_type'],
            );
        }

        $job = ExportJob::create($validated);

        // next_run_at initial berechnen
        $this->jobService->updateNextRunAt($job);

        return response()->json(['data' => $job->fresh()], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $job = ExportJob::findOrFail($id);
        $this->authorizeJobAccess($request, $job);

        return response()->json([
            'data' => $job->load(['exportProfile', 'searchProfile']),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $job = ExportJob::findOrFail($id);
        $this->authorizeJobAccess($request, $job);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'export_profile_id' => 'sometimes|string|nullable|exists:export_profiles,id',
            'search_profile_id' => 'sometimes|string|nullable|exists:search_profiles,id',
            'format' => 'sometimes|string|in:json,excel,csv,xml',
            'sections' => 'sometimes|array|nullable',
            'sections.*' => 'string',
            'filters' => 'sometimes|array|nullable',
            'cron_expression' => 'sometimes|string|nullable|max:100',
            'delivery_type' => 'sometimes|string|nullable|in:filesystem,sftp,http',
            'delivery_config' => 'sometimes|array|nullable',
            'is_active' => 'sometimes|boolean',
            'is_shared' => 'sometimes|boolean',
        ]);

        // Credentials verschlüsseln
        if (!empty($validated['delivery_config']) && !empty($validated['delivery_type'] ?? $job->delivery_type)) {
            $validated['delivery_config'] = ExportDeliveryService::encryptCredentials(
                $validated['delivery_config'],
                $validated['delivery_type'] ?? $job->delivery_type,
            );
        }

        $job->update($validated);

        // next_run_at neu berechnen bei Änderung von cron_expression oder is_active
        if (array_key_exists('cron_expression', $validated) || array_key_exists('is_active', $validated)) {
            $this->jobService->updateNextRunAt($job->fresh());
        }

        return response()->json(['data' => $job->fresh()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $job = ExportJob::findOrFail($id);
        $this->authorizeJobAccess($request, $job);
        $job->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/export-jobs/{id}/execute — Job sofort ausführen.
     */
    public function execute(Request $request, string $id): JsonResponse
    {
        $job = ExportJob::findOrFail($id);
        $this->authorizeJobAccess($request, $job);
        $async = $request->boolean('async', false);

        if ($async) {
            ExecuteExportJob::dispatch($job->id);

            $job->update(['last_status' => 'pending']);

            return response()->json([
                'message' => 'Export-Job in Warteschlange eingereiht',
                'data' => $job->fresh(),
            ], 202);
        }

        $result = $this->jobService->execute($job);

        return response()->json([
            'message' => 'Export-Job abgeschlossen',
            'data' => [
                'job' => $job->fresh(),
                'result' => $result,
            ],
        ]);
    }

    /**
     * GET /api/v1/export-jobs/{id}/download — Letzte Export-Datei herunterladen.
     */
    public function download(Request $request, string $id)
    {
        $job = ExportJob::findOrFail($id);
        $this->authorizeJobAccess($request, $job);

        if (!$job->last_output_path || !file_exists($job->last_output_path)) {
            return response()->json([
                'error' => 'Keine Export-Datei vorhanden. Bitte zuerst den Job ausführen.',
            ], 404);
        }

        return response()->download(
            $job->last_output_path,
            basename($job->last_output_path),
        );
    }

    /**
     * GET /api/v1/export-jobs/{id}/stream — JSON-Export direkt als API-Response streamen.
     *
     * Gibt die PIM-Daten des Jobs direkt als JSON zurück, ohne Datei auf Disk.
     * Nur für Jobs mit format=json verfügbar.
     */
    public function stream(Request $request, string $id, JsonFormatExporter $exporter): StreamedResponse
    {
        $job = ExportJob::findOrFail($id);
        $this->authorizeJobAccess($request, $job);

        if ($job->format !== 'json') {
            abort(422, 'Streaming ist nur für JSON-Exporte verfügbar.');
        }

        $filters = $this->jobService->resolveFilters($job);
        $sections = $job->sections ?? [];

        return response()->stream(function () use ($exporter, $sections, $filters) {
            echo $exporter->export($sections, $filters);
        }, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * GET /api/v1/export-jobs/{id}/logs — Ausführungsprotokoll.
     */
    public function logs(Request $request, string $id): JsonResponse
    {
        $job = ExportJob::findOrFail($id);
        $this->authorizeJobAccess($request, $job);

        $logs = $job->logs()->limit(50)->get();

        return response()->json(['data' => $logs]);
    }

    /**
     * Prüft, ob der aktuelle Benutzer Zugriff auf den Job hat.
     * Geteilte Jobs sind für alle sichtbar, private Jobs nur für den Ersteller.
     */
    private function authorizeJobAccess(Request $request, ExportJob $job): void
    {
        $userId = $request->user()?->id;
        if (!$job->is_shared && $job->user_id && $userId !== $job->user_id) {
            abort(403, 'Kein Zugriff auf diesen Export-Job.');
        }
    }
}
