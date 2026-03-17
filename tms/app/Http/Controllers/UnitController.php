<?php

declare(strict_types=1);

namespace Tms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
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

        if ($request->query('status')) {
            $status = $request->query('status');
            $lang = $request->query('lang', 'en');

            if ($status === 'missing') {
                $query->whereDoesntHave('translations', fn ($q) => $q->where('target_lang', $lang));
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
        $unit = TmsUnit::with(['translations', 'usages'])->findOrFail($id);

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
     * GET /api/stats — translation coverage per language.
     */
    public function stats(): JsonResponse
    {
        $totalUnits = TmsUnit::count();
        $targetLangs = config('tms.target_languages', ['en', 'fr', 'es', 'it', 'nl']);

        // Single grouped query instead of N queries per language
        $counts = TmsTranslation::selectRaw('target_lang, status, count(*) as cnt')
            ->whereIn('target_lang', $targetLangs)
            ->groupBy('target_lang', 'status')
            ->get();

        $stats = [];
        foreach ($targetLangs as $lang) {
            $langCounts = $counts->where('target_lang', $lang);
            $translated = $langCounts->sum('cnt');
            $reviewed = $langCounts->where('status', 'reviewed')->sum('cnt');
            $auto = $langCounts->where('status', 'auto')->sum('cnt');

            $stats[$lang] = [
                'total' => $totalUnits,
                'translated' => $translated,
                'reviewed' => $reviewed,
                'auto' => $auto,
                'missing' => $totalUnits - $translated,
                'coverage' => $totalUnits > 0 ? round(($translated / $totalUnits) * 100, 1) : 0,
            ];
        }

        return response()->json([
            'total_units' => $totalUnits,
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

        // Flush Redis cache
        $prefix = config('tms.cache_prefix', 'tms:t:');
        try {
            $keys = Redis::keys("{$prefix}*");
            if (!empty($keys)) {
                // Strip Redis prefix if present
                $cleaned = array_map(fn ($k) => str_replace(config('database.redis.options.prefix', ''), '', $k), $keys);
                Redis::del($cleaned);
            }
        } catch (\Throwable $e) {
            // Cache flush is best-effort
        }

        return response()->json([
            'message' => "Deleted {$count} translations.",
            'deleted' => $count,
        ]);
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
            TranslateUnitJob::dispatch($unitId, $targetLangs);
            $dispatched++;
        }

        return response()->json([
            'message' => "Dispatched {$dispatched} translation jobs.",
            'dispatched' => $dispatched,
        ]);
    }
}
