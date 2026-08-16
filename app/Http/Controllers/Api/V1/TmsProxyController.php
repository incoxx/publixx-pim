<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\IngestToTmsJob;
use App\Jobs\SyncTmsTranslationsJob;
use App\Models\Language;
use App\Services\Tms\GlossaryFileService;
use App\Services\Tms\TmsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * PATCH /tms/units/{id} — Terminologie-Angaben pflegen.
     */
    public function updateUnitMetadata(Request $request, string $id): JsonResponse
    {
        $this->abortIfDisabled();
        abort_unless(auth()->user()?->hasPermissionTo('translations.edit'), 403);

        $request->validate([
            'do_not_translate' => 'sometimes|boolean',
            'definition' => 'sometimes|nullable|string|max:2000',
            'word_class' => 'sometimes|nullable|string|max:20',
            'preferred_unit_id' => 'sometimes|nullable|string|size:36',
        ]);

        $data = $this->client->updateUnitMetadata($id, $request->only([
            'do_not_translate', 'definition', 'word_class', 'preferred_unit_id',
        ]));

        // Ablehnungen des TMS (z.B. Synonym-Kette) mit ihrem Grund
        // durchreichen, statt sie als Erfolg auszugeben.
        if (!empty($data['error'])) {
            return response()->json(
                ['message' => $data['message'] ?? 'Aenderung wurde abgelehnt.'],
                (int) ($data['status'] ?? 422),
            );
        }

        return response()->json($data);
    }

    /**
     * GET /tms/stats — translation coverage statistics.
     */
    public function stats(): JsonResponse
    {
        $this->abortIfDisabled();

        $data = $this->client->getStats(Language::targetCodes());

        // Der TmsClient verschluckt Fehler bewusst und liefert dann []. Seit das
        // TM standardmaessig aktiv ist, waere ein nicht erreichbarer Dienst sonst
        // nicht von einem leeren TM zu unterscheiden — die Oberflaeche zeigte
        // stillschweigend "0 Begriffe". Ein echtes leeres TM antwortet mit
        // total_units=0 und ist damit nie ein leeres Array.
        if (empty($data)) {
            abort(503, 'Das Translation Memory ist nicht erreichbar. '
                . 'Pruefen: laeuft der Dienst, stimmt TMS_API_KEY in .env und tms/.env? '
                . 'Details: php artisan tms:status');
        }

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

        // Ohne ausdrueckliche Angabe die Sprachliste des PIM verwenden, damit
        // das TMS nicht auf seine eigene Konfiguration zurueckfaellt.
        $payload = $request->only('unit_ids', 'target_langs');
        if (empty($payload['target_langs'])) {
            $payload['target_langs'] = Language::targetCodes();
        }

        $data = $this->client->retranslate($payload);
        return response()->json($data);
    }

    /**
     * POST /tms/translate-missing — eine neue Zielsprache ausrollen.
     *
     * Plant alle Begriffe ein, die in dieser Sprache noch fehlen. Gedeckelt;
     * `remaining` in der Antwort sagt, wie viel noch offen ist.
     */
    public function translateMissing(Request $request): JsonResponse
    {
        $this->abortIfDisabled();
        abort_unless(auth()->user()?->hasPermissionTo('translations.edit'), 403);

        $request->validate([
            'lang' => 'required|string|max:5',
            'limit' => 'sometimes|integer|min:1|max:5000',
        ]);

        $data = $this->client->translateMissing(
            $request->input('lang'),
            (int) $request->input('limit', 1000),
        );

        return response()->json($data, 202);
    }

    /**
     * GET /tms/glossary/export — Begriffe als XLSX oder CSV herausgeben.
     *
     * Für Übersetzung ohne MT-Anbieter: Datei an den Landesvertrieb oder eine
     * Agentur, ausgefüllt zurück über /tms/glossary/import.
     */
    public function exportGlossary(Request $request, GlossaryFileService $service): StreamedResponse
    {
        $this->abortIfDisabled();

        $request->validate([
            'langs' => 'sometimes|nullable|string|max:200',
            'status' => 'sometimes|in:all,missing,auto,reviewed',
            'domain' => 'sometimes|nullable|string|max:50',
            'format' => 'sometimes|in:xlsx,csv',
        ]);

        $langs = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $request->query('langs')),
        )));

        return $service->export(
            $langs,
            $request->query('status', 'all'),
            $request->query('domain'),
            $request->query('format', 'xlsx'),
        );
    }

    /**
     * POST /tms/glossary/import — ausgefüllte Datei zurückspielen.
     */
    public function importGlossary(Request $request, GlossaryFileService $service): JsonResponse
    {
        $this->abortIfDisabled();
        abort_unless(auth()->user()?->hasPermissionTo('translations.edit'), 403);

        $request->validate([
            'file' => 'required|file|max:51200|mimes:xlsx,xls,csv,txt',
        ]);

        $result = $service->import($request->file('file')->getRealPath());

        return response()->json($result + [
            'message' => sprintf(
                '%d Übersetzungen übernommen, %d unverändert, %d Begriffe nicht gefunden.',
                $result['updated'],
                $result['unchanged'],
                $result['unknown'],
            ),
        ]);
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
