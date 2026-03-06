<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Export\JsonFormatExporter;
use App\Services\Import\JsonFormatImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
     * GET /api/v1/json-export — Vollexport aller PIM-Daten als JSON-Download.
     */
    public function export(Request $request): StreamedResponse
    {
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

        $result = $this->importer->importData($data);

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
}
