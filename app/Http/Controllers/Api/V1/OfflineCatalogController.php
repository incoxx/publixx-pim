<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Export\OfflineCatalogExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;

class OfflineCatalogController extends BaseController
{
    public function __construct(
        private readonly OfflineCatalogExportService $service,
    ) {}

    /**
     * POST /api/v1/admin/offline-catalog/generate
     *
     * Startet die Generierung des Offline-Katalogs.
     * Läuft synchron (innerhalb des Request-Timeouts).
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => 'sometimes|string|in:de,en',
            'template_id' => 'sometimes|nullable|uuid',
        ]);

        $lang = $request->input('lang', 'de');
        $templateId = $request->input('template_id');

        try {
            $result = $this->service->generate($lang, $templateId);

            if ($result['cancelled']) {
                return response()->json([
                    'message' => 'Export wurde abgebrochen.',
                    'cancelled' => true,
                    'total_products' => $result['total_products'],
                ], 200);
            }

            if (!$result['path']) {
                return response()->json([
                    'message' => 'Keine aktiven Produkte gefunden.',
                    'total_products' => 0,
                ], 200);
            }

            return response()->json([
                'message' => 'Offline-Katalog erfolgreich erstellt.',
                'file_name' => $result['file_name'],
                'total_products' => $result['total_products'],
                'chunks' => $result['chunks'],
                'duration' => $result['duration'],
                'file_size' => $result['file_size'],
            ]);
        } catch (\Throwable $e) {
            Log::channel('export')->error('Offline-Katalog-Export fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Export fehlgeschlagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/admin/offline-catalog/progress
     *
     * Gibt den aktuellen Fortschritt zurück.
     */
    public function progress(): JsonResponse
    {
        $progress = $this->service->getProgress();

        if (!$progress) {
            return response()->json(['status' => 'idle']);
        }

        return response()->json($progress);
    }

    /**
     * POST /api/v1/admin/offline-catalog/cancel
     *
     * Bricht den laufenden Export ab.
     */
    public function cancel(): JsonResponse
    {
        $this->service->cancel();

        return response()->json(['message' => 'Abbruch angefordert.']);
    }

    /**
     * GET /api/v1/admin/offline-catalog/download
     *
     * Lädt die zuletzt erstellte Offline-Katalog-ZIP-Datei herunter.
     */
    public function download(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $progress = $this->service->getProgress();

        if (!$progress || $progress['status'] !== 'completed' || empty($progress['path'])) {
            return response()->json(['message' => 'Keine Datei verfügbar.'], 404);
        }

        $path = $progress['path'];
        if (!file_exists($path)) {
            return response()->json(['message' => 'Datei nicht mehr vorhanden.'], 404);
        }

        return response()->download($path, $progress['file_name'] ?? basename($path));
    }
}
