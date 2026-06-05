<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiTemplate;
use App\Services\ApiDesigner\ApiDataCollector;
use App\Services\ApiDesigner\GraphqlDesignerService;
use App\Services\ApiDesigner\JsonWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MCP (Model Context Protocol) Endpoint — Streamable HTTP Transport.
 *
 * Stellt den anyPIM API-Designer als MCP-Server bereit, sodass Claude (claude.ai
 * Custom Connector) und andere AI-Agenten direkt auf die API-Templates zugreifen
 * können — ohne separaten Node.js-Prozess.
 *
 * Transport: JSON-RPC 2.0 über POST /mcp (MCP Streamable HTTP).
 * Auth:      Globaler Bearer-Token aus config('services.mcp.token').
 *
 * Die Tools sind dünne Wrapper um die bestehenden API-Designer-Services:
 *   list_templates  → ApiTemplate-Liste
 *   stream_products → ApiDataCollector + JsonWriter (JSON-Templates)
 *   graphql_query   → GraphqlDesignerService::execute (Query)
 *   graphql_mutate  → GraphqlDesignerService::execute (Mutation)
 *   get_schema      → GraphqlDesignerService::schemaPreview
 */
class McpController extends Controller
{
    /** Vom Server unterstützte MCP-Protokollversion (Fallback). */
    private const PROTOCOL_VERSION = '2025-06-18';

    public function __construct(
        private readonly ApiDataCollector $dataCollector,
        private readonly JsonWriter $jsonWriter,
        private readonly GraphqlDesignerService $graphqlDesignerService,
    ) {}

    /**
     * POST /mcp — JSON-RPC 2.0 Dispatcher.
     *
     * @param string|null $urlToken Token aus dem URL-Pfad (claude.ai Custom Connector,
     *                              dessen Dialog kein Header-Feld bietet).
     */
    public function handle(Request $request, ?string $urlToken = null): JsonResponse
    {
        $this->authenticate($request, $urlToken);

        $payload = $request->json()->all();

        // Batch-Requests (Array von Calls)
        if (array_is_list($payload) && $payload !== []) {
            $responses = [];
            foreach ($payload as $call) {
                $response = $this->dispatch(is_array($call) ? $call : []);
                if ($response !== null) {
                    $responses[] = $response;
                }
            }

            return response()->json($responses);
        }

        $response = $this->dispatch($payload);

        // Notifications (ohne id) → 202 ohne Body
        if ($response === null) {
            return response()->json(null, 202);
        }

        return response()->json($response);
    }

    /**
     * Einen einzelnen JSON-RPC-Call verarbeiten.
     * Gibt null zurück bei Notifications (kein "id" → keine Antwort erwartet).
     */
    private function dispatch(array $call): ?array
    {
        $id     = $call['id'] ?? null;
        $method = $call['method'] ?? '';
        $params = $call['params'] ?? [];

        // Notifications haben keine id → keine Antwort
        $isNotification = !array_key_exists('id', $call);

        try {
            $result = match ($method) {
                'initialize'                 => $this->initialize($params),
                'ping'                       => (object) [],
                'tools/list'                 => ['tools' => $this->toolDefinitions()],
                'tools/call'                 => $this->callTool($params),
                'notifications/initialized'  => null,
                default                      => throw new McpException(-32601, "Methode nicht gefunden: {$method}"),
            };
        } catch (McpException $e) {
            if ($isNotification) {
                return null;
            }

            return $this->errorResponse($id, $e->getCode(), $e->getMessage());
        } catch (\Throwable $e) {
            if ($isNotification) {
                return null;
            }

            return $this->errorResponse($id, -32603, 'Interner Fehler: ' . $e->getMessage());
        }

        if ($isNotification) {
            return null;
        }

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ];
    }

    // ── MCP-Methoden ────────────────────────────────────────────────────────

    private function initialize(array $params): array
    {
        $clientVersion = $params['protocolVersion'] ?? self::PROTOCOL_VERSION;

        return [
            'protocolVersion' => is_string($clientVersion) ? $clientVersion : self::PROTOCOL_VERSION,
            'capabilities'    => [
                'tools' => (object) [],
            ],
            'serverInfo' => [
                'name'    => 'anyPIM',
                'version' => '1.0.0',
            ],
        ];
    }

    /**
     * Tool-Schemas — identisch zu mcp-server/src/tools.ts, aber als JSON-Schema.
     */
    private function toolDefinitions(): array
    {
        $slug = [
            'type'        => 'string',
            'pattern'     => '^[a-zA-Z0-9_-]+$',
            'description' => 'URL-Slug des API-Templates',
        ];

        return [
            [
                'name'        => 'list_templates',
                'description' => 'Listet alle aktiven API-Templates des PIM. Gibt Slug, Name, Format (json/graphql) und Richtung zurück.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => (object) [],
                ],
            ],
            [
                'name'        => 'stream_products',
                'description' => 'Ruft Produkte aus einem JSON-API-Template ab. Unterstützt Pagination, Delta-Sync und Sprachauswahl.',
                'inputSchema' => [
                    'type'       => 'object',
                    'required'   => ['slug'],
                    'properties' => [
                        'slug'   => $slug,
                        'limit'  => ['type' => 'integer', 'minimum' => 1, 'description' => 'Max. Anzahl Produkte (Standard: alle)'],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Start-Record für Pagination'],
                        'since'  => ['type' => 'string', 'description' => 'ISO-8601 Timestamp — nur seit diesem Datum geänderte Produkte'],
                        'sort'   => ['type' => 'string', 'enum' => ['sku', 'name', 'status', 'created_at', 'updated_at'], 'description' => 'Sortierfeld'],
                        'order'  => ['type' => 'string', 'enum' => ['asc', 'desc'], 'description' => 'Sortierrichtung (Standard: asc)'],
                        'fields' => ['type' => 'string', 'description' => 'Kommaliste von JSON-Keys — nur diese Felder (z.B. "sku,name,price")'],
                        'lang'   => ['type' => 'string', 'description' => 'Sprachcode (z.B. "de", "en") — überschreibt Template-Sprache'],
                    ],
                ],
            ],
            [
                'name'        => 'graphql_query',
                'description' => 'Führt eine GraphQL-Query gegen ein GraphQL-API-Template aus (Daten lesen).',
                'inputSchema' => [
                    'type'       => 'object',
                    'required'   => ['slug', 'query'],
                    'properties' => [
                        'slug'      => $slug,
                        'query'     => ['type' => 'string', 'description' => 'GraphQL Query-String, z.B. "{ total groups { products { sku name } } }"'],
                        'variables' => ['type' => 'object', 'description' => 'GraphQL-Variablen als Objekt'],
                    ],
                ],
            ],
            [
                'name'        => 'graphql_mutate',
                'description' => 'Führt eine GraphQL-Mutation gegen ein bidirektionales API-Template aus (Daten schreiben/importieren). Nur für Templates mit direction "import" oder "bidirectional".',
                'inputSchema' => [
                    'type'       => 'object',
                    'required'   => ['slug', 'mutation'],
                    'properties' => [
                        'slug'      => $slug,
                        'mutation'  => ['type' => 'string', 'description' => 'GraphQL Mutation-String'],
                        'variables' => ['type' => 'object', 'description' => 'GraphQL-Variablen als Objekt'],
                    ],
                ],
            ],
            [
                'name'        => 'get_schema',
                'description' => 'Gibt das GraphQL-Schema (SDL) eines API-Templates zurück. Nützlich um zu verstehen welche Felder und Typen verfügbar sind.',
                'inputSchema' => [
                    'type'       => 'object',
                    'required'   => ['slug'],
                    'properties' => ['slug' => $slug],
                ],
            ],
        ];
    }

    /**
     * tools/call — Tool ausführen und MCP-content zurückgeben.
     */
    private function callTool(array $params): array
    {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        $text = match ($name) {
            'list_templates'  => $this->toolListTemplates(),
            'stream_products' => $this->toolStreamProducts($args),
            'graphql_query'   => $this->toolGraphql($args, 'query'),
            'graphql_mutate'  => $this->toolGraphql($args, 'mutation'),
            'get_schema'      => $this->toolGetSchema($args),
            default           => throw new McpException(-32602, "Unbekanntes Tool: {$name}"),
        };

        return [
            'content' => [
                ['type' => 'text', 'text' => $text],
            ],
        ];
    }

    // ── Tool-Implementierungen ────────────────────────────────────────────────

    private function toolListTemplates(): string
    {
        $templates = ApiTemplate::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'output_format', 'direction', 'language']);

        if ($templates->isEmpty()) {
            return 'Keine aktiven Templates gefunden.';
        }

        return $templates->map(function (ApiTemplate $t) {
            $lang = $t->language ? ', ' . $t->language : '';

            return "• {$t->slug} — {$t->name} [{$t->output_format}, {$t->direction}{$lang}]";
        })->implode("\n");
    }

    private function toolStreamProducts(array $args): string
    {
        $template = $this->resolveTemplate($args['slug'] ?? '');

        if ($template->output_format !== 'json') {
            throw new McpException(-32602, "Template \"{$template->slug}\" ist ein GraphQL-Template — nutze graphql_query.");
        }

        $limit     = isset($args['limit']) ? max(1, (int) $args['limit']) : null;
        $offset    = max(0, (int) ($args['offset'] ?? 0));
        $since     = $this->parseSince($args['since'] ?? null);
        $sortField = $args['sort'] ?? null;
        $sortOrder = $args['order'] ?? 'asc';
        $fields    = array_filter(array_map('trim', explode(',', (string) ($args['fields'] ?? ''))));
        $lang      = $args['lang'] ?? $template->language ?? 'de';

        $data = $this->dataCollector->collect(
            $template,
            $template->searchProfile,
            $limit,
            $offset,
            $since,
            $sortField ?: null,
            $sortOrder,
            $lang,
        );

        return $this->jsonWriter->buildString(
            $data['grouped'],
            $template->template_json,
            $lang,
            $data,
            $fields,
        );
    }

    /**
     * @param 'query'|'mutation' $kind
     */
    private function toolGraphql(array $args, string $kind): string
    {
        $template = $this->resolveTemplate($args['slug'] ?? '');

        if ($template->output_format !== 'graphql') {
            throw new McpException(-32602, "Template \"{$template->slug}\" ist ein JSON-Template — nutze stream_products.");
        }

        $query = $args[$kind] ?? '';
        if ($query === '') {
            throw new McpException(-32602, "Parameter \"{$kind}\" fehlt.");
        }

        if ($kind === 'mutation' && !in_array($template->direction, ['import', 'bidirectional'], true)) {
            throw new McpException(-32602, "Template \"{$template->slug}\" unterstützt keine Mutationen (direction muss import/bidirectional sein).");
        }

        $variables = $args['variables'] ?? null;
        $result = $this->graphqlDesignerService->execute($template, $query, is_array($variables) ? $variables : null);

        return (string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetSchema(array $args): string
    {
        $template = $this->resolveTemplate($args['slug'] ?? '');

        if ($template->output_format !== 'graphql') {
            throw new McpException(-32602, "Template \"{$template->slug}\" ist ein JSON-Template und hat kein GraphQL-Schema.");
        }

        $result = $this->graphqlDesignerService->schemaPreview($template);

        return $result['sdl'] ?? (string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    // ── Helfer ────────────────────────────────────────────────────────────────

    private function resolveTemplate(string $slug): ApiTemplate
    {
        $template = ApiTemplate::where('slug', $slug)->active()->first();

        if (!$template) {
            throw new McpException(-32602, "Template mit Slug \"{$slug}\" nicht gefunden oder inaktiv.");
        }

        return $template;
    }

    private function parseSince(mixed $since): ?\DateTimeImmutable
    {
        if (!is_string($since) || $since === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($since);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Token wird aus drei Quellen akzeptiert (erste nicht-leere gewinnt):
     *   1. URL-Pfad     — /api/v1/mcp/<token>  (claude.ai Custom Connector)
     *   2. Bearer-Header — Authorization: Bearer <token>  (Claude Desktop, curl)
     *   3. Query-Param   — ?token=<token>  (Fallback)
     */
    private function authenticate(Request $request, ?string $urlToken = null): void
    {
        $token = config('services.mcp.token');

        // Kein Token konfiguriert → Endpoint deaktiviert (fail closed)
        if (empty($token)) {
            abort(503, 'MCP-Endpoint ist nicht konfiguriert (MCP_AUTH_TOKEN fehlt).');
        }

        $header = $request->header('Authorization', '');
        $bearer = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';

        $provided = $urlToken
            ?: ($bearer ?: (string) $request->query('token', ''));

        if (!hash_equals((string) $token, $provided)) {
            abort(401, 'Ungültiger oder fehlender MCP-Token.');
        }
    }

    private function errorResponse(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ];
    }
}

/**
 * Interne JSON-RPC-Fehler mit Code (z.B. -32601 Method not found).
 */
class McpException extends \RuntimeException
{
    public function __construct(int $code, string $message)
    {
        parent::__construct($message, $code);
    }
}
