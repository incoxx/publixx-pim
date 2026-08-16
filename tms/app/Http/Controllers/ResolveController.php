<?php

declare(strict_types=1);

namespace Tms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Tms\Models\TmsTranslation;
use Tms\Models\TmsUnit;

class ResolveController
{
    /**
     * GET /api/resolve?hashes=abc,def&langs=fr,it,es
     *
     * Redis-first resolution with DB fallback.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'hashes' => 'required|string',
            'langs' => 'required|string',
        ]);

        $hashes = array_filter(explode(',', $request->query('hashes')));
        $langs = array_filter(explode(',', $request->query('langs')));

        if (empty($hashes) || empty($langs)) {
            return response()->json([]);
        }

        // Cap input sizes to prevent abuse
        $hashes = array_slice($hashes, 0, 200);
        $langs = array_slice($langs, 0, 20);

        $prefix = config('tms.cache_prefix', 'tms:t:');
        $ttl = config('tms.cache_ttl', 86400);
        $result = [];
        $missingKeys = [];

        // 1. Redis zuerst — als ein einziges MGET statt bis zu 200×20 = 4.000
        // einzelnen GET-Roundtrips (der Sync-Job ruft das pro 500er-Chunk auf).
        $cacheKeys = [];
        $keyIndex = [];
        foreach ($hashes as $hash) {
            $result[$hash] = [];
            foreach ($langs as $lang) {
                $cacheKeys[] = "{$prefix}{$hash}:{$lang}";
                $keyIndex[] = ['hash' => $hash, 'lang' => $lang];
            }
        }

        $cachedValues = empty($cacheKeys) ? [] : Redis::mget($cacheKeys);

        foreach ($keyIndex as $i => $key) {
            $cached = $cachedValues[$i] ?? null;

            if ($cached !== null && $cached !== false) {
                $result[$key['hash']][$key['lang']] = $cached === '__NULL__' ? null : $cached;
            } else {
                $missingKeys[] = $key;
            }
        }

        // 2. DB fallback for cache misses
        if (!empty($missingKeys)) {
            $missingHashes = array_unique(array_column($missingKeys, 'hash'));

            // preferredUnit mitladen: Synonyme erben deren Uebersetzung, sonst
            // laege pro Begriff eine weitere Abfrage an.
            $units = TmsUnit::whereIn('text_hash', $missingHashes)
                ->with([
                    'translations' => fn ($q) => $q->whereIn('target_lang', $langs),
                    'preferredUnit.translations' => fn ($q) => $q->whereIn('target_lang', $langs),
                ])
                ->get()
                ->keyBy('text_hash');

            foreach ($missingKeys as $key) {
                $hash = $key['hash'];
                $lang = $key['lang'];
                $unit = $units->get($hash);
                // Beruecksichtigt "nicht uebersetzen" (Quelltext gilt) und
                // Synonyme (Uebersetzung der Vorzugsbenennung).
                $text = $unit?->effectiveTranslation($lang);

                $result[$hash][$lang] = $text;

                // Warm cache
                $cacheKey = "{$prefix}{$hash}:{$lang}";
                Redis::setex($cacheKey, $ttl, $text ?? '__NULL__');
            }
        }

        return response()->json($result);
    }
}
