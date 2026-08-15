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
        $this->abortIfDisabled();

        $params = $request->only(['page', 'per_page', 'search', 'domain', 'status', 'lang']);

        $data = $this->client->getUnits($params);
        return response()->json($data);
    }

    /**
     * GET /tms/units/{id} — single unit with translations + usages.
     */
    public function unit(Request $request, string $id): JsonResponse
    {
        $this->abortIfDisabled();

        $data = $this->client->getUnit($id);
        return response()->json($data);
    }

    /**
     * PUT /tms/units/{id}/translations/{lang} — update manual translation.
     */
    public function updateTranslation(Request $request, string $id, string $lang): JsonResponse
    {
        $this->abortIfDisabled();
        abort_unless(auth()->user()?->hasPermissionTo('translations.edit'), 403);

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
        $this->abortIfDisabled();

        $data = $this->client->getStats();
        return response()->json($data);
    }

    /**
     * GET /tms/missing — units without translation for a given language.
     */
    public function missing(Request $request): JsonResponse
    {
        $this->abortIfDisabled();

        $params = $request->only(['page', 'per_page', 'lang']);

        $data = $this->client->getMissing($params);
        return response()->json($data);
    }

    /**
     * POST /tms/retranslate — trigger re-translation via MT provider.
     */
    public function retranslate(Request $request): JsonResponse
    {
        $this->abortIfDisabled();
        abort_unless(auth()->user()?->hasPermissionTo('translations.edit'), 403);

        $request->validate([
            'unit_ids' => 'required|array|max:100',
            'unit_ids.*' => 'string|max:36',
            'target_langs' => 'nullable|array',
            'target_langs.*' => 'string|max:5',
        ]);

        $data = $this->client->retranslate($request->only('unit_ids', 'target_langs'));
        return response()->json($data);
    }

    /**
     * POST /tms/ingest — Ingest vom PIM ins TMS anstoßen.
     *
     * Läuft über die Queue: der Job iteriert über den kompletten
     * Metadaten-Bestand (u.a. alle Hierarchie-Knoten, Attribute und
     * Wertelisten-Einträge) mit einem HTTP-Roundtrip je 200er-Batch und
     * lief synchron zuverlässig in den Request-Timeout.
     */
    public function triggerIngest(): JsonResponse
    {
        $this->abortIfDisabled();
        abort_unless(auth()->user()?->hasPermissionTo('translations.edit'), 403);

        IngestToTmsJob::dispatch();

        return response()->json([
            'queued' => true,
            'message' => 'Ingest wurde eingeplant und läuft im Hintergrund.',
        ], 202);
    }

    /**
     * DELETE /tms/translations — delete all translations (optionally filtered by language).
     */
    public function deleteTranslations(Request $request): JsonResponse
    {
        $this->abortIfDisabled();
        abort_unless(auth()->user()?->hasPermissionTo('translations.edit'), 403);

        $request->validate([
            'target_lang' => 'nullable|string|max:5',
        ]);

        $data = $this->client->deleteAllTranslations($request->query('target_lang'));

        return response()->json($data);
    }

    /**
     * DELETE /tms/units — purge all units (source terms + translations).
     */
    public function purgeUnits(): JsonResponse
    {
        $this->abortIfDisabled();
        abort_unless(auth()->user()?->hasPermissionTo('translations.edit'), 403);

        $data = $this->client->purgeAllUnits();

        return response()->json($data);
    }

    /**
     * POST /tms/sync — Übersetzungen aus dem TMS zurück ins PIM schreiben.
     *
     * Läuft aus denselben Gründen wie triggerIngest() über die Queue.
     */
    public function syncToDatabase(): JsonResponse
    {
        $this->abortIfDisabled();
        abort_unless(auth()->user()?->hasPermissionTo('translations.edit'), 403);

        SyncTmsTranslationsJob::dispatch();

        return response()->json([
            'queued' => true,
            'message' => 'Sync wurde eingeplant und läuft im Hintergrund.',
        ], 202);
    }

    private function abortIfDisabled(): void
    {
        if (!$this->client->isEnabled()) {
            abort(503, 'TMS is not enabled.');
        }
    }
}
