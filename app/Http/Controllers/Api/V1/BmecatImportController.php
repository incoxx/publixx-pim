<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Import\BmecatFormatImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * REST-API für BMEcat-Import.
 *
 * POST /api/v1/bmecat-import           — BMEcat-XML-Datei importieren (mit SSE-Progress)
 * POST /api/v1/bmecat-import/validate  — BMEcat-XML validieren (ohne Import)
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

        // XML aus Datei-Upload oder Request-Body
        if ($request->hasFile('file')) {
            $request->validate([
                'file' => 'required|file|max:204800|mimetypes:application/xml,text/xml,text/plain',
            ]);
            $xml = file_get_contents($request->file('file')->getRealPath());
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
        ini_set('memory_limit', '512M');

        try {
            $result = $this->importer->importFromString($xml, $productType);
        } catch (\Throwable $e) {
            Log::channel('import')->error('BMEcat-Import via REST fehlgeschlagen', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Detaillierte Fehlermeldung statt generischem "Import fehlgeschlagen"
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
     * SSE-Streaming-Import mit Fortschrittsmeldungen.
     */
    private function importStreamed(string $xml, ?string $productType): StreamedResponse
    {
        return new StreamedResponse(function () use ($xml, $productType) {
            // Timeouts und Memory für lang laufende Imports aufheben
            set_time_limit(0);
            ini_set('max_execution_time', '0');
            ini_set('memory_limit', '512M');
            if (function_exists('fastcgi_finish_request')) {
                // FPM: Verbindung offen halten
                ignore_user_abort(true);
            }

            // Disable output buffering for real-time streaming
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $lastEventTime = microtime(true);

            $sendEvent = function (string $event, array $data) use (&$lastEventTime) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                $lastEventTime = microtime(true);
            };

            // SSE-Heartbeat: Hält die Verbindung offen wenn lange kein Event kommt
            // (verhindert nginx proxy_read_timeout)
            $sendHeartbeat = function () use (&$lastEventTime) {
                if ((microtime(true) - $lastEventTime) > 15) {
                    echo ": heartbeat\n\n";
                    flush();
                    $lastEventTime = microtime(true);
                }
            };

            $sendEvent('progress', [
                'phase' => 'parsing',
                'message' => 'XML wird geparst...',
                'current' => 0,
                'total' => 0,
            ]);

            $this->importer->setProgressCallback(
                function (string $phase, int $current, int $total, array $stats) use ($sendEvent) {
                    $sendEvent('progress', [
                        'phase' => $phase,
                        'message' => "Importiere {$phase}...",
                        'current' => $current,
                        'total' => $total,
                        'stats' => $stats,
                    ]);
                }
            );

            // Heartbeat alle 15s senden um nginx proxy_read_timeout zu vermeiden
            $this->importer->setHeartbeatCallback($sendHeartbeat);

            try {
                $result = $this->importer->importFromString($xml, $productType);

                Log::channel('import')->info('BMEcat-Import via REST abgeschlossen', $result->toArray());

                $sendEvent('complete', [
                    'message' => 'Import erfolgreich',
                    'data' => $result->toArray(),
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
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    /**
     * Formatiert Import-Fehler für eine verständliche Rückmeldung an den Benutzer.
     */
    private function formatImportError(\Throwable $e): string
    {
        $message = $e->getMessage();

        // Bekannte Fehlertypen mit verständlichen Meldungen
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

        // Allgemeiner Fehler — Nachricht weitergeben statt generischem Text
        return "Import fehlgeschlagen: {$message}";
    }

    /**
     * Vereinfacht Datenbank-Fehlermeldungen.
     */
    private function simplifyDbError(string $message): string
    {
        // NOT NULL Constraint
        if (preg_match('/NOT NULL constraint.*?\.(\w+)/i', $message, $m)) {
            return "Pflichtfeld \"{$m[1]}\" ist leer. Bitte prüfen Sie die BMEcat-Daten.";
        }

        // Unique Constraint
        if (preg_match('/UNIQUE constraint/i', $message)) {
            return 'Duplikat-Eintrag. Ein Datensatz mit diesem Schlüssel existiert bereits.';
        }

        // Truncation
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
                'file' => 'required|file|max:204800|mimetypes:application/xml,text/xml,text/plain',
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
