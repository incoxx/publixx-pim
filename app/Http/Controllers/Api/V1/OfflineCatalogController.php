<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Export\OfflineCatalogExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

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
            'website_profile_id' => 'sometimes|nullable|uuid|exists:website_profiles,id',
        ]);

        $lang = $request->input('lang', 'de');
        $templateId = $request->input('template_id');
        $websiteProfileId = $request->input('website_profile_id');

        // Kein PHP-Timeout bei großen Exporten (100k+ Produkte)
        set_time_limit(0);
        // Export soll auch bei Verbindungsabbruch (Network Error) weiterlaufen
        ignore_user_abort(true);

        try {
            $result = $this->service->generate($lang, $templateId, $websiteProfileId);

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
    public function buildBundle(Request $request): JsonResponse
    {
        $request->validate([
            'debug' => 'sometimes|boolean',
        ]);

        $debug = $request->boolean('debug');
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

        // Build (mit optionalem Debug-Modus: ohne Minifizierung, mit Source Maps)
        $envVars = 'VITE_BUILD_TARGET=offline';
        if ($debug) {
            $envVars .= ' VITE_DEBUG_BUILD=1';
        }

        $output = [];
        exec(
            "cd " . escapeshellarg($catalogEmbedDir) . " && {$envVars} npx vite build 2>&1",
            $output,
            $code,
        );

        if ($code !== 0) {
            return response()->json(['message' => 'Build fehlgeschlagen.', 'output' => implode("\n", $output)], 500);
        }

        $jsPath = "{$catalogEmbedDir}/dist/catalog-offline.umd.js";
        $cssPath = "{$catalogEmbedDir}/dist/catalog-embed.css";

        // Alte Source Maps aufräumen bei Non-Debug-Build,
        // damit bundleStatus() nicht fälschlich debug=true meldet
        if (!$debug) {
            @unlink("{$catalogEmbedDir}/dist/catalog-offline.umd.js.map");
            @unlink("{$catalogEmbedDir}/dist/catalog-embed.css.map");
        }

        return response()->json([
            'message' => $debug ? 'Offline-Bundle (Debug) erfolgreich gebaut.' : 'Offline-Bundle erfolgreich gebaut.',
            'js_size' => file_exists($jsPath) ? filesize($jsPath) : 0,
            'css_size' => file_exists($cssPath) ? filesize($cssPath) : 0,
            'debug' => $debug,
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
            'debug' => file_exists($jsPath . '.map'),
        ]);
    }

    /**
     * GET /api/v1/admin/offline-catalog/download-bundle
     *
     * Lädt das gebaute Offline-Bundle als ZIP herunter (JS + CSS + ggf. Source Maps).
     * Als ZIP verpackt, damit Antivirus-Software die JS-Datei nicht blockiert.
     */
    public function downloadBundle(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['message' => 'Nicht authentifiziert.'], 401);
        }

        $distDir = base_path('catalog-embed/dist');
        $jsPath = "{$distDir}/catalog-offline.umd.js";

        if (!file_exists($jsPath)) {
            return response()->json(['message' => 'Kein Bundle vorhanden.'], 404);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'bundle_') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFile($jsPath, 'catalog-offline.umd.js');

        $cssPath = "{$distDir}/catalog-embed.css";
        if (file_exists($cssPath)) {
            $zip->addFile($cssPath, 'catalog-embed.css');
        }
        if (file_exists("{$jsPath}.map")) {
            $zip->addFile("{$jsPath}.map", 'catalog-offline.umd.js.map');
        }

        $zip->close();

        return response()->download($zipPath, 'catalog-offline-bundle.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend();
    }

    /**
     * GET /api/v1/admin/offline-catalog/preview
     *
     * Entpackt die ZIP und leitet auf die index.html weiter.
     */
    public function preview(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['message' => 'Nicht authentifiziert.'], 401);
        }

        $previewDir = storage_path('app/offline-preview');
        if (!is_dir($previewDir) || !file_exists("{$previewDir}/index.html")) {
            return response()->json(['message' => 'Keine Preview vorhanden. Bitte zuerst einen Export generieren.'], 404);
        }

        // Redirect auf die Asset-Route für index.html
        return redirect(url('/api/v1/admin/offline-catalog/preview-asset/index.html'));
    }

    /**
     * GET /api/v1/admin/offline-catalog/preview-asset/{path}
     *
     * Liefert eine statische Datei aus dem entpackten Preview-Verzeichnis.
     * Keine Auth-Prüfung nötig — Zugriff erfordert Kenntnis der URL,
     * und die Daten entsprechen dem öffentlichen Katalog.
     */
    public function previewAsset(Request $request, string $path)
    {
        // Path-Traversal verhindern
        $path = ltrim($path, '/');
        if (str_contains($path, '..')) {
            return response('Ungültiger Pfad.', 400);
        }

        $previewDir = storage_path('app/offline-preview');
        $filePath = realpath("{$previewDir}/{$path}");

        // Sicherstellen, dass der aufgelöste Pfad innerhalb des Preview-Verzeichnisses liegt
        if (!$filePath || !str_starts_with($filePath, realpath($previewDir))) {
            return response('Datei nicht gefunden.', 404);
        }

        if (!is_file($filePath)) {
            return response('Datei nicht gefunden.', 404);
        }

        $mimeTypes = [
            'html' => 'text/html',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
        ];

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

        return response()->file($filePath, [
            'Content-Type' => $mime,
            'Cache-Control' => $ext === 'html' ? 'no-store' : 'public, max-age=3600',
        ]);
    }

    /**
     * DELETE /api/v1/admin/offline-catalog/cleanup
     *
     * Löscht alle generierten ZIPs und das Preview-Verzeichnis.
     */
    public function cleanup(Request $request): JsonResponse
    {
        $deletedFiles = 0;
        $freedBytes = 0;

        // 1. ZIP-Dateien in storage/app/exports/offline-catalog_*.zip löschen
        $exportsDir = storage_path('app/exports');
        if (is_dir($exportsDir)) {
            foreach (glob("{$exportsDir}/offline-catalog_*.zip") as $file) {
                $freedBytes += filesize($file);
                unlink($file);
                $deletedFiles++;
            }
        }

        // 2. Preview-Verzeichnis löschen
        $previewDir = storage_path('app/offline-preview');
        if (is_dir($previewDir)) {
            $this->deleteDirectory($previewDir);
            $deletedFiles++;
        }

        // 3. Temporäre Build-Verzeichnisse löschen
        $tmpDir = storage_path('app/tmp');
        if (is_dir($tmpDir)) {
            foreach (glob("{$tmpDir}/offline-catalog-*") as $dir) {
                if (is_dir($dir)) {
                    $this->deleteDirectory($dir);
                    $deletedFiles++;
                }
            }
        }

        // 4. Cache-Einträge bereinigen
        $this->service->clearProgress();

        return response()->json([
            'message' => 'Aufgeräumt.',
            'deleted_files' => $deletedFiles,
            'freed_bytes' => $freedBytes,
        ]);
    }

    /**
     * GET /api/v1/admin/offline-catalog/status
     *
     * Prüft ob ein Offline-Katalog auf der Platte vorhanden ist.
     * Liefert Metadaten zur letzten Generierung.
     */
    public function status(): JsonResponse
    {
        $result = $this->findLatestZip();

        if (!$result) {
            return response()->json(['available' => false]);
        }

        $previewDir = storage_path('app/offline-preview');
        $hasPreview = is_dir($previewDir) && file_exists("{$previewDir}/index.html");

        return response()->json([
            'available' => true,
            'file_name' => $result['file_name'],
            'file_size' => $result['file_size'],
            'created_at' => $result['created_at'],
            'has_preview' => $hasPreview,
        ]);
    }

    /**
     * GET /api/v1/admin/offline-catalog/download
     *
     * Lädt die zuletzt erstellte Offline-Katalog-ZIP-Datei herunter.
     */
    public function download(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['message' => 'Nicht authentifiziert.'], 401);
        }

        // 1. Versuche neueste ZIP auf der Platte zu finden
        $result = $this->findLatestZip();

        if (!$result) {
            return response()->json(['message' => 'Keine Datei verfügbar.'], 404);
        }

        // Kein Timeout beim Streamen großer Dateien
        set_time_limit(0);

        return response()->download($result['path'], $result['file_name'], [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store',
        ]);
    }

    // ─── Hilfsmethoden ────────────────────────────────────────────

    /**
     * Authentifizierung via Bearer-Header oder ?token= Query-Parameter.
     * Nötig für Routen außerhalb der auth:sanctum Middleware (Browser-Navigation).
     */
    private function resolveUser(Request $request)
    {
        if ($request->user()) {
            return $request->user();
        }

        $token = $request->query('token');
        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken) {
                return $accessToken->tokenable;
            }
        }

        return null;
    }

    /**
     * Findet die neueste offline-catalog_*.zip auf der Platte.
     */
    private function findLatestZip(): ?array
    {
        $exportsDir = storage_path('app/exports');
        if (!is_dir($exportsDir)) {
            return null;
        }

        $files = glob("{$exportsDir}/offline-catalog_*.zip");
        if (empty($files)) {
            return null;
        }

        // Neueste Datei nach Änderungszeit
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $path = $files[0];

        return [
            'path' => $path,
            'file_name' => basename($path),
            'file_size' => filesize($path),
            'created_at' => date('c', filemtime($path)),
        ];
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $action = $fileinfo->isDir() ? 'rmdir' : 'unlink';
            $action($fileinfo->getRealPath());
        }

        rmdir($dir);
    }
}
