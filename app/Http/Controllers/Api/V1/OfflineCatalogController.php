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

        // Kein PHP-Timeout bei großen Exporten (100k+ Produkte)
        set_time_limit(0);

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
     * POST /api/v1/admin/offline-catalog/build-bundle
     *
     * Baut das Offline-JS/CSS-Bundle (catalog-offline.umd.js + catalog-embed.css).
     */
    public function buildBundle(): JsonResponse
    {
        $catalogEmbedDir = base_path('catalog-embed');

        if (!is_dir($catalogEmbedDir)) {
            return response()->json(['message' => 'catalog-embed Verzeichnis nicht gefunden.'], 500);
        }

        // Install deps if needed
        if (!is_dir("{$catalogEmbedDir}/node_modules")) {
            exec("cd " . escapeshellarg($catalogEmbedDir) . " && npm install 2>&1", $output, $code);
            if ($code !== 0) {
                return response()->json(['message' => 'npm install fehlgeschlagen.', 'output' => implode("\n", $output)], 500);
            }
        }

        // Build
        $output = [];
        exec(
            "cd " . escapeshellarg($catalogEmbedDir) . " && VITE_BUILD_TARGET=offline npx vite build 2>&1",
            $output,
            $code,
        );

        if ($code !== 0) {
            return response()->json(['message' => 'Build fehlgeschlagen.', 'output' => implode("\n", $output)], 500);
        }

        $jsPath = "{$catalogEmbedDir}/dist/catalog-offline.umd.js";
        $cssPath = "{$catalogEmbedDir}/dist/catalog-embed.css";

        return response()->json([
            'message' => 'Offline-Bundle erfolgreich gebaut.',
            'js_size' => file_exists($jsPath) ? filesize($jsPath) : 0,
            'css_size' => file_exists($cssPath) ? filesize($cssPath) : 0,
        ]);
    }

    /**
     * GET /api/v1/admin/offline-catalog/bundle-status
     *
     * Prüft ob das Offline-Bundle existiert.
     */
    public function bundleStatus(): JsonResponse
    {
        $jsPath = base_path('catalog-embed/dist/catalog-offline.umd.js');
        $cssPath = base_path('catalog-embed/dist/catalog-embed.css');

        return response()->json([
            'built' => file_exists($jsPath),
            'js_size' => file_exists($jsPath) ? filesize($jsPath) : 0,
            'css_size' => file_exists($cssPath) ? filesize($cssPath) : 0,
            'built_at' => file_exists($jsPath) ? date('c', filemtime($jsPath)) : null,
        ]);
    }

    /**
     * GET /api/v1/admin/offline-catalog/download
     *
     * Lädt die zuletzt erstellte Offline-Katalog-ZIP-Datei herunter.
     */
    public function download(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $progress = $this->service->getProgress();

        if (!$progress || $progress['status'] !== 'completed' || empty($progress['path'])) {
            return response()->json(['message' => 'Keine Datei verfügbar.'], 404);
        }

        $path = $progress['path'];
        if (!file_exists($path)) {
            return response()->json(['message' => 'Datei nicht mehr vorhanden.'], 404);
        }

        $fileName = $progress['file_name'] ?? basename($path);
        $fileSize = filesize($path);

        // Kein Timeout beim Streamen großer Dateien
        set_time_limit(0);

        return response()->streamDownload(function () use ($path) {
            // Output-Buffer leeren, damit PHP nicht alles im RAM hält
            if (ob_get_level()) {
                ob_end_clean();
            }

            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return;
            }

            // 8 KB Chunks streamen
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'application/zip',
            'Content-Length' => $fileSize,
            'Cache-Control' => 'no-store',
        ]);
    }
}
