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
            $request->validate(['file' => 'required|file|mimetypes:application/xml,text/xml,text/plain']);
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
        try {
            $result = $this->importer->importFromString($xml, $productType);
        } catch (\Throwable $e) {
            Log::channel('import')->error('BMEcat-Import via REST fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Import fehlgeschlagen: ' . $e->getMessage(),
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
            // Disable output buffering for real-time streaming
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $sendEvent = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
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
                ]);

                $sendEvent('error', [
                    'error' => 'Import fehlgeschlagen: ' . $e->getMessage(),
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
     * POST /api/v1/bmecat-import/validate — BMEcat-XML validieren ohne zu importieren.
     */
    public function validate(Request $request): JsonResponse
    {
        if ($request->hasFile('file')) {
            $request->validate(['file' => 'required|file|mimetypes:application/xml,text/xml,text/plain']);
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
