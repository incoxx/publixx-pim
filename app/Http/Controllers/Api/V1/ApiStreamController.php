<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ApiTemplate;
use App\Models\ApiTemplateAccessLog;
use App\Services\ApiDesigner\ApiDesignerService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiStreamController extends Controller
{
    public function __construct(
        private readonly ApiDesignerService $apiDesignerService,
    ) {}

    /**
     * GET /api/v1/api-streams/{slug} — Stream product data as JSON.
     */
    public function stream(Request $request, string $slug): StreamedResponse
    {
        $template = ApiTemplate::where('slug', $slug)->active()->firstOrFail();

        $this->authenticateStream($request, $template);

        $startTime = microtime(true);

        $searchProfile = $template->searchProfile;

        $response = $this->apiDesignerService->stream($template, $searchProfile);

        // Log access asynchronously
        $this->logAccess($template, $request, $startTime);

        return $response;
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
            if (!$key || hash('sha256', $key) !== $template->api_key) {
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
