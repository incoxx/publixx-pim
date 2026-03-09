<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\IngestToTmsJob;
use App\Jobs\SyncTmsTranslationsJob;
use App\Services\Tms\TmsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TmsProxyController extends Controller
{
    public function __construct(private readonly TmsClient $client)
    {
    }

    /**
     * GET /tms/units — list translation units (paginated).
     */
    public function units(Request $request): JsonResponse
    {
        $data = $this->client->getUnits($request->query());
        return response()->json($data);
    }

    /**
     * GET /tms/units/{id} — single unit with translations + usages.
     */
    public function unit(string $id): JsonResponse
    {
        $data = $this->client->getUnit($id);
        return response()->json($data);
    }

    /**
     * PUT /tms/units/{id}/translations/{lang} — update manual translation.
     */
    public function updateTranslation(Request $request, string $id, string $lang): JsonResponse
    {
        $request->validate([
            'translation' => 'required|string|max:5000',
        ]);

        $data = $this->client->updateTranslation($id, $lang, $request->only('translation'));
        return response()->json($data);
    }

    /**
     * GET /tms/stats — translation coverage statistics.
     */
    public function stats(): JsonResponse
    {
        $data = $this->client->getStats();
        return response()->json($data);
    }

    /**
     * GET /tms/missing — units without translation for a given language.
     */
    public function missing(Request $request): JsonResponse
    {
        $data = $this->client->getMissing($request->query());
        return response()->json($data);
    }

    /**
     * POST /tms/retranslate — trigger re-translation via MT provider.
     */
    public function retranslate(Request $request): JsonResponse
    {
        $request->validate([
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'string',
            'target_langs' => 'nullable|array',
        ]);

        $data = $this->client->retranslate($request->only('unit_ids', 'target_langs'));
        return response()->json($data);
    }

    /**
     * POST /tms/ingest — trigger manual ingest from PIM to TMS.
     */
    public function triggerIngest(): JsonResponse
    {
        IngestToTmsJob::dispatch();

        return response()->json([
            'message' => 'TMS ingest job dispatched.',
        ]);
    }

    /**
     * POST /tms/sync — sync translations from TMS back into PIM database.
     */
    public function syncToDatabase(): JsonResponse
    {
        SyncTmsTranslationsJob::dispatch();

        return response()->json([
            'message' => 'TMS sync job dispatched.',
        ]);
    }
}
