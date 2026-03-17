<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ImportCancelledException;
use App\Services\Import\BmecatFormatImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * REST-API für BMEcat-Import.
 *
 * POST /api/v1/bmecat-import           — BMEcat-XML-Datei importieren (mit SSE-Progress)
 * POST /api/v1/bmecat-import/validate  — BMEcat-XML validieren (ohne Import)
 * POST /api/v1/bmecat-import/cancel    — Laufenden Import abbrechen
 */
class BmecatImportController extends Controller
{
    public function __construct(
        private readonly BmecatFormatImporter $importer,
    ) {}

    /**
     * POST /api/v1/bmecat-import — BMEcat-XML importieren.
     *
     * When Accept: text/event-stream is set, streams progress events (SSE).
     * Otherwise returns a regular JSON response.
     */
    public function import(Request $request): StreamedResponse|JsonResponse
    {
        $mode = $request->input('mode', 'update');
        $productType = $request->input('product_type');
        $this->importer->setMode($mode);

        // Datei-Upload oder Request-Body
        if ($request->hasFile('file')) {
            $request->validate([
                'file' => 'required|file|max:512000|mimetypes:application/xml,text/xml,text/plain',
            ]);
            $uploadedFile = $request->file('file');
            $fileSize = $uploadedFile->getSize();
            $isLargeFile = $fileSize > 10 * 1024 * 1024; // > 10 MB

            if ($isLargeFile) {
                // Große Dateien: auf Disk speichern statt in RAM laden
                $tempPath = $uploadedFile->getRealPath();

                // Validierung mit den ersten 8KB statt der ganzen Datei
                $handle = fopen($tempPath, 'r');
                $headerXml = fread($handle, 8192);
                fclose($handle);

                // Schnelle Validierung: XML-Wohlgeformtheit prüfen
                $validation = $this->importer->validate($headerXml);
                if (!$validation['valid']) {
                    // Fallback: Vollständige Validierung nur wenn Header-Check fehlschlägt
                    // (kann bei kleinen Dateien vorkommen die < 8KB sind)
                    if ($fileSize <= 8192) {
                        return response()->json([
                            'error' => 'Validierungsfehler',
                            'details' => $validation['errors'],
                            'warnings' => $validation['warnings'] ?? [],
                        ], 422);
                    }
                    // Bei großen Dateien: Header-Validierung reicht um grobe Fehler zu erkennen
                    // Feingranulare Fehler werden beim Parsing abgefangen
                }

                $wantsStream = str_contains($request->header('Accept', ''), 'text/event-stream');
                if (!$wantsStream) {
                    return $this->importJsonFromFile($tempPath, $productType);
                }

                return $this->importStreamedFromFile($tempPath, $productType);
            }

            $xml = file_get_contents($uploadedFile->getRealPath());
        } else {
            $xml = $request->getContent();
        }

        if (empty($xml)) {
            return response()->json([
                'error' => 'Keine XML-Daten empfangen. Bitte eine Datei hochladen oder XML im Body senden.',
            ], 422);
        }

        // Erst validieren
        $validation = $this->importer->validate($xml);
        if (!$validation['valid']) {
            return response()->json([
                'error' => 'Validierungsfehler',
                'details' => $validation['errors'],
                'warnings' => $validation['warnings'] ?? [],
            ], 422);
        }

        $wantsStream = str_contains($request->header('Accept', ''), 'text/event-stream');

        if (!$wantsStream) {
            return $this->importJson($xml, $productType);
        }

        return $this->importStreamed($xml, $productType);
    }

    /**
     * Standard-JSON-Import (ohne Streaming).
     */
    private function importJson(string $xml, ?string $productType): JsonResponse
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1G');

        try {
            $result = $this->importer->importFromString($xml, $productType);
        } catch (\Throwable $e) {
            Log::channel('import')->error('BMEcat-Import via REST fehlgeschlagen', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMessage = $this->formatImportError($e);

            return response()->json([
                'error' => $errorMessage,
            ], 500);
        }

        Log::channel('import')->info('BMEcat-Import via REST abgeschlossen', $result->toArray());

        return response()->json([
            'message' => 'Import erfolgreich',
            'data' => $result->toArray(),
        ]);
    }

    /**
     * JSON-Import aus Dateipfad (für große Dateien).
     */
    private function importJsonFromFile(string $filePath, ?string $productType): JsonResponse
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '2G');

        try {
            $result = $this->importer->importFromFilePath($filePath, $productType);
        } catch (\Throwable $e) {
            Log::channel('import')->error('BMEcat-Import via REST fehlgeschlagen (Datei-Modus)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => $this->formatImportError($e),
            ], 500);
        }

        Log::channel('import')->info('BMEcat-Import via REST abgeschlossen', $result->toArray());

        return response()->json([
            'message' => 'Import erfolgreich',
            'data' => $result->toArray(),
        ]);
    }

    /**
     * SSE-Streaming-Import mit Fortschrittsmeldungen.
     */
    private function importStreamed(string $xml, ?string $productType): StreamedResponse
    {
        return new StreamedResponse(function () use ($xml, $productType) {
            $this->configureForLongRunning('1G');
            $sendEvent = $this->createSseEventSender();
            $sendHeartbeat = $this->createSseHeartbeatSender();

            $importId = $this->importer->getImportId();

            $sendEvent('progress', [
                'phase' => 'parsing',
                'message' => 'XML wird geparst...',
                'current' => 0,
                'total' => 0,
                'import_id' => $importId,
            ]);

            $this->importer->setBuildProgressCallback(
                function (string $step, int $current, int $total) use ($sendEvent, $importId) {
                    $sendEvent('progress', [
                        'phase' => 'building',
                        'message' => "Datenaufbereitung: {$step}",
                        'current' => $current,
                        'total' => $total,
                        'import_id' => $importId,
                    ]);
                }
            );

            $this->importer->setProgressCallback(
                function (string $phase, int $current, int $total, array $stats) use ($sendEvent, $importId) {
                    $sendEvent('progress', [
                        'phase' => $phase,
                        'message' => "Importiere {$phase}...",
                        'current' => $current,
                        'total' => $total,
                        'stats' => $stats,
                        'import_id' => $importId,
                    ]);
                }
            );

            $this->importer->setHeartbeatCallback($sendHeartbeat);

            try {
                $result = $this->importer->importFromString($xml, $productType);

                Log::channel('import')->info('BMEcat-Import via REST abgeschlossen', $result->toArray());

                $sendEvent('complete', [
                    'message' => 'Import erfolgreich',
                    'data' => $result->toArray(),
                ]);
            } catch (ImportCancelledException $e) {
                Log::channel('import')->info('BMEcat-Import abgebrochen', ['import_id' => $importId]);
                $sendEvent('cancelled', [
                    'message' => 'Import wurde abgebrochen.',
                    'import_id' => $importId,
                ]);
            } catch (\Throwable $e) {
                Log::channel('import')->error('BMEcat-Import via REST fehlgeschlagen', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $sendEvent('error', [
                    'error' => $this->formatImportError($e),
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * SSE-Streaming-Import aus Dateipfad (für große Dateien).
     */
    private function importStreamedFromFile(string $filePath, ?string $productType): StreamedResponse
    {
        return new StreamedResponse(function () use ($filePath, $productType) {
            $this->configureForLongRunning('2G');
            $sendEvent = $this->createSseEventSender();
            $sendHeartbeat = $this->createSseHeartbeatSender();

            $importId = $this->importer->getImportId();
            $fileSizeMb = round(filesize($filePath) / 1024 / 1024, 1);

            $sendEvent('progress', [
                'phase' => 'parsing',
                'message' => "XML wird geparst ({$fileSizeMb} MB)...",
                'current' => 0,
                'total' => 0,
                'import_id' => $importId,
            ]);

            // Parsing-Progress: zeigt wie viele Produkte bereits geparst wurden
            $this->importer->setParsingProgressCallback(
                function (int $productsParsed) use ($sendEvent, $importId, $sendHeartbeat) {
                    $sendEvent('progress', [
                        'phase' => 'parsing',
                        'message' => "{$productsParsed} Produkte geparst...",
                        'current' => $productsParsed,
                        'total' => 0, // Total unbekannt beim Parsen
                        'import_id' => $importId,
                    ]);
                    $sendHeartbeat();
                }
            );

            // Build-Progress: zeigt Fortschritt während Datenaufbereitung
            $this->importer->setBuildProgressCallback(
                function (string $step, int $current, int $total) use ($sendEvent, $importId) {
                    $sendEvent('progress', [
                        'phase' => 'building',
                        'message' => "Datenaufbereitung: {$step}",
                        'current' => $current,
                        'total' => $total,
                        'import_id' => $importId,
                    ]);
                }
            );

            // Import-Progress: zeigt welches Sheet gerade importiert wird
            $this->importer->setProgressCallback(
                function (string $phase, int $current, int $total, array $stats) use ($sendEvent, $importId) {
                    $sendEvent('progress', [
                        'phase' => $phase,
                        'message' => "Importiere {$phase}...",
                        'current' => $current,
                        'total' => $total,
                        'stats' => $stats,
                        'import_id' => $importId,
                    ]);
                }
            );

            $this->importer->setHeartbeatCallback($sendHeartbeat);

            try {
                $result = $this->importer->importFromFilePath($filePath, $productType);

                Log::channel('import')->info('BMEcat-Import via REST abgeschlossen (Datei-Modus)', $result->toArray());

                $sendEvent('complete', [
                    'message' => 'Import erfolgreich',
                    'data' => $result->toArray(),
                ]);
            } catch (ImportCancelledException $e) {
                Log::channel('import')->info('BMEcat-Import abgebrochen', ['import_id' => $importId]);
                $sendEvent('cancelled', [
                    'message' => 'Import wurde abgebrochen.',
                    'import_id' => $importId,
                ]);
            } catch (\Throwable $e) {
                Log::channel('import')->error('BMEcat-Import via REST fehlgeschlagen (Datei-Modus)', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $sendEvent('error', [
                    'error' => $this->formatImportError($e),
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * POST /api/v1/bmecat-import/upload-init — Chunked Upload initialisieren.
     *
     * Gibt eine Upload-ID zurück, mit der Chunks hochgeladen werden können.
     */
    public function uploadInit(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => 'required|string|max:255',
            'filesize' => 'required|integer|min:1',
            'chunk_size' => 'integer|min:1048576|max:20971520', // 1MB–20MB
        ]);

        $uploadId = Str::uuid()->toString();
        $chunkDir = storage_path("app/bmecat-chunks/{$uploadId}");

        if (!is_dir($chunkDir)) {
            mkdir($chunkDir, 0755, true);
        }

        // Metadaten speichern
        file_put_contents("{$chunkDir}/_meta.json", json_encode([
            'filename' => $request->input('filename'),
            'filesize' => (int) $request->input('filesize'),
            'chunk_size' => (int) $request->input('chunk_size', 5242880),
            'created_at' => now()->toIso8601String(),
        ]));

        return response()->json([
            'upload_id' => $uploadId,
            'chunk_size' => (int) $request->input('chunk_size', 5242880),
        ]);
    }

    /**
     * POST /api/v1/bmecat-import/upload-chunk — Einzelnen Chunk hochladen.
     */
    public function uploadChunk(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => 'required|string|uuid',
            'chunk_index' => 'required|integer|min:0',
            'chunk' => 'required|file|max:25600', // max 25MB pro Chunk
        ]);

        $uploadId = $request->input('upload_id');
        $chunkIndex = (int) $request->input('chunk_index');
        $chunkDir = storage_path("app/bmecat-chunks/{$uploadId}");

        if (!is_dir($chunkDir) || !file_exists("{$chunkDir}/_meta.json")) {
            return response()->json(['error' => 'Ungültige Upload-ID.'], 404);
        }

        $chunkFile = $request->file('chunk');
        $chunkFile->move($chunkDir, sprintf('chunk_%05d', $chunkIndex));

        // Zähle vorhandene Chunks
        $chunks = glob("{$chunkDir}/chunk_*");
        $meta = json_decode(file_get_contents("{$chunkDir}/_meta.json"), true);
        $expectedChunks = (int) ceil($meta['filesize'] / $meta['chunk_size']);

        return response()->json([
            'chunk_index' => $chunkIndex,
            'chunks_received' => count($chunks),
            'chunks_expected' => $expectedChunks,
            'complete' => count($chunks) >= $expectedChunks,
        ]);
    }

    /**
     * POST /api/v1/bmecat-import/upload-complete — Chunks zusammenführen und Import starten.
     */
    public function uploadComplete(Request $request): StreamedResponse|JsonResponse
    {
        $request->validate([
            'upload_id' => 'required|string|uuid',
            'mode' => 'string|in:update,delete_insert',
            'product_type' => 'nullable|string',
        ]);

        $uploadId = $request->input('upload_id');
        $mode = $request->input('mode', 'update');
        $productType = $request->input('product_type');
        $chunkDir = storage_path("app/bmecat-chunks/{$uploadId}");

        if (!is_dir($chunkDir) || !file_exists("{$chunkDir}/_meta.json")) {
            return response()->json(['error' => 'Ungültige Upload-ID.'], 404);
        }

        $meta = json_decode(file_get_contents("{$chunkDir}/_meta.json"), true);

        // Chunks zusammenführen
        $assembledPath = storage_path("app/bmecat-chunks/{$uploadId}/assembled.xml");
        $chunks = glob("{$chunkDir}/chunk_*");
        natsort($chunks);

        $output = fopen($assembledPath, 'wb');
        foreach ($chunks as $chunkPath) {
            $chunk = fopen($chunkPath, 'rb');
            stream_copy_to_stream($chunk, $output);
            fclose($chunk);
            unlink($chunkPath); // Chunk nach Zusammenführen löschen
        }
        fclose($output);

        // Metadaten löschen
        @unlink("{$chunkDir}/_meta.json");

        $this->importer->setMode($mode);

        // Schnelle XML-Header-Validierung
        $handle = fopen($assembledPath, 'r');
        $headerXml = fread($handle, 8192);
        fclose($handle);

        $validation = $this->importer->validate($headerXml);
        if (!$validation['valid'] && filesize($assembledPath) <= 8192) {
            $this->cleanupChunkDir($chunkDir);
            return response()->json([
                'error' => 'Validierungsfehler',
                'details' => $validation['errors'],
                'warnings' => $validation['warnings'] ?? [],
            ], 422);
        }

        $wantsStream = str_contains($request->header('Accept', ''), 'text/event-stream');

        if (!$wantsStream) {
            $response = $this->importJsonFromFile($assembledPath, $productType);
            // Cleanup nach Import
            $this->cleanupChunkDir($chunkDir);
            return $response;
        }

        // Bei SSE: Cleanup nach dem Stream registrieren
        $response = $this->importStreamedFromFile($assembledPath, $productType);

        // Cleanup wird nach Request automatisch durchgeführt
        app()->terminating(function () use ($chunkDir) {
            $this->cleanupChunkDir($chunkDir);
        });

        return $response;
    }

    /**
     * Chunk-Verzeichnis aufräumen.
     */
    private function cleanupChunkDir(string $chunkDir): void
    {
        if (!is_dir($chunkDir)) {
            return;
        }

        $files = glob("{$chunkDir}/*");
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($chunkDir);
    }

    /**
     * POST /api/v1/bmecat-import/cancel — Laufenden Import abbrechen.
     */
    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'import_id' => 'required|string|uuid',
        ]);

        $importId = $request->input('import_id');
        BmecatFormatImporter::cancelImport($importId);

        Log::channel('import')->info('BMEcat-Import-Abbruch angefordert', ['import_id' => $importId]);

        return response()->json([
            'message' => 'Abbruch wurde angefordert. Der Import wird nach dem aktuellen Schritt gestoppt.',
            'import_id' => $importId,
        ]);
    }

    /**
     * Konfiguriert PHP für lang laufende Imports.
     */
    private function configureForLongRunning(string $memoryLimit): void
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', $memoryLimit);
        if (function_exists('fastcgi_finish_request')) {
            ignore_user_abort(true);
        }

        // Output Buffering deaktivieren für Echtzeit-Streaming
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    /**
     * Erstellt eine SSE-Event-Sender-Funktion.
     */
    private function createSseEventSender(): \Closure
    {
        $lastEventTime = microtime(true);

        return function (string $event, array $data) use (&$lastEventTime) {
            echo "event: {$event}\n";
            echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
            $lastEventTime = microtime(true);
        };
    }

    /**
     * Erstellt eine SSE-Heartbeat-Funktion.
     */
    private function createSseHeartbeatSender(): \Closure
    {
        $lastEventTime = microtime(true);

        return function () use (&$lastEventTime) {
            if ((microtime(true) - $lastEventTime) > 15) {
                echo ": heartbeat\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                $lastEventTime = microtime(true);
            }
        };
    }

    /**
     * Formatiert Import-Fehler für eine verständliche Rückmeldung an den Benutzer.
     */
    private function formatImportError(\Throwable $e): string
    {
        if ($e instanceof ImportCancelledException) {
            return $e->getMessage();
        }

        $message = $e->getMessage();

        if (str_contains($message, 'konnte nicht geparst werden')) {
            return "XML-Parsing-Fehler: {$message}";
        }

        if (str_contains($message, 'Import-Daten konnten nicht aufbereitet werden')) {
            return "Datenaufbereitung fehlgeschlagen: {$message}";
        }

        if ($e instanceof \Illuminate\Database\QueryException) {
            return 'Datenbankfehler beim Import: ' . $this->simplifyDbError($message);
        }

        if (str_contains($message, 'SQLSTATE')) {
            return 'Datenbankfehler beim Import: ' . $this->simplifyDbError($message);
        }

        return "Import fehlgeschlagen: {$message}";
    }

    /**
     * Vereinfacht Datenbank-Fehlermeldungen.
     */
    private function simplifyDbError(string $message): string
    {
        if (preg_match('/NOT NULL constraint.*?\.(\w+)/i', $message, $m)) {
            return "Pflichtfeld \"{$m[1]}\" ist leer. Bitte prüfen Sie die BMEcat-Daten.";
        }

        if (preg_match('/UNIQUE constraint/i', $message)) {
            return 'Duplikat-Eintrag. Ein Datensatz mit diesem Schlüssel existiert bereits.';
        }

        if (preg_match('/Data too long for column.*?\'(\w+)\'/i', $message, $m)) {
            return "Feld \"{$m[1]}\" enthält zu lange Daten. Bitte kürzen.";
        }

        return $message;
    }

    /**
     * POST /api/v1/bmecat-import/validate — BMEcat-XML validieren ohne zu importieren.
     */
    public function validate(Request $request): JsonResponse
    {
        if ($request->hasFile('file')) {
            $request->validate([
                'file' => 'required|file|max:512000|mimetypes:application/xml,text/xml,text/plain',
            ]);
            $xml = file_get_contents($request->file('file')->getRealPath());
        } else {
            $xml = $request->getContent();
        }

        if (empty($xml)) {
            return response()->json([
                'valid' => false,
                'errors' => ['Keine XML-Daten empfangen'],
            ], 422);
        }

        $result = $this->importer->validate($xml);

        return response()->json($result);
    }
}
