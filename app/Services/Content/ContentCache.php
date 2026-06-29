<?php

declare(strict_types=1);

namespace App\Services\Content;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

/**
 * Caching der gerenderten Content-Ausgaben (Sitemap, Seite, Produktseite).
 * Siehe docs/architecture/23-content-caching.md.
 *
 * - Tag-Store (Redis/Memcached/Array): gezielte Invalidierung per Tag.
 * - Nicht-Tag-Store (file/database): Fallback über einen globalen Versions-Key.
 *
 * Reverse-Index product → Seiten: damit bestehende Produkt-/Preis-/Medien-/
 * Attribut-Änderungen die einbettenden Content-Seiten mit-invalidieren.
 */
class ContentCache
{
    /** Wurde der letzte remember()-Aufruf aus dem Cache bedient? (HIT/MISS) */
    public bool $lastHit = false;

    public function enabled(): bool
    {
        return (bool) config('content.cache.enabled', true);
    }

    public function ttl(): int
    {
        return (int) config('content.cache.ttl', 3600);
    }

    private function taggable(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }

    /**
     * Wert cachen. Tags müssen beim Lesen und Schreiben identisch sein
     * (Laravel-Semantik) → nur feste, vorab bekannte Tags übergeben.
     */
    public function remember(string $key, array $tags, ?int $ttl, Closure $callback): mixed
    {
        if (! $this->enabled()) {
            $this->lastHit = false;

            return $callback();
        }

        $ttl = $ttl ?? $this->ttl();

        if ($this->taggable()) {
            $this->lastHit = Cache::tags($tags)->has($key);
            $this->recordMetric();

            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }

        // Fallback ohne Tag-Support: Versions-Key
        $vKey = $key . ':v' . $this->version();
        $this->lastHit = Cache::has($vKey);
        $this->recordMetric();

        return Cache::remember($vKey, $ttl, $callback);
    }

    /** Einträge mit (irgend)einem dieser Tags verwerfen. */
    public function flushTags(array $tags): void
    {
        if (! $this->enabled()) {
            return;
        }
        if ($this->taggable()) {
            Cache::tags($tags)->flush();

            return;
        }
        $this->bumpVersion();
    }

    /** Kompletten Content-Cache verwerfen. */
    public function flushAll(): void
    {
        $this->flushTags(['content']);
        if (! $this->taggable()) {
            $this->bumpVersion();
        }
    }

    // ─── Reverse-Index: welche Seiten betten ein Produkt ein? ─────────

    public function indexProductPages(array $productIds, string $pageId): void
    {
        if (! $this->enabled() || ! $this->taggable()) {
            return; // Fallback invalidiert global über die Version
        }
        foreach (array_unique($productIds) as $pid) {
            $key = "content:rev:product:{$pid}";
            $pages = Cache::get($key, []);
            if (! in_array($pageId, $pages, true)) {
                $pages[] = $pageId;
                Cache::put($key, $pages, $this->ttl() * 2);
            }
        }
    }

    /**
     * Produktbezogenen Content invalidieren: die Produkt-Detailseite sowie
     * alle Content-Seiten, die das Produkt einbetten (Reverse-Index).
     */
    public function flushProduct(string $productId): void
    {
        if (! $this->enabled()) {
            return;
        }
        if (! $this->taggable()) {
            $this->bumpVersion();

            return;
        }

        Cache::tags(["content:product:{$productId}"])->flush();

        $key = "content:rev:product:{$productId}";
        foreach (Cache::get($key, []) as $pageId) {
            Cache::tags(["content:page:{$pageId}"])->flush();
        }
        Cache::forget($key);
    }

    // ─── Metriken (Hit-Rate) ──────────────────────────────────────────

    private function recordMetric(): void
    {
        $key = $this->lastHit ? 'content:metrics:hits' : 'content:metrics:misses';
        if (Cache::increment($key) === false) {
            Cache::forever($key, 1);
        }
    }

    /** @return array{hits:int, misses:int, total:int, hit_rate:float} */
    public function metrics(): array
    {
        $hits = (int) Cache::get('content:metrics:hits', 0);
        $misses = (int) Cache::get('content:metrics:misses', 0);
        $total = $hits + $misses;

        return [
            'hits' => $hits,
            'misses' => $misses,
            'total' => $total,
            'hit_rate' => $total > 0 ? round($hits / $total, 4) : 0.0,
        ];
    }

    public function resetMetrics(): void
    {
        Cache::forget('content:metrics:hits');
        Cache::forget('content:metrics:misses');
    }

    // ─── Versions-Fallback ────────────────────────────────────────────

    private function version(): int
    {
        return (int) Cache::get('content:cache_version', 1);
    }

    private function bumpVersion(): void
    {
        Cache::forever('content:cache_version', $this->version() + 1);
    }
}
