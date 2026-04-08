<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ApiTemplate;
use App\Models\ApiTemplateAccessLog;
use App\Services\ApiDesigner\ApiDesignerService;
use App\Services\ApiDesigner\GraphqlDesignerService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiStreamController extends Controller
{
    public function __construct(
        private readonly ApiDesignerService $apiDesignerService,
        private readonly GraphqlDesignerService $graphqlDesignerService,
        private readonly RateLimiter $rateLimiter,
    ) {}

    /**
     * GET|POST /api/v1/api-streams/{slug} — Stream product data as JSON or execute GraphQL query.
     */
    public function stream(Request $request, string $slug): StreamedResponse|JsonResponse
    {
        $template = ApiTemplate::where('slug', $slug)->active()->firstOrFail();

        $this->authenticateStream($request, $template);

        $startTime = microtime(true);

        // Per-Template Rate-Limiting
        $rateLimitResponse = $this->enforceRateLimit($request, $template);
        if ($rateLimitResponse !== null) {
            return $rateLimitResponse;
        }

        if ($template->output_format === 'graphql') {
            $response = $this->handleGraphql($request, $template);
        } else {
            $limit  = $request->integer('limit', 0) ?: null;   // 0 = kein Limit
            $offset = max(0, $request->integer('offset', 0));
            $since  = $this->parseSince($request->input('since'));
            $sortField = $request->input('sort');
            $sortOrder = $request->input('order', 'asc');
            $fields = array_filter(array_map('trim', explode(',', $request->input('fields', ''))));

            $nav = $this->buildNavigationUrls($request, $limit, $offset);

            $response = $this->apiDesignerService->stream(
                $template,
                $template->searchProfile,
                $limit,
                $offset,
                $since,
                $sortField ?: null,
                $sortOrder,
                $nav,
                $fields,
            );
        }

        $this->logAccess($template, $request, $startTime);

        return $response;
    }

    /**
     * GraphQL-Query oder -Mutation aus Request lesen und ausführen.
     */
    private function handleGraphql(Request $request, ApiTemplate $template): JsonResponse
    {
        // Query aus POST-Body oder GET-Parameter lesen
        $query = $request->input('query');
        $variables = $request->input('variables');

        if (!$query) {
            return response()->json([
                'errors' => [['message' => 'GraphQL-Query fehlt. Sende {"query": "{ ... }"} als JSON-Body.']],
            ], 400);
        }

        // Direction-Guard: Export-only Templates dürfen keine Mutations ausführen
        $direction = $template->direction ?? 'export';
        if ($direction === 'export' && $this->isMutationQuery($query)) {
            return response()->json([
                'errors' => [['message' => 'Dieses API-Template unterstützt keine Mutationen. Richtung muss "import" oder "bidirectional" sein.']],
            ], 405);
        }

        if (is_string($variables)) {
            $variables = json_decode($variables, true);
        }

        try {
            $result = $this->graphqlDesignerService->execute($template, $query, $variables);

            return response()->json($result, 200, [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-cache',
            ]);
        } catch (\GraphQL\Error\SyntaxError $e) {
            return response()->json([
                'errors' => [['message' => 'GraphQL Syntax-Fehler: ' . $e->getMessage()]],
            ], 400);
        }
    }

    /**
     * POST /api/v1/api-streams/{slug}/import — Import data via GraphQL-Mutation oder JSON.
     */
    public function import(Request $request, string $slug): JsonResponse
    {
        $template = ApiTemplate::where('slug', $slug)->active()->firstOrFail();

        if (!in_array($template->direction, ['import', 'bidirectional'])) {
            abort(405, 'Dieses API-Template unterstützt keinen Import.');
        }

        $this->authenticateStream($request, $template);

        $startTime = microtime(true);

        // GraphQL-Templates: Mutations über die GraphQL-Engine ausführen
        if ($template->output_format === 'graphql') {
            $response = $this->handleGraphql($request, $template);
        } else {
            // JSON-Import: Noch nicht implementiert
            $response = response()->json([
                'message' => 'JSON-Import wird in einer zukünftigen Version verfügbar sein.',
            ], 501);
        }

        $this->logAccess($template, $request, $startTime);

        return $response;
    }

    /**
     * Prüft ob ein GraphQL-Query-String eine Mutation ist.
     */
    private function isMutationQuery(string $query): bool
    {
        return (bool) preg_match('/^\s*mutation\b/i', $query);
    }

    private function authenticateStream(Request $request, ApiTemplate $template): void
    {
        if ($template->auth_type === 'none') {
            return;
        }

        if ($template->auth_type === 'api_key') {
            $key = $request->header('X-Api-Key');
            if (! $key) {
                abort(401, 'X-Api-Key Header fehlt.');
            }
            if (hash('sha256', $key) !== $template->api_key) {
                abort(401, 'Ungültiger API-Key.');
            }

            return;
        }

        // Bearer token — standard Sanctum auth
        if (!$request->user()) {
            abort(401, 'Authentifizierung erforderlich.');
        }
    }

    /**
     * Per-Template Rate-Limiting — nutzt das `rate_limit`-Feld aus dem Template (Req/Minute).
     */
    private function enforceRateLimit(Request $request, ApiTemplate $template): ?JsonResponse
    {
        $maxPerMinute = max(1, (int) $template->rate_limit);
        $key = 'api_stream:' . $template->id . ':' . ($request->header('X-Api-Key') ?? $request->ip());

        if ($this->rateLimiter->tooManyAttempts($key, $maxPerMinute)) {
            $retryAfter = $this->rateLimiter->availableIn($key);

            return response()->json([
                'error' => 'Rate limit exceeded',
                'retry_after' => $retryAfter,
            ], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After'          => (string) $retryAfter,
                'X-RateLimit-Limit'    => (string) $maxPerMinute,
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        $this->rateLimiter->hit($key, 60);

        return null;
    }

    /**
     * `?since=` — ISO-8601 oder Y-m-d in DateTimeImmutable umwandeln. Null bei ungültigem Wert.
     */
    private function parseSince(?string $since): ?\DateTimeImmutable
    {
        if (!$since) {
            return null;
        }

        try {
            return new \DateTimeImmutable($since);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Baut next_url / prev_url aus der aktuellen Request-URL + Pagination-Parametern.
     * Gibt leeres Array zurück wenn kein Limit gesetzt (keine Paginierung).
     */
    private function buildNavigationUrls(Request $request, ?int $limit, int $offset): array
    {
        if ($limit === null) {
            return [];
        }

        $base = $request->url();
        $extra = array_filter($request->only(['since', 'sort', 'order', 'fields']));

        $nav = [];

        // next_url — immer hinzufügen wenn limit gesetzt (ob noch mehr da ist wissen wir erst nach collect)
        $nav['next_url'] = $base . '?' . http_build_query(array_merge($extra, [
            'limit'  => $limit,
            'offset' => $offset + $limit,
        ]));

        // prev_url — nur wenn wir nicht am Anfang sind
        if ($offset > 0) {
            $prevOffset = max(0, $offset - $limit);
            $nav['prev_url'] = $base . '?' . http_build_query(array_merge($extra, [
                'limit'  => $limit,
                'offset' => $prevOffset,
            ]));
        }

        return $nav;
    }

    private function logAccess(ApiTemplate $template, Request $request, float $startTime): void
    {
        try {
            ApiTemplateAccessLog::create([
                'api_template_id' => $template->id,
                'ip_address' => $request->ip(),
                'response_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'product_count' => null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Silent fail — access logs should not break the stream
        }
    }
}
