<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\McpException;
use App\Http\Controllers\Controller;
use App\Jobs\WriteAuditLog;
use App\Models\ApiTemplate;
use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\ProductSearchIndex;
use App\Services\ApiDesigner\ApiDataCollector;
use App\Services\ApiDesigner\GraphqlDesignerService;
use App\Services\ApiDesigner\JsonWriter;
use App\Services\Pim\ProductAttributeWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    // Wird in handle() gesetzt; Request-Facade als Fallback falls logMcpCall()
    // in einem unerwarteten Pfad ohne handle() aufgerufen wird.
    private ?Request $currentRequest = null;

    public function __construct(
        private readonly ApiDataCollector $dataCollector,
        private readonly JsonWriter $jsonWriter,
        private readonly GraphqlDesignerService $graphqlDesignerService,
        private readonly ProductAttributeWriter $attributeWriter,
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
        $this->currentRequest = $request;

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
                'name'        => 'search_products',
                'description' => 'Volltextsuche über Produkte in anyPIM. Sucht serverseitig via FULLTEXT-Index (schnell, phonetisch). '
                    . 'WANN: bevorzugtes Tool, wenn der Nutzer nach bestimmten Produkten sucht (Name, Eigenschaft, SKU). '
                    . 'Zusätzlich können strukturierte Attribut-Filter gesetzt werden (z.B. farbe=blau); für SKU-Präfixe filters mit operator "starts_with" nutzen. '
                    . 'Gibt die Ergebnisse im Format des angegebenen Templates zurück. Rufe zuerst list_templates auf, um einen gültigen slug zu erhalten.',
                'inputSchema' => [
                    'type'       => 'object',
                    'required'   => ['slug'],
                    'properties' => [
                        'slug'    => $slug,
                        'query'   => ['type' => 'string', 'description' => 'Freitext-Suchbegriff (z.B. "blau 128GB"). Leer = alle Produkte (nur Filter aktiv).'],
                        'filters' => [
                            'type'        => 'array',
                            'description' => 'Strukturierte Attribut-Filter. Nur Felder mit is_searchable=true im Template erlaubt.',
                            'items'       => [
                                'type'       => 'object',
                                'required'   => ['field', 'value'],
                                'properties' => [
                                    'field'    => ['type' => 'string', 'description' => 'jsonKey des Feldes aus dem Template (z.B. "farbe", "status", "sku")'],
                                    'value'    => ['description' => 'Suchwert (String, Zahl oder Array für "in"-Operator)'],
                                    'operator' => ['type' => 'string', 'enum' => ['eq', 'like', 'starts_with', 'gt', 'lt', 'gte', 'lte', 'in'], 'description' => 'Vergleichsoperator (Standard: eq)'],
                                ],
                            ],
                        ],
                        'limit'  => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'description' => 'Max. Treffer (Standard: 20)'],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Offset für Pagination'],
                        'lang'   => ['type' => 'string', 'description' => 'Sprachcode (z.B. "de", "en")'],
                        'status' => ['type' => 'string', 'enum' => ['active', 'draft', 'inactive', 'discontinued'], 'description' => 'Produkt-Status-Filter'],
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
            [
                'name'        => 'list_attributes',
                'description' => 'Listet Attribut-Definitionen des PIM inkl. UUID (id). '
                    . 'WANN: wenn du verfügbare Attribute erkunden oder eine Attribut-UUID benötigst. '
                    . 'Die id (oder technical_name) wird von update_product_attribute zum Setzen von Werten gebraucht. '
                    . 'Optional filterbar nach Suchbegriff, Datentyp, Quellsystem und Status.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'        => ['type' => 'string', 'description' => 'Freitext in technical_name / name_de / name_en'],
                        'data_type'     => ['type' => 'string', 'description' => 'Datentyp-Filter (String, Number, Float, Date, Flag, Selection, ...)'],
                        'source_system' => ['type' => 'string', 'description' => 'Quellsystem-Filter (z.B. ETIM, ONYX)'],
                        'status'        => ['type' => 'string', 'enum' => ['active', 'inactive'], 'description' => 'Status-Filter (Standard: active)'],
                        'limit'         => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'description' => 'Max. Anzahl (Standard: 100)'],
                        'offset'        => ['type' => 'integer', 'minimum' => 0, 'description' => 'Offset für Pagination'],
                    ],
                ],
            ],
            [
                'name'        => 'list_hierarchies',
                'description' => 'Listet alle Hierarchien (Klassifikationen) inkl. UUID. Typ "master" = interne Produkt-Klassifizierung, "output" = Export-Standard (ETIM, ONYX, ...). '
                    . 'WANN: Einstiegspunkt für die Klassifikations-Navigation — rufe dies ZUERST auf, um die hierarchy_id zu erhalten, die list_hierarchy_nodes benötigt.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['master', 'output'], 'description' => 'Filter nach Hierarchie-Typ'],
                    ],
                ],
            ],
            [
                'name'        => 'list_hierarchy_nodes',
                'description' => 'Listet die Knoten (Kategorien/Klassen) einer Hierarchie inkl. UUID, Pfad und Tiefe. '
                    . 'BENÖTIGT hierarchy_id (aus list_hierarchies). Die zurückgegebene Knoten-id (node_id) verwenden '
                    . 'list_node_attributes und list_node_products. Optional auf einen Teilbaum (parent_node_id) oder Suchbegriff eingrenzen.',
                'inputSchema' => [
                    'type'       => 'object',
                    'required'   => ['hierarchy_id'],
                    'properties' => [
                        'hierarchy_id'   => ['type' => 'string', 'description' => 'UUID der Hierarchie (aus list_hierarchies)'],
                        'parent_node_id' => ['type' => 'string', 'description' => 'Nur direkte Kinder dieses Knotens'],
                        'search'         => ['type' => 'string', 'description' => 'Freitext in name_de / name_en'],
                        'limit'          => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'description' => 'Max. Anzahl (Standard: 200)'],
                        'offset'         => ['type' => 'integer', 'minimum' => 0, 'description' => 'Offset für Pagination'],
                    ],
                ],
            ],
            [
                'name'        => 'list_node_attributes',
                'description' => 'Listet die einem Hierarchie-Knoten zugeordneten Attribute inkl. Attribut-UUID, Pflicht-Flag, Collection und Sichtbarkeits-Flags. '
                    . 'WANN: um zu erfahren, welche Attribute für eine Klasse/Kategorie relevant sind. BENÖTIGT node_id (aus list_hierarchy_nodes).',
                'inputSchema' => [
                    'type'       => 'object',
                    'required'   => ['node_id'],
                    'properties' => [
                        'node_id' => ['type' => 'string', 'description' => 'UUID des Hierarchie-Knotens'],
                    ],
                ],
            ],
            [
                'name'        => 'list_node_products',
                'description' => 'Listet die Produkte eines Hierarchie-Knotens. type "master" = master_hierarchy_node_id, "output" = Output-Zuordnung, "both" = beide (Standard). '
                    . 'BENÖTIGT node_id (aus list_hierarchy_nodes). Für Freitext-/Attributsuche über alle Produkte nutze stattdessen search_products.',
                'inputSchema' => [
                    'type'       => 'object',
                    'required'   => ['node_id'],
                    'properties' => [
                        'node_id' => ['type' => 'string', 'description' => 'UUID des Hierarchie-Knotens'],
                        'type'    => ['type' => 'string', 'enum' => ['master', 'output', 'both'], 'description' => 'Zuordnungsart (Standard: both)'],
                        'limit'   => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'description' => 'Max. Anzahl (Standard: 100)'],
                        'offset'  => ['type' => 'integer', 'minimum' => 0, 'description' => 'Offset für Pagination'],
                    ],
                ],
            ],
            [
                'name'        => 'update_product_attribute',
                'description' => 'Setzt EINEN Attributwert EINES Produkts (SCHREIBEND). '
                    . 'WANN: nur wenn der Nutzer eine konkrete Produktdaten-Änderung wünscht. '
                    . 'VORGEHEN (Pflicht): (1) Das genaue Produkt bestimmen — betrifft die Änderung mehrere oder ist unklar welches Produkt gemeint ist, '
                    . 'erst per search_products eingrenzen und beim Nutzer rückfragen; rate NICHT. (2) Das Attribut mit list_attributes prüfen — '
                    . 'ob es existiert, ob es übersetzbar ist (dann "language" Pflicht) und ob es eine Einheit hat (has_unit/default_unit → dann "unit" angeben, '
                    . 'z.B. "g" für Gramm). (3) Pro Aufruf genau EIN Produkt und EIN Wert. '
                    . 'Produkt per UUID/SKU, Attribut per UUID/technical_name. Selection: "value_selection_id". Composite/Collection nicht unterstützt.',
                'inputSchema' => [
                    'type'       => 'object',
                    'required'   => ['product', 'attribute', 'value'],
                    'properties' => [
                        'product'            => ['type' => 'string', 'description' => 'Produkt-UUID oder SKU (genau EIN Produkt)'],
                        'attribute'          => ['type' => 'string', 'description' => 'Attribut-UUID oder technical_name'],
                        'value'              => ['description' => 'Neuer Wert (String, Zahl, Boolean oder ISO-Datum) — ohne Einheit, die gehört in "unit"'],
                        'language'           => ['type' => 'string', 'description' => 'Sprachcode (Pflicht bei übersetzbaren Attributen, z.B. "de")'],
                        'value_selection_id' => ['type' => 'string', 'description' => 'ValueListEntry-UUID bei Selection-Attributen'],
                        'unit'               => ['type' => 'string', 'description' => 'Einheit (Abkürzung wie "g", "kg", "mm" oder technical_name) — nur/erforderlich bei Attributen mit Einheit'],
                    ],
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

        $text = $this->executeToolPublic($name, $args);

        $this->logMcpCall($name, $args);

        return [
            'content' => [
                ['type' => 'text', 'text' => $text],
            ],
        ];
    }

    /**
     * Führt ein MCP-Tool aus und liefert das (JSON-/Text-)Ergebnis als String.
     * Wird sowohl von tools/call (JSON-RPC) als auch vom Sanctum-Debug-Endpoint
     * (testCall) genutzt — damit der Playground exakt dieselbe Logik testet.
     *
     * @param  array<string, mixed>  $args
     */
    public function executeToolPublic(string $name, array $args): string
    {
        return match ($name) {
            'list_templates'           => $this->toolListTemplates(),
            'stream_products'          => $this->toolStreamProducts($args),
            'search_products'          => $this->toolSearchProducts($args),
            'graphql_query'            => $this->toolGraphql($args, 'query'),
            'graphql_mutate'           => $this->toolGraphql($args, 'mutation'),
            'get_schema'               => $this->toolGetSchema($args),
            'list_attributes'          => $this->toolListAttributes($args),
            'list_hierarchies'         => $this->toolListHierarchies($args),
            'list_hierarchy_nodes'     => $this->toolListHierarchyNodes($args),
            'list_node_attributes'     => $this->toolListNodeAttributes($args),
            'list_node_products'       => $this->toolListNodeProducts($args),
            'update_product_attribute' => $this->toolUpdateProductAttribute($args),
            default                    => throw new McpException(-32602, "Unbekanntes Tool: {$name}"),
        };
    }

    /**
     * POST /api/v1/mcp/test-call — Admin-Debug-Endpoint (Sanctum).
     *
     * Führt ein MCP-Tool unter Sanctum-Auth aus, ohne den globalen MCP-Token,
     * damit der MCP-Playground die echten Tools (inkl. search_products) testen
     * und das exakte Ergebnis anzeigen kann.
     */
    public function testCall(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('Admin') && !$user->hasRole('Sysadmin'))) {
            return response()->json(['error' => 'Nur für Administratoren.'], 403);
        }

        $validated = $request->validate([
            'tool'      => 'required|string',
            'arguments' => 'sometimes|array',
        ]);

        $tool = $validated['tool'];
        $args = $validated['arguments'] ?? [];
        $this->currentRequest = $request;

        try {
            $text = $this->executeToolPublic($tool, $args);
        } catch (\Throwable $e) {
            return response()->json([
                'tool'      => $tool,
                'arguments' => $args,
                'is_error'  => true,
                'error'     => $e->getMessage(),
            ]);
        }

        $this->logMcpCall($tool, $args);

        $decoded = json_decode($text, true);

        return response()->json([
            'tool'      => $tool,
            'arguments' => $args,
            'result'    => json_last_error() === JSON_ERROR_NONE ? $decoded : $text,
        ]);
    }

    // ── Tool-Implementierungen ────────────────────────────────────────────────

    private function toolListTemplates(): string
    {
        $templates = ApiTemplate::query()
            ->active()
            ->where('is_mcp_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'output_format', 'direction', 'language', 'description']);

        if ($templates->isEmpty()) {
            return 'Keine für MCP freigegebenen Templates gefunden. Bitte im API Designer "Für Claude freigeben" aktivieren.';
        }

        return $templates->map(function (ApiTemplate $t) {
            $lang = $t->language ? ', ' . $t->language : '';
            $line = "• {$t->slug} — {$t->name} [{$t->output_format}, {$t->direction}{$lang}]";

            if (!empty($t->description)) {
                $line .= "\n  → {$t->description}";
            }

            return $line;
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
     * Volltextsuche über den products_search_index + optionale Attribut-Filter.
     * Ergebnisse werden durch den Template-JsonWriter formatiert.
     */
    private function toolSearchProducts(array $args): string
    {
        $template = $this->resolveTemplate($args['slug'] ?? '');

        if ($template->output_format !== 'json') {
            throw new McpException(-32602, "search_products funktioniert nur mit JSON-Templates. Nutze graphql_query für GraphQL-Templates.");
        }

        $queryText = trim((string) ($args['query'] ?? ''));
        $filters   = is_array($args['filters'] ?? null) ? $args['filters'] : [];
        $limit     = min(200, max(1, (int) ($args['limit'] ?? 20)));
        $offset    = max(0, (int) ($args['offset'] ?? 0));
        $lang      = $args['lang'] ?? $template->language ?? 'de';
        $status    = $args['status'] ?? null;

        // Durchsuchbare Felder aus template_json extrahieren (is_searchable != false)
        $searchableElements = $this->extractSearchableElements($template->template_json);

        // Attribute für Filter-Felder vorab laden (N+1 vermeiden)
        $filterAttrIds = array_filter(array_map(
            fn ($f) => isset($f['field']) ? ($searchableElements[$f['field']]['attributeId'] ?? null) : null,
            $filters,
        ));
        $attributeCache = $filterAttrIds
            ? Attribute::whereIn('id', array_values($filterAttrIds))->get()->keyBy('id')->all()
            : [];

        // ── Query aufbauen ────────────────────────────────────────────────────
        $builder = ProductSearchIndex::query()
            ->join('products', 'products.id', '=', 'products_search_index.product_id')
            ->where('products.product_type_ref', 'product');

        if ($status) {
            $builder->where('products.status', $status);
        } else {
            $builder->where('products.status', 'active');
        }

        // FULLTEXT-Suche über searchable_text (inkl. aller is_quick_search-Attribute)
        if ($queryText !== '') {
            if (DB::getDriverName() === 'mysql') {
                // Mehrwörtige Suche: jedes Wort mit + prefixen → AND-Semantik statt OR.
                // Umlaut-Variante als Fallback (ä→ae etc.) falls Kollation Umlaute nicht matched.
                $booleanTerm         = $this->toBooleanSearchTerm($queryText);
                $booleanTermAscii    = $this->toBooleanSearchTerm($this->normalizeUmlauts($queryText));
                $phoneticTerm        = class_exists(\App\Support\KoelnerPhonetik::class)
                    ? \App\Support\KoelnerPhonetik::encode($queryText)
                    : '';

                $builder->where(function ($q) use ($booleanTerm, $booleanTermAscii, $queryText, $phoneticTerm) {
                    $q->whereRaw('MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST(? IN BOOLEAN MODE)', [$booleanTerm])
                      ->orWhereRaw('MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST(? IN BOOLEAN MODE)', [$booleanTermAscii])
                      ->orWhere('products_search_index.sku', 'like', '%' . $queryText . '%')
                      ->orWhere('products_search_index.ean', 'like', '%' . $queryText . '%')
                      ->orWhereRaw('products_search_index.searchable_text IS NOT NULL AND MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST(? IN BOOLEAN MODE)', [$booleanTerm])
                      ->orWhereRaw('products_search_index.searchable_text IS NOT NULL AND MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST(? IN BOOLEAN MODE)', [$booleanTermAscii]);
                    if ($phoneticTerm) {
                        $q->orWhere('products_search_index.phonetic_name_de', 'like', '%' . $phoneticTerm . '%');
                    }
                });
            } else {
                $like = '%' . $queryText . '%';
                $builder->where(fn ($q) => $q
                    ->where('products_search_index.name_de', 'like', $like)
                    ->orWhere('products_search_index.name_en', 'like', $like)
                    ->orWhere('products_search_index.sku', 'like', $like));
            }
        }

        // Strukturierte Attribut-Filter (nur is_searchable Felder erlaubt)
        $filterIdx = 0;
        foreach ($filters as $filter) {
            $fieldKey  = $filter['field'] ?? '';
            $value     = $filter['value'] ?? null;
            $operator  = $filter['operator'] ?? 'eq';

            if ($fieldKey === '' || $value === null) {
                continue;
            }

            // Base-Felder direkt auf products-Spalten mappen
            if (in_array($fieldKey, ['sku', 'ean', 'status', 'name'], true)) {
                $col  = match ($fieldKey) {
                    'sku'    => 'products_search_index.sku',
                    'ean'    => 'products_search_index.ean',
                    'status' => 'products.status',
                    'name'   => 'products_search_index.name_de',
                };
                $op = match ($operator) {
                    'like'       => fn ($q) => $q->where($col, 'like', '%' . $value . '%'),
                    'starts_with'=> fn ($q) => $q->where($col, 'like', $value . '%'),
                    'in'         => fn ($q) => $q->whereIn($col, (array) $value),
                    default      => fn ($q) => $q->where($col, $value),
                };
                $builder->where($op);
                continue;
            }

            // Attribut-Filter: Template-Element nach jsonKey suchen
            $element = $searchableElements[$fieldKey] ?? null;
            if (!$element || !isset($element['attributeId'])) {
                continue; // Unbekanntes oder nicht-durchsuchbares Feld → überspringen
            }

            $attrId = $element['attributeId'];
            $attr   = $attributeCache[$attrId] ?? null;
            if (!$attr) {
                continue;
            }

            $valueCol = match ($attr->data_type) {
                'Number', 'Float' => 'value_number',
                'Date'            => 'value_date',
                'Flag'            => 'value_flag',
                default           => 'value_string',
            };

            $alias = "pav_mcp_{$filterIdx}";
            $filterIdx++;

            $builder->whereExists(function ($sub) use ($alias, $attrId, $attr, $valueCol, $value, $operator, $lang) {
                $sub->select(DB::raw(1))
                    ->from("product_attribute_values as {$alias}")
                    ->whereColumn("{$alias}.product_id", 'products.id')
                    ->where("{$alias}.attribute_id", $attrId);

                if ($attr->is_translatable ?? false) {
                    $sub->where("{$alias}.language", $lang);
                }

                $col = "{$alias}.{$valueCol}";
                match ($operator) {
                    'like'        => $sub->where($col, 'like', '%' . $value . '%'),
                    'starts_with' => $sub->where($col, 'like', $value . '%'),
                    'gt'          => $sub->where($col, '>', $value),
                    'lt'          => $sub->where($col, '<', $value),
                    'gte'         => $sub->where($col, '>=', $value),
                    'lte'         => $sub->where($col, '<=', $value),
                    'in'          => $sub->whereIn($col, (array) $value),
                    default       => $sub->where($col, $value),
                };
            });
        }

        // Relevanz-Sortierung (bei Freitext), sonst nach Name.
        // Kein Alias — ->select() weiter unten würde selectRaw() überschreiben.
        if ($queryText !== '' && DB::getDriverName() === 'mysql') {
            $bt    = $this->toBooleanSearchTerm($queryText);
            $btAsc = $this->toBooleanSearchTerm($this->normalizeUmlauts($queryText));
            $builder->orderByRaw(
                'GREATEST('
                . '(MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST(? IN BOOLEAN MODE)) * 10,'
                . '(MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST(? IN BOOLEAN MODE)) * 10'
                . ') + IF(products_search_index.searchable_text IS NOT NULL, GREATEST('
                . 'MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST(? IN BOOLEAN MODE) * 3,'
                . 'MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST(? IN BOOLEAN MODE) * 3'
                . '), 0) DESC',
                [$bt, $btAsc, $bt, $btAsc]
            );
        } else {
            $builder->orderBy('products_search_index.name_de');
        }

        $total = (clone $builder)->count();
        $rows  = $builder
            ->select(['products.id', 'products_search_index.sku', 'products_search_index.name_de', 'products_search_index.name_en'])
            ->offset($offset)
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $hint = $queryText !== '' ? " für \"{$queryText}\"" : '';
            return "Keine Produkte{$hint} gefunden. (Total: 0)";
        }

        // Produkte über DataCollector laden — wir injizieren die gefundenen IDs
        // als zusätzlichen WHERE-Filter in die normale collect()-Pipeline
        $productIds = $rows->pluck('id')->toArray();

        $data = $this->dataCollector->collectByIds(
            $template,
            $productIds,
            $lang,
        );

        $json = $this->jsonWriter->buildString(
            $data['grouped'],
            $template->template_json,
            $lang,
            ['total' => $total, 'count' => count($productIds), 'offset' => $offset, 'limit' => $limit],
        );

        // Suchanfrage als Kontext voranstellen
        $context = "Suchergebnisse für";
        if ($queryText !== '') {
            $context .= " \"{$queryText}\"";
        }
        if (!empty($filters)) {
            $filterDesc = collect($filters)->map(fn ($f) => ($f['field'] ?? '') . '=' . (is_array($f['value'] ?? '') ? implode(',', $f['value']) : $f['value']))->implode(', ');
            $context .= " [Filter: {$filterDesc}]";
        }
        $context .= " — {$total} Treffer gesamt, {$rows->count()} zurückgegeben:";

        return $context . "\n" . $json;
    }

    /**
     * Extrahiert alle is_searchable Felder aus template_json als [jsonKey => element]-Map.
     * Default: is_searchable=true (alle Felder sind durchsuchbar, außer explizit false).
     */
    private function extractSearchableElements(array $templateJson): array
    {
        $result = [];

        $walk = function (array $groups) use (&$walk, &$result) {
            foreach ($groups as $group) {
                foreach ($group['detail']['elements'] ?? [] as $element) {
                    if (($element['is_searchable'] ?? true) === false) {
                        continue;
                    }
                    $key = $element['jsonKey'] ?? $element['field'] ?? null;
                    if ($key) {
                        $result[$key] = $element;
                    }
                }
                $walk($group['groups'] ?? []);
            }
        };

        $walk($templateJson['groups'] ?? []);

        return $result;
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

    // ── PIM-Struktur-Tools (Attribute, Hierarchien, Knoten, Produkte) ──────────

    private function toolListAttributes(array $args): string
    {
        $query = Attribute::query();

        $status = $args['status'] ?? 'active';
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if (!empty($args['data_type'])) {
            $query->where('data_type', $args['data_type']);
        }
        if (!empty($args['source_system'])) {
            $query->where('source_system', $args['source_system']);
        }
        if (!empty($args['search'])) {
            $s = '%' . $args['search'] . '%';
            $query->where(fn ($q) => $q
                ->where('technical_name', 'like', $s)
                ->orWhere('name_de', 'like', $s)
                ->orWhere('name_en', 'like', $s));
        }

        $limit  = min(500, max(1, (int) ($args['limit'] ?? 100)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $total  = (clone $query)->count();

        $rows = $query->with('defaultUnit:id,abbreviation,technical_name')
            ->orderBy('technical_name')->offset($offset)->limit($limit)
            ->get(['id', 'technical_name', 'name_de', 'name_en', 'data_type', 'is_translatable', 'source_system', 'value_list_id', 'unit_group_id', 'default_unit_id', 'status']);

        return $this->jsonResult([
            'total'      => $total,
            'count'      => $rows->count(),
            'attributes' => $rows->map(fn ($a) => [
                'id'              => $a->id,
                'technical_name'  => $a->technical_name,
                'name_de'         => $a->name_de,
                'name_en'         => $a->name_en,
                'data_type'       => $a->data_type,
                'is_translatable' => (bool) $a->is_translatable,
                'source_system'   => $a->source_system,
                'value_list_id'   => $a->value_list_id,
                // Einheiten-Info: signalisiert update_product_attribute, ob "unit" nötig ist
                'has_unit'        => (bool) $a->unit_group_id,
                'default_unit'    => $a->defaultUnit?->abbreviation ?: $a->defaultUnit?->technical_name,
                'status'          => $a->status,
            ])->all(),
        ]);
    }

    private function toolListHierarchies(array $args): string
    {
        $query = Hierarchy::query();
        if (!empty($args['type'])) {
            $query->where('hierarchy_type', $args['type']);
        }

        $rows = $query->orderBy('hierarchy_type')->orderBy('technical_name')
            ->get(['id', 'technical_name', 'name_de', 'name_en', 'hierarchy_type', 'description']);

        return $this->jsonResult([
            'count'       => $rows->count(),
            'hierarchies' => $rows->map(fn ($h) => [
                'id'             => $h->id,
                'technical_name' => $h->technical_name,
                'name_de'        => $h->name_de,
                'name_en'        => $h->name_en,
                'hierarchy_type' => $h->hierarchy_type,
                'description'    => $h->description,
            ])->all(),
        ]);
    }

    private function toolListHierarchyNodes(array $args): string
    {
        $hierarchyId = (string) ($args['hierarchy_id'] ?? '');
        if ($hierarchyId === '') {
            throw new McpException(-32602, 'Parameter "hierarchy_id" fehlt.');
        }
        if (!Hierarchy::whereKey($hierarchyId)->exists()) {
            throw new McpException(-32602, "Hierarchie \"{$hierarchyId}\" nicht gefunden.");
        }

        $query = HierarchyNode::where('hierarchy_id', $hierarchyId);
        if (array_key_exists('parent_node_id', $args)) {
            $query->where('parent_node_id', $args['parent_node_id'] ?: null);
        }
        if (!empty($args['search'])) {
            $s = '%' . $args['search'] . '%';
            $query->where(fn ($q) => $q->where('name_de', 'like', $s)->orWhere('name_en', 'like', $s));
        }

        $limit  = min(1000, max(1, (int) ($args['limit'] ?? 200)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $total  = (clone $query)->count();

        $rows = $query->orderBy('path')->orderBy('sort_order')->offset($offset)->limit($limit)
            ->get(['id', 'parent_node_id', 'name_de', 'name_en', 'path', 'depth', 'sort_order', 'is_active']);

        return $this->jsonResult([
            'total' => $total,
            'count' => $rows->count(),
            'nodes' => $rows->map(fn ($n) => [
                'id'             => $n->id,
                'parent_node_id' => $n->parent_node_id,
                'name_de'        => $n->name_de,
                'name_en'        => $n->name_en,
                'path'           => $n->path,
                'depth'          => $n->depth,
                'sort_order'     => $n->sort_order,
                'is_active'      => (bool) $n->is_active,
            ])->all(),
        ]);
    }

    private function toolListNodeAttributes(array $args): string
    {
        $nodeId = (string) ($args['node_id'] ?? '');
        if ($nodeId === '') {
            throw new McpException(-32602, 'Parameter "node_id" fehlt.');
        }
        $node = HierarchyNode::find($nodeId);
        if (!$node) {
            throw new McpException(-32602, "Hierarchie-Knoten \"{$nodeId}\" nicht gefunden.");
        }

        $assignments = HierarchyNodeAttributeAssignment::with([
            'attribute:id,technical_name,name_de,name_en,data_type,is_translatable,value_list_id',
        ])
            ->where('hierarchy_node_id', $nodeId)
            ->orderBy('collection_sort')
            ->orderBy('attribute_sort')
            ->get();

        return $this->jsonResult([
            'node'       => ['id' => $node->id, 'name_de' => $node->name_de],
            'count'      => $assignments->count(),
            'attributes' => $assignments->map(function (HierarchyNodeAttributeAssignment $a) {
                $attr = $a->attribute;

                return [
                    'assignment_id'   => $a->id,
                    'attribute_id'    => $attr?->id,
                    'technical_name'  => $attr?->technical_name,
                    'name_de'         => $attr?->name_de,
                    'data_type'       => $attr?->data_type,
                    'is_translatable' => (bool) ($attr?->is_translatable),
                    'value_list_id'   => $attr?->value_list_id,
                    'collection_name' => $a->collection_name,
                    'is_required'     => (bool) $a->is_required,
                    'access_product'  => $a->access_product,
                ];
            })->all(),
        ]);
    }

    private function toolListNodeProducts(array $args): string
    {
        $nodeId = (string) ($args['node_id'] ?? '');
        if ($nodeId === '') {
            throw new McpException(-32602, 'Parameter "node_id" fehlt.');
        }
        if (!HierarchyNode::whereKey($nodeId)->exists()) {
            throw new McpException(-32602, "Hierarchie-Knoten \"{$nodeId}\" nicht gefunden.");
        }

        $type   = $args['type'] ?? 'both';
        $limit  = min(500, max(1, (int) ($args['limit'] ?? 100)));
        $offset = max(0, (int) ($args['offset'] ?? 0));

        // Produkt-IDs je Quelle (master / output) sammeln und zusammenführen
        $sourceById = [];
        if (in_array($type, ['master', 'both'], true)) {
            foreach (Product::where('master_hierarchy_node_id', $nodeId)->pluck('id') as $id) {
                $sourceById[$id] = 'master';
            }
        }
        if (in_array($type, ['output', 'both'], true)) {
            foreach (OutputHierarchyProductAssignment::where('hierarchy_node_id', $nodeId)->pluck('product_id') as $id) {
                $sourceById[$id] = isset($sourceById[$id]) ? 'both' : 'output';
            }
        }

        $allIds = array_keys($sourceById);
        $total  = count($allIds);
        $pageIds = array_slice($allIds, $offset, $limit);

        $products = Product::whereIn('id', $pageIds)->get(['id', 'sku', 'name', 'status'])->keyBy('id');

        $list = [];
        foreach ($pageIds as $id) {
            $p = $products[$id] ?? null;
            if (!$p) {
                continue;
            }
            $list[] = [
                'id'     => $p->id,
                'sku'    => $p->sku,
                'name'   => $p->name,
                'status' => $p->status,
                'source' => $sourceById[$id],
            ];
        }

        return $this->jsonResult([
            'total'    => $total,
            'count'    => count($list),
            'type'     => $type,
            'products' => $list,
        ]);
    }

    private function toolUpdateProductAttribute(array $args): string
    {
        foreach (['product', 'attribute'] as $req) {
            if (empty($args[$req])) {
                throw new McpException(-32602, "Parameter \"{$req}\" fehlt.");
            }
        }
        if (!array_key_exists('value', $args)) {
            throw new McpException(-32602, 'Parameter "value" fehlt.');
        }

        try {
            $result = $this->attributeWriter->update(
                (string) $args['product'],
                (string) $args['attribute'],
                $args['value'],
                isset($args['language']) ? (string) $args['language'] : null,
                isset($args['value_selection_id']) ? (string) $args['value_selection_id'] : null,
                isset($args['unit']) ? (string) $args['unit'] : null,
            );
        } catch (\Throwable $e) {
            throw new McpException(-32602, $e->getMessage());
        }

        return $this->jsonResult($result);
    }

    /** Kompaktes JSON-Resultat für ein Tool. */
    private function jsonResult(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // ── Helfer ────────────────────────────────────────────────────────────────

    private function resolveTemplate(string $slug): ApiTemplate
    {
        $template = ApiTemplate::where('slug', $slug)
            ->active()
            ->where('is_mcp_enabled', true)
            ->first();

        if (!$template) {
            throw new McpException(-32602, "Template mit Slug \"{$slug}\" nicht gefunden, inaktiv oder nicht für MCP freigegeben.");
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

    /**
     * Jeden erfolgreichen tools/call asynchron ins Journal (audit_logs) schreiben.
     * auditable_type = "MCP", auditable_id = Template-Slug oder Tool-Name.
     * new_values enthält Tool + bereinigte Argumente (keine Passwörter/Tokens).
     */
    private function logMcpCall(string $toolName, array $args): void
    {
        $slug = $args['slug'] ?? null;

        // Filter-Werte nicht loggen (können sensible Daten enthalten), nur Feld-Namen
        $logArgs = $args;
        if (isset($logArgs['filters']) && is_array($logArgs['filters'])) {
            $logArgs['filters'] = array_map(
                fn ($f) => ['field' => $f['field'] ?? '?', 'operator' => $f['operator'] ?? 'eq'],
                $logArgs['filters']
            );
        }

        WriteAuditLog::dispatch(
            auditableType: 'MCP',
            auditableId:   $slug ?? $toolName,
            action:        'mcp_query',
            newValues:     ['tool' => $toolName, 'args' => $logArgs],
            ipAddress:     $this->currentRequest?->ip(),
            userAgent:     $this->currentRequest?->userAgent(),
        );
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

    /**
     * Baut einen MySQL BOOLEAN MODE Suchterm: jedes Wort bekommt ein führendes +
     * (AND-Semantik) und einen abschließenden * (Prefix-Match).
     * Einzelzeichen und MySQL-Sonderzeichen werden bereinigt.
     */
    private function toBooleanSearchTerm(string $query): string
    {
        $words = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY);
        $terms = [];
        foreach ($words as $word) {
            // MySQL BOOLEAN-Sonderzeichen escapen
            $clean = preg_replace('/[+\-><()~*"@]/', '', $word);
            if ($clean !== null && mb_strlen($clean) >= 2) {
                $terms[] = '+' . $clean . '*';
            }
        }
        // Fallback: keine gültigen Terme (z.B. alles Einzelzeichen) → Prefix-Suche
        return $terms !== [] ? implode(' ', $terms) : trim($query) . '*';
    }

    /**
     * Ersetzt deutsche Umlaute durch ASCII-Äquivalente für kollationsresistente Suche.
     * MySQL FULLTEXT-Indizes mit utf8mb4_unicode_ci matchen Umlaute oft nicht
     * korrekt gegen umlautfreie Indexeinträge und umgekehrt.
     */
    private function normalizeUmlauts(string $text): string
    {
        return str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
            ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'],
            $text
        );
    }
}
