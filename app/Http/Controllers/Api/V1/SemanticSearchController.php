<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Search\HybridSearchService;
use App\Services\Search\MeilisearchClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Semantische Schnellsuche (Meilisearch Hybrid Search).
 *
 * Versteht natürlichsprachige Anfragen („smartes Telefon mit 8 Zoll
 * Bildschirm") und liefert gerankte Treffer als JSON. Quantitative
 * Angaben werden deterministisch zu harten Filtern; das semantische
 * Verstehen unscharfer Begriffe übernimmt der Vektor-Anteil.
 */
class SemanticSearchController extends Controller
{
    /**
     * POST /api/v1/semantic-search
     */
    public function search(Request $request, HybridSearchService $searchService): JsonResponse
    {
        if (!config('meilisearch.enabled')) {
            return response()->json([
                'message' => 'Semantische Suche ist nicht aktiviert (MEILISEARCH_ENABLED).',
            ], 503);
        }

        $maxLimit = (int) config('meilisearch.search.max_limit', 100);

        $validated = $request->validate([
            'q' => 'nullable|string|max:500',
            'mode' => 'nullable|string|in:auto,hybrid,keyword,filter',
            'filters' => 'sometimes|array',
            'filters.*.attribute' => 'required|string|max:200',
            'filters.*.operator' => 'required|string|in:eq,neq,gt,gte,lt,lte,between',
            'filters.*.value' => 'required',
            'facets' => 'sometimes|array',
            'facets.*' => 'string|max:200',
            'limit' => "nullable|integer|min:1|max:{$maxLimit}",
            'offset' => 'nullable|integer|min:0',
            'semantic_ratio' => 'nullable|numeric|min:0|max:1',
            'status' => 'nullable|string|max:50',
        ]);

        try {
            $result = $searchService->search(
                q: $validated['q'] ?? '',
                explicitFilters: $validated['filters'] ?? [],
                facets: $validated['facets'] ?? [],
                limit: (int) ($validated['limit'] ?? config('meilisearch.search.default_limit', 20)),
                offset: (int) ($validated['offset'] ?? 0),
                semanticRatio: isset($validated['semantic_ratio'])
                    ? (float) $validated['semantic_ratio']
                    : null,
                mode: $validated['mode'] ?? 'auto',
                status: array_key_exists('status', $validated)
                    ? ($validated['status'] ?: null)
                    : 'active',
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('SemanticSearch fehlgeschlagen', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Suche derzeit nicht verfügbar.',
            ], 503);
        }

        return response()->json($result);
    }

    /**
     * GET /api/v1/semantic-search/health — Status von Index und Embedder.
     */
    public function health(MeilisearchClient $client): JsonResponse
    {
        if (!config('meilisearch.enabled')) {
            return response()->json(['enabled' => false, 'healthy' => false]);
        }

        $healthy = $client->isHealthy();
        $stats = null;

        if ($healthy) {
            try {
                $stats = $client->stats((string) config('meilisearch.index', 'products'));
            } catch (\Throwable) {
                $stats = null;
            }
        }

        return response()->json([
            'enabled' => true,
            'healthy' => $healthy,
            'index' => config('meilisearch.index', 'products'),
            'documents' => $stats['numberOfDocuments'] ?? null,
            'indexing' => $stats['isIndexing'] ?? null,
            'embedder' => config('meilisearch.embedder.enabled')
                ? config('meilisearch.embedder.source')
                : null,
        ]);
    }
}
