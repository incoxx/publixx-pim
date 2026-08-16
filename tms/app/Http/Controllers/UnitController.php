<?php

declare(strict_types=1);

namespace Tms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\Rule;
use Tms\Jobs\TranslateUnitJob;
use Tms\Models\TmsTranslation;
use Tms\Models\TmsUnit;

class UnitController
{
    /**
     * GET /api/units — paginated list of translation units.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TmsUnit::query()->withCount('translations');

        if ($search = $request->query('search')) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $query->where('source_text', 'LIKE', "%{$escaped}%");
        }

        if ($domain = $request->query('domain')) {
            $query->where('domain', $domain);
        }

        // Die Uebersetzung der gewaehlten Sprache mitliefern — unabhaengig vom
        // Statusfilter. Vorher wurde 'lang' nur ausgewertet, wenn zusaetzlich
        // ein Status gesetzt war; die Sprachauswahl in der Oberflaeche blieb
        // damit folgenlos, und die Liste zeigte ohnehin nur die Quellsprache.
        if ($lang = $request->query('lang')) {
            $query->with(['translations' => fn ($q) => $q->where('target_lang', $lang)]);
        }

        if ($request->query('status')) {
            $status = $request->query('status');
            $lang = $request->query('lang', 'en');

            if ($status === 'missing') {
                // Ohne diese beiden Bedingungen bliebe die Liste dauerhaft mit
                // Begriffen gefuellt, die gar nicht uebersetzt werden sollen.
                $query->whereDoesntHave('translations', fn ($q) => $q->where('target_lang', $lang))
                    ->where('do_not_translate', false)
                    ->whereNull('preferred_unit_id');
            } elseif ($status === 'auto') {
                $query->whereHas('translations', fn ($q) => $q->where('target_lang', $lang)->where('status', 'auto'));
            } elseif ($status === 'reviewed') {
                $query->whereHas('translations', fn ($q) => $q->where('target_lang', $lang)->where('status', 'reviewed'));
            }
        }

        $perPage = min((int) $request->query('per_page', '50'), 200);
        $units = $query->orderBy('source_text')->paginate($perPage);

        return response()->json($units);
    }

    /**
     * GET /api/units/{id} — single unit with translations + usages.
     */
    public function show(string $id): JsonResponse
    {
        $unit = TmsUnit::with(['translations', 'usages', 'preferredUnit', 'synonyms'])->findOrFail($id);

        return response()->json($unit);
    }

    /**
     * PUT /api/units/{id}/translations/{lang} — manual translation update.
     */
    public function updateTranslation(Request $request, string $id, string $lang): JsonResponse
    {
        $request->validate([
            'translation' => 'required|string|max:5000',
        ]);

        $unit = TmsUnit::findOrFail($id);

        $translation = TmsTranslation::updateOrCreate(
            ['tms_unit_id' => $unit->id, 'target_lang' => $lang],
            [
                'translation' => $request->input('translation'),
                'provider' => 'human',
                'status' => 'reviewed',
                'confidence' => 1.00,
                'reviewed_at' => now(),
            ]
        );

        // Update Redis cache
        $prefix = config('tms.cache_prefix', 'tms:t:');
        $ttl = config('tms.cache_ttl', 86400);
        Redis::setex(
            "{$prefix}{$unit->text_hash}:{$lang}",
            $ttl,
            $translation->translation
        );

        return response()->json($translation);
    }

    /**
     * PATCH /api/units/{id} — Terminologie-Angaben pflegen.
     *
     * Alle Felder sind einzeln optional: die Oberflaeche schickt nur, was
     * geaendert wurde.
     */
    public function updateMetadata(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'do_not_translate' => 'sometimes|boolean',
            'definition' => 'sometimes|nullable|string|max:2000',
            'word_class' => ['sometimes', 'nullable', Rule::in(TmsUnit::WORD_CLASSES)],
            'preferred_unit_id' => 'sometimes|nullable|string|size:36',
        ]);

        $unit = TmsUnit::findOrFail($id);

        if (array_key_exists('preferred_unit_id', $validated) && $validated['preferred_unit_id'] !== null) {
            $preferred = TmsUnit::find($validated['preferred_unit_id']);

            // Ein Synonym zeigt genau eine Ebene tief auf seine
            // Vorzugsbenennung. Ketten und Ringe wuerden effectiveTranslation()
            // in eine Endlosschleife schicken.
            if (!$preferred || $preferred->id === $unit->id) {
                return response()->json([
                    'message' => 'Vorzugsbenennung nicht gefunden oder identisch mit dem Begriff selbst.',
                ], 422);
            }
            if ($preferred->isSynonym()) {
                return response()->json([
                    'message' => 'Die gewaehlte Vorzugsbenennung ist selbst ein Synonym. Bitte den Hauptbegriff waehlen.',
                ], 422);
            }
            if ($unit->synonyms()->exists()) {
                return response()->json([
                    'message' => 'Dieser Begriff ist selbst Vorzugsbenennung fuer andere und kann kein Synonym werden.',
                ], 422);
            }
            if ($preferred->source_lang !== $unit->source_lang) {
                return response()->json([
                    'message' => 'Synonyme muessen dieselbe Quellsprache haben.',
                ], 422);
            }
        }

        $unit->fill($validated)->save();

        // Die Kennzeichen aendern, was resolve() ausliefert (Quelltext bei
        // "nicht uebersetzen", Uebersetzung der Vorzugsbenennung bei
        // Synonymen). Der Cache wird deshalb verworfen — eine seltene
        // Pflegeaktion, daher bewusst grob statt schluesselgenau.
        $this->flushTranslationCache();

        return response()->json(
            $unit->load(['translations', 'usages', 'preferredUnit', 'synonyms'])
        );
    }

    /**
     * GET /api/stats — translation coverage per language.
     */
    public function stats(Request $request): JsonResponse
    {
        $totalUnits = TmsUnit::count();

        // Das PIM ist die Quelle der Wahrheit fuer die Sprachliste und gibt sie
        // mit. Ohne Angabe greift die eigene Konfiguration — sonst wuerde die
        // Statistik andere Sprachen zeigen als der Rest der Oberflaeche.
        $requested = array_filter(array_map('trim', explode(',', (string) $request->query('langs'))));
        $targetLangs = !empty($requested)
            ? $requested
            : config('tms.target_languages', ['en', 'fr', 'es', 'it', 'nl']);

        // Bezugsgröße je Zielsprache: Units, die in dieser Sprache bereits
        // vorliegen, sind keine offenen Übersetzungen. TranslateUnitJob
        // überspringt source_lang === target_lang — würden sie mitgezählt,
        // könnte z.B. die en-Abdeckung nie 100 % erreichen, sobald englische
        // Quelltexte ingestiert wurden.
        $unitsPerSourceLang = TmsUnit::selectRaw('source_lang, count(*) as cnt')
            ->groupBy('source_lang')
            ->pluck('cnt', 'source_lang');

        // Terminologie-Kennzeichen aus der Bezugsgroesse nehmen: "nicht
        // uebersetzen" gilt in jeder Sprache als erledigt (der Quelltext steht
        // ueberall gleich), Synonyme erben die Uebersetzung ihrer
        // Vorzugsbenennung. Blieben sie drin, koennte die Abdeckung nie 100 %
        // erreichen und die Liste "Fehlend" waere dauerhaft verstopft.
        $exempt = TmsUnit::where('do_not_translate', true)
            ->orWhereNotNull('preferred_unit_id')
            ->count();

        // Single grouped query instead of N queries per language
        $counts = TmsTranslation::selectRaw('target_lang, status, count(*) as cnt')
            ->whereIn('target_lang', $targetLangs)
            ->groupBy('target_lang', 'status')
            ->get();

        $stats = [];
        foreach ($targetLangs as $lang) {
            $langCounts = $counts->where('target_lang', $lang);
            $translated = (int) $langCounts->sum('cnt');
            $reviewed = (int) $langCounts->where('status', 'reviewed')->sum('cnt');
            $auto = (int) $langCounts->where('status', 'auto')->sum('cnt');

            $relevant = $totalUnits - (int) ($unitsPerSourceLang[$lang] ?? 0) - $exempt;

            $stats[$lang] = [
                'total' => $relevant,
                'translated' => $translated,
                'reviewed' => $reviewed,
                'auto' => $auto,
                'missing' => max(0, $relevant - $translated),
                'coverage' => $relevant > 0 ? round(($translated / $relevant) * 100, 1) : 0,
            ];
        }

        return response()->json([
            'total_units' => $totalUnits,
            'exempt_units' => $exempt,
            'languages' => $stats,
        ]);
    }

    /**
     * GET /api/missing — units without translation for a given language.
     */
    public function missing(Request $request): JsonResponse
    {
        $lang = $request->query('lang', 'en');
        $perPage = min((int) $request->query('per_page', '50'), 200);

        $units = TmsUnit::whereDoesntHave('translations', fn ($q) => $q->where('target_lang', $lang))
            ->where('do_not_translate', false)
            ->whereNull('preferred_unit_id')
            ->orderBy('source_text')
            ->paginate($perPage);

        return response()->json($units);
    }

    /**
     * DELETE /api/translations — delete all translations (optionally filtered by language).
     */
    public function deleteTranslations(Request $request): JsonResponse
    {
        $request->validate([
            'target_lang' => 'nullable|string|max:5',
        ]);

        $query = TmsTranslation::query();

        if ($lang = $request->query('target_lang')) {
            $query->where('target_lang', $lang);
        }

        $count = $query->count();
        $query->delete();

        $this->flushTranslationCache();

        return response()->json([
            'message' => "Deleted {$count} translations.",
            'deleted' => $count,
        ]);
    }

    /**
     * DELETE /api/units — purge all units, translations, and usages.
     */
    public function purgeUnits(): JsonResponse
    {
        $count = TmsUnit::count();

        // Delete in correct order (foreign keys)
        TmsTranslation::query()->delete();
        \Tms\Models\TmsUsage::query()->delete();
        TmsUnit::query()->delete();

        $this->flushTranslationCache();

        return response()->json([
            'message' => "{$count} Begriffe und alle zugehörigen Übersetzungen gelöscht.",
            'deleted' => $count,
        ]);
    }

    /**
     * Leert den Übersetzungs-Cache in Redis.
     *
     * Nutzt SCAN statt KEYS: KEYS ist ein blockierender O(N)-Befehl über den
     * gesamten Keyspace — in Redis-DB 4 liegen zusätzlich die Queue-Daten des
     * TMS, ein Purge hätte damit den Worker mit ausgebremst.
     */
    private function flushTranslationCache(): void
    {
        $prefix = config('tms.cache_prefix', 'tms:t:');
        $redisPrefix = (string) config('database.redis.options.prefix', '');

        try {
            $cursor = '0';

            do {
                [$cursor, $keys] = Redis::scan($cursor, ['match' => "{$prefix}*", 'count' => 500]);

                if (!empty($keys)) {
                    $cleaned = $redisPrefix === ''
                        ? $keys
                        : array_map(fn ($k) => str_replace($redisPrefix, '', $k), $keys);

                    Redis::del($cleaned);
                }
            } while ($cursor !== '0' && $cursor !== 0);
        } catch (\Throwable $e) {
            // Cache flush is best-effort
        }
    }

    /**
     * POST /api/translate-missing — alle Begriffe ohne Übersetzung in einer
     * Zielsprache zur maschinellen Übersetzung einplanen.
     *
     * Das ist der Weg, eine neu hinzugefügte Sprache auszurollen. Bewusst
     * gedeckelt (`limit`) und mit `remaining` in der Antwort: der Aufrufer
     * schleift so lange, bis nichts mehr offen ist, statt bei 500.000 Begriffen
     * die Queue in einem Rutsch zu fluten. Der Aufruf ist idempotent —
     * abgebrochene Läufe kann man einfach wiederholen.
     */
    public function translateMissing(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => 'required|string|max:5',
            'limit' => 'sometimes|integer|min:1|max:5000',
        ]);

        $lang = $request->input('lang');
        $limit = (int) $request->input('limit', 1000);

        // Units ohne Übersetzung in dieser Sprache, Quellsprache ausgenommen —
        // TranslateUnitJob würde sie ohnehin überspringen.
        $query = TmsUnit::where('source_lang', '!=', $lang)
            ->whereDoesntHave('translations', fn ($q) => $q->where('target_lang', $lang));

        $total = (clone $query)->count();

        $dispatched = 0;
        $query->orderBy('id')->limit($limit)->pluck('id')->each(function ($id) use ($lang, &$dispatched) {
            TranslateUnitJob::dispatch($id, [$lang]);
            $dispatched++;
        });

        return response()->json([
            'lang' => $lang,
            'dispatched' => $dispatched,
            'remaining' => max(0, $total - $dispatched),
            'message' => "{$dispatched} Begriffe für '{$lang}' eingeplant.",
        ], 202);
    }

    /**
     * POST /api/retranslate — trigger MT re-translation.
     */
    public function retranslate(Request $request): JsonResponse
    {
        $request->validate([
            'unit_ids' => 'required|array|max:100',
            'unit_ids.*' => 'required|string|uuid',
            'target_langs' => 'nullable|array',
        ]);

        $unitIds = $request->input('unit_ids');
        $targetLangs = $request->input('target_langs', config('tms.target_languages'));

        $dispatched = 0;
        foreach ($unitIds as $unitId) {
            // force: Retranslate ist eine bewusste Nutzeraktion und soll auch
            // vorhandene maschinelle Übersetzungen erneuern (geprüfte nicht).
            TranslateUnitJob::dispatch($unitId, $targetLangs, true);
            $dispatched++;
        }

        return response()->json([
            'message' => "Dispatched {$dispatched} translation jobs.",
            'dispatched' => $dispatched,
        ]);
    }
}
