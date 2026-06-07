<?php

declare(strict_types=1);

namespace App\Services\Copilot;

use App\Models\ApiTemplate;
use App\Services\ApiDesigner\GraphqlDesignerService;
use App\Services\Pim\ProductAttributeWriter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;

/**
 * Copilot-Service — In-App Chat-Assistent für anyPIM.
 *
 * anyPIM tritt hier als MCP-*Host* auf: Der Service ruft die Anthropic Messages
 * API auf und bindet den bestehenden anyPIM-MCP-Endpoint via MCP-Connector ein
 * (mcp_servers). Claude kann so eigenständig die lesenden PIM-Tools nutzen.
 *
 * Schreibende Aktionen (graphql_mutate) laufen NICHT über den Connector, sondern
 * als client-seitiges Tool — damit das Frontend jede Mutation vor der Ausführung
 * vom Nutzer bestätigen lassen kann (Human-in-the-loop).
 */
final class CopilotService
{
    private const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';

    /** Beta-Header für den MCP-Connector der Messages API. */
    private const MCP_BETA = 'mcp-client-2025-11-20';

    /**
     * Nur-lesende MCP-Tools, die der Connector automatisch ausführen darf.
     * graphql_mutate ist bewusst NICHT enthalten (Bestätigungs-Flow im Client).
     */
    public const READ_ONLY_TOOLS = [
        'list_templates',
        'stream_products',
        'search_products',
        'graphql_query',
        'get_schema',
        'list_attributes',
        'list_hierarchies',
        'list_hierarchy_nodes',
        'list_node_attributes',
        'list_node_products',
    ];

    public function __construct(
        private readonly GraphqlDesignerService $graphqlDesignerService,
        private readonly ProductAttributeWriter $attributeWriter,
    ) {}

    /**
     * Öffnet den Streaming-Request gegen die Anthropic Messages API und gibt den
     * rohen SSE-Body-Stream zurück (wird vom Controller 1:1 durchgereicht).
     *
     * @param  array<int, array<string, mixed>>  $messages  Vollständiger Verlauf (vom Client)
     * @param  array<string, mixed>              $context   Aktueller UI-Kontext (Route, SKU, …)
     */
    public function openStream(array $messages, array $context = []): StreamInterface
    {
        $config = config('connectors.copilot');
        $apiKey = (string) ($config['api_key'] ?? '');

        if ($apiKey === '') {
            throw new \RuntimeException('Copilot ist nicht konfiguriert (CLAUDE_AI_API_KEY/COPILOT_API_KEY fehlt).');
        }

        $mcpToken = (string) config('services.mcp.token');
        if ($mcpToken === '') {
            throw new \RuntimeException('MCP-Endpoint ist nicht konfiguriert (MCP_AUTH_TOKEN fehlt) — der Copilot kann keine PIM-Daten abrufen.');
        }

        $payload = [
            'model'      => (string) $config['model'],
            'max_tokens' => (int) $config['max_tokens'],
            'stream'     => true,
            'system'     => $this->buildSystemPrompt($context),
            'messages'   => $messages,
            'mcp_servers' => [[
                'type'                => 'url',
                'name'                => 'anypim',
                'url'                 => (string) $config['mcp_url'],
                'authorization_token' => $mcpToken,
            ]],
            'tools' => [
                // Referenziert den MCP-Server (Pflicht). Allowlist: nur die
                // Lese-Tools werden exponiert (graphql_mutate bleibt aus →
                // läuft als Client-Tool mit Bestätigung).
                [
                    'type'            => 'mcp_toolset',
                    'mcp_server_name' => 'anypim',
                    'default_config'  => ['enabled' => false],
                    'configs'         => $this->readOnlyToolConfigs(),
                ],
                // Schreibende Tools als Client-Tools → stoppen mit stop_reason
                // "tool_use", damit das Frontend den Bestätigungs-Dialog zeigt.
                $this->mutationToolDefinition(),
                $this->attributeUpdateToolDefinition(),
            ],
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'anthropic-beta'    => self::MCP_BETA,
                'content-type'      => 'application/json',
            ])
                ->withOptions(['stream' => true])
                ->timeout(180)
                ->post(self::ANTHROPIC_URL, $payload);
        } catch (ConnectionException $e) {
            Log::warning('Copilot: Verbindung zu Claude fehlgeschlagen', ['error' => $e->getMessage()]);
            throw new \RuntimeException(
                'Die Verbindung zu Claude hat zu lange gedauert oder ist fehlgeschlagen. Bitte versuche es in einem Moment erneut.'
            );
        }

        $psr    = $response->toPsrResponse();
        $status = $psr->getStatusCode();

        // Bei Fehler-Status liefert Anthropic ein JSON (kein SSE). Technisches
        // Detail nur ins Log; dem Nutzer wird eine verständliche Meldung gezeigt.
        if ($status >= 400) {
            $errorBody = (string) $psr->getBody();

            Log::warning('Copilot: Anthropic-Anfrage fehlgeschlagen', [
                'status' => $status,
                'body'   => mb_substr($errorBody, 0, 2000),
            ]);

            throw new \RuntimeException($this->friendlyError($status, $errorBody));
        }

        return $psr->getBody();
    }

    /**
     * Übersetzt einen Anthropic-Fehlerstatus in eine verständliche Meldung
     * für Endnutzer (Technisches steht im Log).
     */
    private function friendlyError(int $status, string $body): string
    {
        $type = '';
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $type = (string) ($decoded['error']['type'] ?? '');
        }

        return match (true) {
            $status === 401 || $type === 'authentication_error'
                => 'Der Copilot ist nicht korrekt mit Claude verbunden (ungültiger API-Schlüssel). Bitte wende dich an deinen Administrator.',
            $status === 403 || $type === 'permission_error'
                => 'Der Copilot hat für diese Anfrage keine Berechtigung. Bitte wende dich an deinen Administrator.',
            $status === 429 || $type === 'rate_limit_error'
                => 'Claude ist gerade stark ausgelastet. Bitte versuche es in einem Moment erneut.',
            $status === 529 || $type === 'overloaded_error'
                => 'Claude ist vorübergehend überlastet. Bitte versuche es in Kürze erneut.',
            $status >= 500
                => 'Claude ist vorübergehend nicht erreichbar. Bitte versuche es später erneut.',
            default
                => 'Beim Verarbeiten deiner Anfrage ist ein Fehler aufgetreten. Bitte versuche es erneut oder formuliere die Frage anders.',
        };
    }

    /**
     * Führt eine vom Nutzer bestätigte graphql_mutate-Aktion aus.
     * Validierung analog zum McpController (Template aktiv, GraphQL, schreibfähig).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function executeMutation(array $input): array
    {
        $slug     = trim((string) ($input['slug'] ?? ''));
        $mutation = trim((string) ($input['mutation'] ?? ''));

        if ($slug === '' || $mutation === '') {
            throw new \InvalidArgumentException('slug und mutation sind erforderlich.');
        }

        $template = ApiTemplate::where('slug', $slug)
            ->active()
            ->where('is_mcp_enabled', true)
            ->first();

        if (!$template) {
            throw new \RuntimeException("Template \"{$slug}\" nicht gefunden, inaktiv oder nicht für MCP freigegeben.");
        }

        if ($template->output_format !== 'graphql') {
            throw new \RuntimeException("Template \"{$slug}\" ist ein JSON-Template und unterstützt keine Mutationen.");
        }

        if (!in_array($template->direction, ['import', 'bidirectional'], true)) {
            throw new \RuntimeException("Template \"{$slug}\" unterstützt keine Mutationen (direction muss import/bidirectional sein).");
        }

        $variables = $input['variables'] ?? null;

        return $this->graphqlDesignerService->execute(
            $template,
            $mutation,
            is_array($variables) ? $variables : null,
        );
    }

    /**
     * Führt eine vom Nutzer bestätigte update_product_attribute-Aktion aus.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function executeAttributeUpdate(array $input): array
    {
        $product   = trim((string) ($input['product'] ?? ''));
        $attribute = trim((string) ($input['attribute'] ?? ''));

        if ($product === '' || $attribute === '') {
            throw new \InvalidArgumentException('product und attribute sind erforderlich.');
        }
        if (!array_key_exists('value', $input)) {
            throw new \InvalidArgumentException('value ist erforderlich.');
        }

        return $this->attributeWriter->update(
            $product,
            $attribute,
            $input['value'],
            isset($input['language']) ? (string) $input['language'] : null,
            isset($input['value_selection_id']) ? (string) $input['value_selection_id'] : null,
            isset($input['unit']) ? (string) $input['unit'] : null,
        );
    }

    /**
     * Baut die per-Tool-Allowlist für den mcp_toolset: jedes Lese-Tool
     * explizit aktivieren (default_config.enabled = false greift für den Rest).
     *
     * @return array<string, array{enabled: bool}>
     */
    private function readOnlyToolConfigs(): array
    {
        $configs = [];
        foreach (self::READ_ONLY_TOOLS as $tool) {
            $configs[$tool] = ['enabled' => true];
        }

        return $configs;
    }

    /**
     * Schema des client-seitigen Schreib-Tools (entspricht dem MCP-graphql_mutate).
     *
     * @return array<string, mixed>
     */
    private function mutationToolDefinition(): array
    {
        return [
            'name'        => 'graphql_mutate',
            'description' => 'Führt eine GraphQL-Mutation gegen ein bidirektionales API-Template aus (Produktdaten SCHREIBEN/ändern). '
                . 'Nur für Templates mit direction "import" oder "bidirectional". '
                . 'WICHTIG: Diese Aktion ändert echte Produktdaten und wird dem Nutzer vor der Ausführung '
                . 'immer zur Bestätigung vorgelegt. Beschreibe die geplante Änderung vorher klar und nachvollziehbar.',
            'input_schema' => [
                'type'       => 'object',
                'required'   => ['slug', 'mutation'],
                'properties' => [
                    'slug'      => ['type' => 'string', 'description' => 'URL-Slug des API-Templates'],
                    'mutation'  => ['type' => 'string', 'description' => 'GraphQL Mutation-String'],
                    'variables' => ['type' => 'object', 'description' => 'GraphQL-Variablen als Objekt'],
                ],
            ],
        ];
    }

    /**
     * Schema des client-seitigen Attribut-Schreib-Tools (Bestätigungs-Flow).
     *
     * @return array<string, mixed>
     */
    private function attributeUpdateToolDefinition(): array
    {
        return [
            'name'        => 'update_product_attribute',
            'description' => 'Setzt EINEN Attributwert EINES Produkts (SCHREIBEND). '
                . 'VORGEHEN: (1) genaues Produkt bestimmen — bei mehreren/unklaren Treffern erst per search_products eingrenzen und rückfragen, nicht raten. '
                . '(2) Attribut via list_attributes prüfen (übersetzbar → "language"; has_unit/default_unit → "unit" angeben, z.B. "g"). (3) Genau EIN Produkt, EIN Wert pro Aufruf. '
                . 'Produkt per UUID/SKU, Attribut per UUID/technical_name. WICHTIG: ändert echte Produktdaten und wird dem Nutzer vor der Ausführung immer zur Bestätigung vorgelegt.',
            'input_schema' => [
                'type'       => 'object',
                'required'   => ['product', 'attribute', 'value'],
                'properties' => [
                    'product'            => ['type' => 'string', 'description' => 'Produkt-UUID oder SKU (genau EIN Produkt)'],
                    'attribute'          => ['type' => 'string', 'description' => 'Attribut-UUID oder technical_name'],
                    'value'              => ['description' => 'Neuer Wert ohne Einheit (String, Zahl, Boolean oder ISO-Datum)'],
                    'language'           => ['type' => 'string', 'description' => 'Sprachcode (Pflicht bei übersetzbaren Attributen, z.B. "de")'],
                    'value_selection_id' => ['type' => 'string', 'description' => 'ValueListEntry-UUID bei Selection-Attributen'],
                    'unit'               => ['type' => 'string', 'description' => 'Einheit (z.B. "g", "kg", "mm") — nur/erforderlich bei Attributen mit Einheit'],
                ],
            ],
        ];
    }

    /**
     * Baut den System-Prompt inkl. aktuellem UI-Kontext (macht aus dem Chat
     * einen kontextbewussten Co-Piloten).
     *
     * @param  array<string, mixed>  $context
     */
    private function buildSystemPrompt(array $context): string
    {
        $prompt = "Du bist der anyPIM Copilot, ein hilfreicher Assistent direkt im PIM-System "
            . "(Product Information Management). Du hilfst Anwendern, Produktdaten zu finden, zu "
            . "verstehen und zu pflegen. Antworte präzise und in der Sprache des Nutzers (Standard: Deutsch).\n\n"
            . "Nutze die verfügbaren MCP-Werkzeuge, um echte PIM-Daten abzurufen, statt zu raten:\n"
            . "- list_templates: zeigt verfügbare API-Templates mit ihren Slugs.\n"
            . "- search_products: Volltext- und Attributsuche über Produkte.\n"
            . "- stream_products / graphql_query: Produktdaten abrufen.\n"
            . "- get_schema: GraphQL-Schema eines Templates ansehen.\n"
            . "- list_attributes: Attribut-Definitionen inkl. UUID (für gezielte Updates).\n"
            . "- list_hierarchies / list_hierarchy_nodes: Klassifikationen und ihre Knoten.\n"
            . "- list_node_attributes / list_node_products: zugeordnete Attribute bzw. Produkte eines Knotens.\n"
            . "Bei Unsicherheit über einen gültigen Slug rufe zuerst list_templates auf.\n\n"
            . "Arbeitsabläufe (Zusammenhänge der Werkzeuge):\n"
            . "- Klassifikation erkunden: list_hierarchies → list_hierarchy_nodes (braucht hierarchy_id) → "
            . "list_node_attributes / list_node_products (brauchen node_id).\n"
            . "- Produktwert ändern: (1) Produkt eindeutig bestimmen. Sind mehrere Produkte betroffen oder ist unklar welches, "
            . "erst per search_products eingrenzen und nachfragen — NICHT raten und nicht ungefragt mehrere Produkte ändern. "
            . "(2) Attribut per list_attributes prüfen: existiert es (ggf. mehrere Treffer → nachfragen welches), ist es übersetzbar "
            . "(dann language), hat es eine Einheit (has_unit/default_unit → dann unit, z.B. \"g\"). (3) update_product_attribute "
            . "mit genau EINEM Produkt und EINEM Wert aufrufen; den Zahlenwert ohne Einheit in value, die Einheit in unit.\n\n"
            . "Schreibende Tools (graphql_mutate, update_product_attribute) ändern echte Produktdaten und werden dem Nutzer "
            . "immer zur Bestätigung vorgelegt — beschreibe die geplante Änderung vorher klar (inkl. Einheit) und beschaffe "
            . "nötige IDs/Einheiten zuvor über die Lese-Tools.";

        $lines = [];
        if (!empty($context['route'])) {
            $lines[] = '- Aktuelle Ansicht: ' . (string) $context['route'];
        }
        if (!empty($context['productSku'])) {
            $product = 'SKU ' . (string) $context['productSku'];
            if (!empty($context['productName'])) {
                $product .= ' (' . (string) $context['productName'] . ')';
            }
            $lines[] = '- Geöffnetes Produkt: ' . $product;
        }
        if (!empty($context['locale'])) {
            $lines[] = '- UI-Sprache: ' . (string) $context['locale'];
        }

        if ($lines !== []) {
            $prompt .= "\n\nAktueller Kontext des Nutzers:\n" . implode("\n", $lines);
        }

        return $prompt;
    }
}
