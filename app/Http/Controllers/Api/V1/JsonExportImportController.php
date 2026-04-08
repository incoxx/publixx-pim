<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\AsyncJsonExportJob;
use App\Services\Export\JsonFormatExporter;
use App\Services\Import\JsonFormatImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * REST-API für JSON Export und Import.
 *
 * Export:
 *   GET  /api/v1/json-export                — Vollexport als JSON-Download
 *   POST /api/v1/json-export                — Export mit Filter/Sektionen
 *   GET  /api/v1/json-export/sections       — Verfügbare Sektionen auflisten
 *
 * Import:
 *   POST /api/v1/json-import                — JSON-Datei importieren
 *   POST /api/v1/json-import/validate       — JSON-Datei validieren (ohne Import)
 */
class JsonExportImportController extends Controller
{
    public function __construct(
        private readonly JsonFormatExporter $exporter,
        private readonly JsonFormatImporter $importer,
    ) {}

    /**
     * POST /api/v1/json-export/start — Async-Export starten.
     * Gibt sofort {export_key} zurück; der Job läuft im Hintergrund.
     */
    public function startAsync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sections'               => 'sometimes|array',
            'sections.*'             => 'string',
            'filter'                 => 'sometimes|array',
            'filter.status'          => 'sometimes|string|in:draft,active,inactive',
            'filter.product_type'    => 'sometimes|string',
            'filter.search_text'     => 'sometimes|string',
            'filter.updated_after'   => 'sometimes|date',
            'filter.skus'            => 'sometimes|array',
            'filter.skus.*'          => 'string',
            'filter.category_ids'    => 'sometimes|array',
            'filter.category_ids.*'  => 'string',
        ]);

        $sections  = $validated['sections'] ?? [];
        $filters   = $validated['filter'] ?? [];
        $exportKey = 'json_' . Str::random(16);
        $userId    = $request->user()?->id;
        $fileName  = 'pim-export-' . now()->format('Y-m-d_His') . '.json';

        AsyncJsonExportJob::dispatch($exportKey, $sections, $filters, $fileName);

        // Owner-ID für Auth-Check bei Progress/Download speichern
        Cache::put(AsyncJsonExportJob::cacheKey($exportKey) . ':owner', $userId, 3600);

        return response()->json([
            'data' => [
                'export_key' => $exportKey,
                'status'     => 'queued',
            ],
        ]);
    }

    /**
     * GET /api/v1/json-export/progress/{exportKey} — Fortschritt abfragen.
     */
    public function exportProgress(Request $request, string $exportKey): JsonResponse
    {
        $this->authorizeExportAccess($request, $exportKey);

        $progress = Cache::get(AsyncJsonExportJob::cacheKey($exportKey));

        if (!$progress) {
            return response()->json([
                'data' => ['status' => 'queued', 'processed' => 0, 'total' => 0, 'percent' => 0],
            ]);
        }

        return response()->json(['data' => $progress]);
    }

    /**
     * POST /api/v1/json-export/cancel/{exportKey} — Async-Export abbrechen.
     */
    public function cancelExport(Request $request, string $exportKey): JsonResponse
    {
        $this->authorizeExportAccess($request, $exportKey);

        AsyncJsonExportJob::cancel($exportKey);

        return response()->json(['data' => ['status' => 'cancelling']]);
    }

    /**
     * GET /api/v1/json-export/download/{exportKey} — Fertige Datei herunterladen.
     */
    public function downloadExport(Request $request, string $exportKey): BinaryFileResponse|JsonResponse
    {
        $this->authorizeExportAccess($request, $exportKey);

        $progress = Cache::get(AsyncJsonExportJob::cacheKey($exportKey));

        if (!$progress || $progress['status'] !== 'completed') {
            return response()->json(['error' => 'Export nicht bereit.'], 404);
        }

        $path = $progress['output_path'] ?? null;
        if (!$path || !file_exists($path)) {
            return response()->json(['error' => 'Export-Datei nicht gefunden.'], 404);
        }

        // Path-Traversal verhindern
        $realPath   = realpath($path);
        $allowedDir = storage_path('app/exports');
        if (!$realPath || !str_starts_with($realPath, $allowedDir)) {
            return response()->json(['error' => 'Ungültiger Export-Pfad.'], 403);
        }

        $fileName = $progress['file_name'] ?? 'pim-export.json';

        // Cache aufräumen
        Cache::forget(AsyncJsonExportJob::cacheKey($exportKey));
        Cache::forget(AsyncJsonExportJob::cacheKey($exportKey) . ':owner');

        return response()->download($path, $fileName, [
            'Content-Type' => 'application/json; charset=utf-8',
        ])->deleteFileAfterSend(true);
    }

    /**
     * GET /api/v1/json-export — Vollexport aller PIM-Daten als JSON-Download.
     */
    public function export(Request $request): StreamedResponse
    {
        set_time_limit(0);
        $sections = $request->input('sections', []);
        $filters = $request->input('filter', []);

        $fileName = 'pim-export-' . now()->format('Y-m-d_His') . '.json';

        return new StreamedResponse(function () use ($sections, $filters) {
            echo $this->exporter->export(
                is_array($sections) ? $sections : [],
                is_array($filters) ? $filters : [],
            );
        }, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * POST /api/v1/json-export — Export mit Filtern und Sektionsauswahl.
     */
    public function exportFiltered(Request $request): StreamedResponse|JsonResponse
    {
        set_time_limit(0);
        $validated = $request->validate([
            'sections' => 'sometimes|array',
            'sections.*' => 'string',
            'filter' => 'sometimes|array',
            'filter.status' => 'sometimes|string|in:draft,active,inactive',
            'filter.product_type' => 'sometimes|string',
            'filter.hierarchy_path' => 'sometimes|string',
            'filter.search_text' => 'sometimes|string',
            'filter.updated_after' => 'sometimes|date',
            'filter.skus' => 'sometimes|array',
            'filter.skus.*' => 'string',
            'filter.category_ids' => 'sometimes|array',
            'filter.category_ids.*' => 'string',
            'inline' => 'sometimes|boolean',
        ]);

        $sections = $validated['sections'] ?? [];
        $filters = $validated['filter'] ?? [];
        $inline = $validated['inline'] ?? false;

        if ($inline) {
            $json = $this->exporter->export($sections, $filters);
            return response()->json(json_decode($json, true));
        }

        $fileName = 'pim-export-' . now()->format('Y-m-d_His') . '.json';

        return new StreamedResponse(function () use ($sections, $filters) {
            echo $this->exporter->export($sections, $filters);
        }, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * GET /api/v1/json-export/sections — Verfügbare Sektionen.
     */
    public function sections(): JsonResponse
    {
        return response()->json([
            'data' => JsonFormatExporter::availableSections(),
        ]);
    }

    /**
     * POST /api/v1/json-import — JSON-Datei importieren.
     */
    public function import(Request $request): JsonResponse
    {
        $mode = $request->input('mode', 'update');
        $this->importer->setMode($mode);

        // JSON aus Datei-Upload oder Request-Body
        if ($request->hasFile('file')) {
            $request->validate(['file' => 'required|file|mimetypes:application/json,text/plain']);
            $json = file_get_contents($request->file('file')->getRealPath());
        } else {
            $json = $request->getContent();
        }

        $data = json_decode($json, true);
        if ($data === null) {
            return response()->json([
                'error' => 'Ungültiges JSON: ' . json_last_error_msg(),
            ], 422);
        }

        // Erst validieren
        $validation = $this->importer->validate($data);
        if (!$validation['valid']) {
            return response()->json([
                'error' => 'Validierungsfehler',
                'details' => $validation['errors'],
            ], 422);
        }

        try {
            $result = $this->importer->importData($data);
        } catch (\Throwable $e) {
            Log::channel('import')->error('JSON-Import via REST fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Import fehlgeschlagen: ' . $e->getMessage(),
            ], 500);
        }

        Log::channel('import')->info('JSON-Import via REST abgeschlossen', $result->toArray());

        return response()->json([
            'message' => 'Import erfolgreich',
            'data' => $result->toArray(),
        ]);
    }

    /**
     * POST /api/v1/json-import/validate — JSON validieren ohne zu importieren.
     */
    public function validate(Request $request): JsonResponse
    {
        if ($request->hasFile('file')) {
            $request->validate(['file' => 'required|file|mimetypes:application/json,text/plain']);
            $json = file_get_contents($request->file('file')->getRealPath());
        } else {
            $json = $request->getContent();
        }

        $data = json_decode($json, true);
        if ($data === null) {
            return response()->json([
                'valid' => false,
                'errors' => ['Ungültiges JSON: ' . json_last_error_msg()],
            ], 422);
        }

        $result = $this->importer->validate($data);

        return response()->json($result);
    }

    private function authorizeExportAccess(Request $request, string $exportKey): void
    {
        $ownerId = Cache::get(AsyncJsonExportJob::cacheKey($exportKey) . ':owner');
        $userId  = $request->user()?->id;

        if ($ownerId === null || $userId !== $ownerId) {
            abort(403, 'Kein Zugriff auf diesen Export.');
        }
    }
}
