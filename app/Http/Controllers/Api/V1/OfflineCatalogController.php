<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\CatalogTemplate;
use App\Services\Export\OfflineCatalogExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OfflineCatalogController extends Controller
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
        $this->authorizeAdmin($request);

        $request->validate([
            'lang' => 'sometimes|string|in:de,en',
            'template_id' => 'sometimes|nullable|string|exists:catalog_templates,id',
        ]);

        $lang = $request->input('lang', 'de');
        $templateId = $request->input('template_id');
        $userId = $request->user()->id;

        try {
            $result = $this->service->generate($lang, $userId, $templateId);

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
     */
    public function progress(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $progress = $this->service->getProgress($userId);

        if (!$progress) {
            return response()->json(['status' => 'idle']);
        }

        return response()->json($progress);
    }

    /**
     * POST /api/v1/admin/offline-catalog/cancel
     */
    public function cancel(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $this->service->cancel($userId);

        return response()->json(['message' => 'Abbruch angefordert.']);
    }

    /**
     * GET /api/v1/admin/offline-catalog/download
     *
     * Lädt die zuletzt erstellte Offline-Katalog-ZIP-Datei herunter.
     * Fallback: Sucht neueste ZIP im exports-Verzeichnis, falls Cache abgelaufen.
     */
    public function download(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $userId = $request->user()->id;
        $progress = $this->service->getProgress($userId);

        // Try from cache first
        if ($progress && $progress['status'] === 'completed' && !empty($progress['path'])) {
            $path = $progress['path'];
            if (file_exists($path)) {
                return response()->download($path, $progress['file_name'] ?? basename($path));
            }
        }

        // Fallback: find newest offline-catalog ZIP on disk
        $exportDir = storage_path('app/exports');
        if (is_dir($exportDir)) {
            $files = glob($exportDir . '/offline-catalog_*.zip');
            if (!empty($files)) {
                usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));
                $latest = $files[0];
                return response()->download($latest, basename($latest));
            }
        }

        return response()->json(['message' => 'Keine Datei verfügbar.'], 404);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Nicht authentifiziert.');
        }

        $isAdmin = $user->hasRole('Admin')
            || (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('*'));

        if (!$isAdmin) {
            abort(403, 'Nur Administratoren können den Offline-Katalog exportieren.');
        }
    }
}
