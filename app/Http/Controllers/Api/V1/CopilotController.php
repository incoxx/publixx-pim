<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\WriteAuditLog;
use App\Services\Copilot\CopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * In-App Copilot — Chat-Endpoint für das anyPIM-Chatfenster.
 *
 * chat()        — proxyt den Anthropic-SSE-Stream an das Frontend.
 * executeTool() — führt eine vom Nutzer bestätigte Schreib-Aktion aus.
 */
class CopilotController extends Controller
{
    public function __construct(
        private readonly CopilotService $copilot,
    ) {}

    /**
     * POST /api/v1/copilot/chat — streamt die Claude-Antwort als Server-Sent Events.
     */
    public function chat(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'messages'           => 'required|array|min:1',
            'messages.*.role'    => 'required|string|in:user,assistant',
            'messages.*.content' => 'required',
            'context'            => 'sometimes|array',
        ]);

        try {
            $body = $this->copilot->openStream(
                $validated['messages'],
                $validated['context'] ?? [],
            );
        } catch (\Throwable $e) {
            // Konfig-/Verbindungsfehler als SSE-Event ausgeben → das Frontend
            // kann es in der Chat-Oberfläche anzeigen.
            return $this->errorStream($e->getMessage());
        }

        return new StreamedResponse(function () use ($body) {
            while (!$body->eof()) {
                echo $body->read(8192);
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream; charset=UTF-8',
            'Cache-Control'     => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * POST /api/v1/copilot/execute-tool — führt ein bestätigtes Client-Tool aus.
     * Aktuell nur graphql_mutate (schreibende Aktion mit Nutzer-Bestätigung).
     */
    public function executeTool(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'required|string',
            'input' => 'required|array',
        ]);

        if ($validated['name'] !== 'graphql_mutate') {
            return response()->json([
                'is_error' => true,
                'content'  => 'Unbekanntes oder nicht erlaubtes Tool: ' . $validated['name'],
            ], 422);
        }

        try {
            $result = $this->copilot->executeMutation($validated['input']);
        } catch (\Throwable $e) {
            // Fehler als (nicht-fataler) Tool-Result zurückgeben — Claude kann
            // darauf reagieren und einen korrigierten Vorschlag machen.
            return response()->json([
                'is_error' => true,
                'content'  => $e->getMessage(),
            ]);
        }

        // Bestätigte Schreib-Aktion ins Audit-Journal schreiben.
        WriteAuditLog::dispatch(
            auditableType: 'Copilot',
            auditableId:   (string) ($validated['input']['slug'] ?? 'graphql_mutate'),
            action:        'copilot_mutate',
            newValues:     ['tool' => 'graphql_mutate', 'slug' => $validated['input']['slug'] ?? null],
            ipAddress:     $request->ip(),
            userAgent:     $request->userAgent(),
        );

        return response()->json([
            'is_error' => false,
            'content'  => (string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * Erzeugt einen SSE-Stream mit einem einzelnen Fehler-Event.
     */
    private function errorStream(string $message): StreamedResponse
    {
        return new StreamedResponse(function () use ($message) {
            $payload = (string) json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
            echo "event: copilot_error\n";
            echo 'data: ' . $payload . "\n\n";
            flush();
        }, 200, [
            'Content-Type'  => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
