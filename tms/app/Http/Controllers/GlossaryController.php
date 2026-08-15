<?php

declare(strict_types=1);

namespace Tms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Tms\Models\TmsTranslation;
use Tms\Models\TmsUnit;

/**
 * Bulk-Zugriff auf das Translation Memory als Glossar.
 *
 * Grundlage für den Datei-Austausch (XLSX/CSV) ohne maschinelle Übersetzung:
 * herausgeben, extern übersetzen lassen, zurückspielen.
 *
 * Die Datei selbst erzeugt das PIM — dort liegt die Tabellen-Bibliothek und
 * dort ist auch die Sprachliste zu Hause. Der TMS liefert nur die Daten.
 */
class GlossaryController
{
    /**
     * GET /api/glossary
     *
     * Begriffe mit ihren Übersetzungen und Verwendungsstellen.
     *
     * Query:
     *   - langs   (Pflicht) Zielsprachen, kommagetrennt
     *   - status  all | missing | auto | reviewed  — bezogen auf die ERSTE Sprache
     *   - domain  optionaler Filter auf den Bereich
     *   - page / per_page
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'langs' => 'required|string|max:200',
            'status' => 'sometimes|in:all,missing,auto,reviewed',
            'domain' => 'sometimes|nullable|string|max:50',
            'per_page' => 'sometimes|integer|min:1|max:2000',
        ]);

        $langs = array_values(array_filter(array_map('trim', explode(',', $request->query('langs')))));
        $status = $request->query('status', 'all');
        $perPage = min((int) $request->query('per_page', '1000'), 2000);

        $query = TmsUnit::query()
            ->with([
                'translations' => fn ($q) => $q->whereIn('target_lang', $langs),
                'usages' => fn ($q) => $q->orderBy('entity_type')->limit(5),
            ]);

        if ($domain = $request->query('domain')) {
            $query->where('domain', $domain);
        }

        // Der Statusfilter bezieht sich auf die erste angeforderte Sprache —
        // bei "übersetze mir Finnisch" ist genau das die Absicht.
        $primary = $langs[0] ?? null;

        if ($primary && $status !== 'all') {
            if ($status === 'missing') {
                $query->where('source_lang', '!=', $primary)
                    ->whereDoesntHave('translations', fn ($q) => $q->where('target_lang', $primary));
            } else {
                $query->whereHas(
                    'translations',
                    fn ($q) => $q->where('target_lang', $primary)->where('status', $status),
                );
            }
        }

        $units = $query->orderBy('domain')->orderBy('source_text')->paginate($perPage);

        $rows = collect($units->items())->map(fn (TmsUnit $unit) => [
            'hash' => $unit->text_hash,
            'domain' => $unit->domain,
            'source_lang' => $unit->source_lang,
            'source_text' => $unit->source_text,
            'usages' => $unit->usages
                ->map(fn ($u) => trim("{$u->entity_type}: " . ($u->entity_label ?? $u->entity_id)))
                ->unique()
                ->values()
                ->all(),
            'translations' => $unit->translations
                ->mapWithKeys(fn ($t) => [$t->target_lang => [
                    'text' => $t->translation,
                    'status' => $t->status,
                ]])
                ->all(),
        ])->all();

        return response()->json([
            'data' => $rows,
            'current_page' => $units->currentPage(),
            'last_page' => $units->lastPage(),
            'total' => $units->total(),
        ]);
    }

    /**
     * POST /api/glossary
     *
     * Übersetzungen aus einer externen Datei zurückspielen.
     *
     * Body: { items: [{hash, lang, translation}] }
     *
     * Zurückgespielte Werte gelten als menschlich geprüft (`reviewed`) und sind
     * damit gegen späteres maschinelles Überschreiben geschützt — TranslateUnitJob
     * fasst `reviewed` nie an.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|max:2000',
            'items.*.hash' => 'required|string|size:64',
            'items.*.lang' => 'required|string|max:5',
            'items.*.translation' => 'required|string|max:10000',
        ]);

        $items = $request->input('items');
        $prefix = config('tms.cache_prefix', 'tms:t:');
        $ttl = config('tms.cache_ttl', 86400);

        // Units in einem Rutsch laden statt je Zeile
        $units = TmsUnit::whereIn('text_hash', array_unique(array_column($items, 'hash')))
            ->get()
            ->keyBy('text_hash');

        $updated = 0;
        $unchanged = 0;
        $unknown = [];

        foreach ($items as $item) {
            $unit = $units->get($item['hash']);

            if (!$unit) {
                $unknown[] = $item['hash'];
                continue;
            }

            $text = trim($item['translation']);
            if ($text === '') {
                continue;
            }

            $existing = TmsTranslation::where('tms_unit_id', $unit->id)
                ->where('target_lang', $item['lang'])
                ->first();

            // Unveränderte Zellen NICHT als geprüft markieren. Sonst würde ein
            // Export-Import-Durchlauf ohne jede Bearbeitung den gesamten
            // maschinellen Bestand zu "geprüft" befördern — und damit jede
            // spätere Korrektur durch den MT-Anbieter blockieren.
            if ($existing && $existing->translation === $text) {
                $unchanged++;
                continue;
            }

            TmsTranslation::updateOrCreate(
                ['tms_unit_id' => $unit->id, 'target_lang' => $item['lang']],
                [
                    'translation' => $text,
                    'provider' => 'human',
                    'status' => 'reviewed',
                    'confidence' => 1.00,
                    'reviewed_at' => now(),
                ],
            );

            Redis::setex("{$prefix}{$unit->text_hash}:{$item['lang']}", $ttl, $text);
            $updated++;
        }

        return response()->json([
            'updated' => $updated,
            'unchanged' => $unchanged,
            'unknown' => count($unknown),
            'unknown_hashes' => array_slice(array_unique($unknown), 0, 20),
            'message' => "{$updated} Übersetzungen übernommen, {$unchanged} unverändert.",
        ]);
    }
}
