<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ApiTemplate;
use App\Models\ApiTemplateAccessLog;
use App\Services\ApiDesigner\ApiDesignerService;
use App\Services\ApiDesigner\GraphqlDesignerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiStreamController extends Controller
{
    public function __construct(
        private readonly ApiDesignerService $apiDesignerService,
        private readonly GraphqlDesignerService $graphqlDesignerService,
    ) {}

    /**
     * GET|POST /api/v1/api-streams/{slug} — Stream product data as JSON or execute GraphQL query.
     */
    public function stream(Request $request, string $slug): StreamedResponse|JsonResponse
    {
        $template = ApiTemplate::where('slug', $slug)->active()->firstOrFail();

        $this->authenticateStream($request, $template);

        $startTime = microtime(true);

        if ($template->output_format === 'graphql') {
            $response = $this->handleGraphql($request, $template);
        } else {
            $response = $this->apiDesignerService->stream($template, $template->searchProfile);
        }

        $this->logAccess($template, $request, $startTime);

        return $response;
    }

    /**
     * GraphQL-Query aus Request lesen und ausführen.
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
     * POST /api/v1/api-streams/{slug} — Import data via JSON.
     */
    public function import(Request $request, string $slug)
    {
        $template = ApiTemplate::where('slug', $slug)->active()->firstOrFail();

        if (!in_array($template->direction, ['import', 'bidirectional'])) {
            abort(405, 'Dieses API-Template unterstützt keinen Import.');
        }

        $this->authenticateStream($request, $template);

        // Import-Logik wird in Phase 2 implementiert
        return response()->json([
            'message' => 'Import-Funktion wird in einer zukünftigen Version verfügbar sein.',
        ], 501);
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
