<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\McpException;
use App\Http\Controllers\Controller;
use App\Jobs\WriteAuditLog;
use App\Models\ApiTemplate;
use App\Models\Attribute;
use App\Models\ProductSearchIndex;
use App\Services\ApiDesigner\ApiDataCollector;
use App\Services\ApiDesigner\GraphqlDesignerService;
use App\Services\ApiDesigner\JsonWriter;
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

    private Request $currentRequest;

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
                    . 'Zusätzlich können strukturierte Attribut-Filter gesetzt werden (z.B. farbe=blau). '
                    . 'Gibt die Ergebnisse im Format des angegebenen Templates zurück. '
                    . 'Nutze list_templates um verfügbare Slugs zu sehen.',
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
            'search_products' => $this->toolSearchProducts($args),
            'graphql_query'   => $this->toolGraphql($args, 'query'),
            'graphql_mutate'  => $this->toolGraphql($args, 'mutation'),
            'get_schema'      => $this->toolGetSchema($args),
            default           => throw new McpException(-32602, "Unbekanntes Tool: {$name}"),
        };

        $this->logMcpCall($name, $args);

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
            ipAddress:     $this->currentRequest->ip(),
            userAgent:     $this->currentRequest->userAgent(),
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
