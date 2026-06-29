<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ContentType;
use App\Services\Content\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Status & Steuerung des Content-Caches für das Backend-Widget.
 * Siehe docs/architecture/23-content-caching.md.
 */
class ContentCacheController extends Controller
{
    public function __construct(private readonly ContentCache $cache) {}

    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', ContentType::class);

        return response()->json(['data' => [
            'enabled' => $this->cache->enabled(),
            'ttl' => $this->cache->ttl(),
            'http_max_age' => (int) config('content.cache.http_max_age', 300),
            'warm_on_publish' => (bool) config('content.cache.warm_on_publish', false),
            'metrics' => $this->cache->metrics(),
        ]]);
    }

    public function clear(Request $request): JsonResponse
    {
        $this->authorize('create', ContentType::class);

        if ($request->boolean('metrics_only')) {
            $this->cache->resetMetrics();

            return response()->json([
                'message' => 'Metriken zurückgesetzt.',
                'metrics' => $this->cache->metrics(),
            ]);
        }

        $this->cache->flushAll();
        if ($request->boolean('reset_metrics')) {
            $this->cache->resetMetrics();
        }

        return response()->json([
            'message' => 'Content-Cache geleert.',
            'metrics' => $this->cache->metrics(),
        ]);
    }
}
