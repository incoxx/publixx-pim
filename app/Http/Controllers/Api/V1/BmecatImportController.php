<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Import\BmecatFormatImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * REST-API für BMEcat-Import.
 *
 * POST /api/v1/bmecat-import           — BMEcat-XML-Datei importieren
 * POST /api/v1/bmecat-import/validate  — BMEcat-XML validieren (ohne Import)
 */
class BmecatImportController extends Controller
{
    public function __construct(
        private readonly BmecatFormatImporter $importer,
    ) {}

    /**
     * POST /api/v1/bmecat-import — BMEcat-XML importieren.
     */
    public function import(Request $request): JsonResponse
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
