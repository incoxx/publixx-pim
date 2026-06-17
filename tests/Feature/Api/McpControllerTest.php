<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ApiTemplate;
use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class McpControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-mcp-secret-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mcp.token' => self::TOKEN]);
        Queue::fake();
    }

    /** @return array<string, string> */
    private function authHeader(): array
    {
        return ['Authorization' => 'Bearer ' . self::TOKEN];
    }

    private function mcpCall(string $tool, array $args = [], int $id = 1): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call',
            'params'  => ['name' => $tool, 'arguments' => $args],
        ], $this->authHeader());
    }

    /** Minimale JSON-Template-Struktur mit sku+name Feldern. */
    private function makeJsonTemplate(array $overrides = []): ApiTemplate
    {
        return ApiTemplate::create(array_merge([
            'name'          => 'JSON Test Template',
            'slug'          => 'json-test',
            'template_json' => [
                'version' => 1,
                'groups'  => [[
                    'id'        => 'g1',
                    'field'     => 'none',
                    'label'     => 'Alle',
                    'sortOrder' => 'asc',
                    'header'    => ['elements' => []],
                    'footer'    => ['elements' => []],
                    'groups'    => [],
                    'detail'    => ['elements' => [
                        ['type' => 'field', 'field' => 'sku',  'jsonKey' => 'sku',  'dataType' => 'string'],
                        ['type' => 'field', 'field' => 'name', 'jsonKey' => 'name', 'dataType' => 'string'],
                    ]],
                ]],
            ],
            'output_format'  => 'json',
            'direction'      => 'export',
            'language'       => 'de',
            'is_active'      => true,
            'is_mcp_enabled' => true,
            'auth_type'      => 'none',
            'rate_limit'     => 60,
        ], $overrides));
    }

    private function makeGraphqlTemplate(array $overrides = []): ApiTemplate
    {
        return ApiTemplate::create(array_merge([
            'name'          => 'GraphQL Test Template',
            'slug'          => 'graphql-test',
            'template_json' => [
                'version' => 1,
                'groups'  => [[
                    'id'        => 'g1',
                    'field'     => 'none',
                    'label'     => 'Alle',
                    'sortOrder' => 'asc',
                    'header'    => ['elements' => []],
                    'footer'    => ['elements' => []],
                    'groups'    => [],
                    'detail'    => ['elements' => [
                        ['type' => 'field', 'field' => 'sku',    'jsonKey' => 'sku',    'dataType' => 'string'],
                        ['type' => 'field', 'field' => 'name',   'jsonKey' => 'name',   'dataType' => 'string'],
                        ['type' => 'field', 'field' => 'status', 'jsonKey' => 'status', 'dataType' => 'string'],
                    ]],
                ]],
            ],
            'output_format'  => 'graphql',
            'direction'      => 'export',
            'language'       => 'de',
            'is_active'      => true,
            'is_mcp_enabled' => true,
            'auth_type'      => 'none',
            'rate_limit'     => 60,
        ], $overrides));
    }

    // ── Existing tests (preserved unchanged) ──────────────────────────────────

    public function test_rejects_missing_token(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
        ]);

        $response->assertStatus(401);
    }

    public function test_rejects_wrong_token(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
        ], ['Authorization' => 'Bearer falsch']);

        $response->assertStatus(401);
    }

    public function test_accepts_token_in_url_path(): void
    {
        $response = $this->postJson('/api/v1/mcp/' . self::TOKEN, [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
        ]);

        $response->assertOk()
            ->assertJsonCount(12, 'result.tools');
    }

    public function test_rejects_wrong_token_in_url_path(): void
    {
        $response = $this->postJson('/api/v1/mcp/falsches-token', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
        ]);

        $response->assertStatus(401);
    }

    public function test_disabled_when_no_token_configured(): void
    {
        config(['services.mcp.token' => null]);

        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
        ], $this->authHeader());

        $response->assertStatus(503);
    }

    public function test_initialize_returns_server_info(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
            'params'  => ['protocolVersion' => '2025-06-18'],
        ], $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('jsonrpc', '2.0')
            ->assertJsonPath('id', 1)
            ->assertJsonPath('result.serverInfo.name', 'anyPIM')
            ->assertJsonPath('result.protocolVersion', '2025-06-18');
    }

    public function test_tools_list_returns_all_tools(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list',
        ], $this->authHeader());

        $response->assertOk();
        $tools = $response->json('result.tools');
        $this->assertCount(12, $tools);

        $names = array_column($tools, 'name');
        $this->assertEqualsCanonicalizing(
            [
                'list_templates', 'stream_products', 'search_products',
                'graphql_query', 'graphql_mutate', 'get_schema',
                'list_attributes', 'list_hierarchies', 'list_hierarchy_nodes',
                'list_node_attributes', 'list_node_products', 'update_product_attribute',
            ],
            $names,
        );
    }

    public function test_unknown_method_returns_jsonrpc_error(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => 3, 'method' => 'does/not/exist',
        ], $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('error.code', -32601);
    }

    public function test_notification_returns_no_content(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'method' => 'notifications/initialized',
        ], $this->authHeader());

        $response->assertStatus(202);
    }

    public function test_list_templates_tool_returns_active_templates(): void
    {
        ApiTemplate::create([
            'name'           => 'Produktkatalog',
            'slug'           => 'produktkatalog',
            'template_json'  => ['version' => 1, 'groups' => []],
            'output_format'  => 'json',
            'direction'      => 'export',
            'language'       => 'de',
            'is_active'      => true,
            'is_mcp_enabled' => true,
            'auth_type'      => 'none',
            'rate_limit'     => 60,
        ]);
        ApiTemplate::create([
            'name'           => 'Inaktiv',
            'slug'           => 'inaktiv',
            'template_json'  => ['version' => 1, 'groups' => []],
            'output_format'  => 'json',
            'direction'      => 'export',
            'is_active'      => false,
            'is_mcp_enabled' => false,
            'auth_type'      => 'none',
            'rate_limit'     => 60,
        ]);

        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call',
            'params'  => ['name' => 'list_templates', 'arguments' => []],
        ], $this->authHeader());

        $response->assertOk();
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('produktkatalog', $text);
        $this->assertStringContainsString('Produktkatalog', $text);
        $this->assertStringNotContainsString('inaktiv', $text);
    }

    public function test_get_schema_rejects_json_template(): void
    {
        ApiTemplate::create([
            'name'           => 'JSON-Only',
            'slug'           => 'json-only',
            'template_json'  => ['version' => 1, 'groups' => []],
            'output_format'  => 'json',
            'direction'      => 'export',
            'is_active'      => true,
            'is_mcp_enabled' => true,
            'auth_type'      => 'none',
            'rate_limit'     => 60,
        ]);

        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call',
            'params'  => ['name' => 'get_schema', 'arguments' => ['slug' => 'json-only']],
        ], $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_log_mcp_call_does_not_crash_when_current_request_is_null(): void
    {
        // logMcpCall() nutzt $this->currentRequest?-> (nullable).
        // list_templates führt logMcpCall aus — der Request ist in handle() gesetzt,
        // aber dieser Test stellt sicher dass kein TypeError geworfen wird.
        ApiTemplate::create([
            'name'           => 'Log-Test',
            'slug'           => 'log-test',
            'template_json'  => ['version' => 1, 'groups' => []],
            'output_format'  => 'json',
            'direction'      => 'export',
            'is_active'      => true,
            'is_mcp_enabled' => true,
            'auth_type'      => 'none',
            'rate_limit'     => 60,
        ]);

        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call',
            'params'  => ['name' => 'list_templates', 'arguments' => []],
        ], $this->authHeader());

        $response->assertOk();
    }

    // ── search_products ───────────────────────────────────────────────────────

    public function test_search_products_findet_produkt_nach_name(): void
    {
        $template = $this->makeJsonTemplate();
        $product  = Product::factory()->active()->create(['sku' => 'SRCH-001', 'name' => 'Suchprodukt Blau']);

        DB::table('products_search_index')->insert([
            'product_id' => $product->id,
            'sku'        => $product->sku,
            'name_de'    => $product->name,
            'status'     => $product->status,
        ]);

        $response = $this->mcpCall('search_products', [
            'slug'  => $template->slug,
            'query' => 'Suchprodukt',
        ]);

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('SRCH-001', $text);
    }

    public function test_search_products_leeres_ergebnis_wenn_kein_treffer(): void
    {
        $template = $this->makeJsonTemplate();

        $response = $this->mcpCall('search_products', [
            'slug'  => $template->slug,
            'query' => 'XY-GIBT-ES-NICHT-9999',
        ]);

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('gefunden', $text);
    }

    public function test_search_products_fehler_bei_unbekanntem_slug(): void
    {
        $response = $this->mcpCall('search_products', [
            'slug'  => 'kein-solches-template',
            'query' => 'test',
        ]);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_search_products_fehler_ohne_slug(): void
    {
        // Leerer Slug -> resolveTemplate wirft McpException
        $response = $this->mcpCall('search_products', [
            'slug'  => '',
            'query' => 'test',
        ]);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    // ── stream_products ───────────────────────────────────────────────────────

    public function test_stream_products_liefert_alle_produkte(): void
    {
        $template = $this->makeJsonTemplate();
        Product::factory()->active()->create(['sku' => 'STRM-001', 'name' => 'Streamprodukt']);

        $response = $this->mcpCall('stream_products', ['slug' => $template->slug]);

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('STRM-001', $text);
    }

    public function test_stream_products_fehler_bei_graphql_template(): void
    {
        $template = $this->makeGraphqlTemplate();

        $response = $this->mcpCall('stream_products', ['slug' => $template->slug]);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_stream_products_mit_limit_und_offset(): void
    {
        $template = $this->makeJsonTemplate();
        Product::factory()->active()->count(3)->create();

        $response = $this->mcpCall('stream_products', [
            'slug'   => $template->slug,
            'limit'  => 1,
            'offset' => 1,
        ]);

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $this->assertNotEmpty($text);
    }

    // ── list_attributes ───────────────────────────────────────────────────────

    public function test_list_attributes_gibt_attribut_zurueck(): void
    {
        Attribute::create([
            'technical_name'  => 'test-attr',
            'name_de'         => 'Test Attribut',
            'data_type'       => 'String',
            'status'          => 'active',
            'is_translatable' => false,
        ]);

        $response = $this->mcpCall('list_attributes');

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('test-attr', $text);
        $this->assertStringContainsString('Test Attribut', $text);
    }

    public function test_list_attributes_filter_nach_data_type(): void
    {
        Attribute::create([
            'technical_name'  => 'string-attr',
            'name_de'         => 'String Attribut',
            'data_type'       => 'String',
            'status'          => 'active',
            'is_translatable' => false,
        ]);
        Attribute::create([
            'technical_name'  => 'number-attr',
            'name_de'         => 'Number Attribut',
            'data_type'       => 'Number',
            'status'          => 'active',
            'is_translatable' => false,
        ]);

        $response = $this->mcpCall('list_attributes', ['data_type' => 'Number']);

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('number-attr', $text);
        $this->assertStringNotContainsString('string-attr', $text);
    }

    public function test_list_attributes_paginierung(): void
    {
        Attribute::create(['technical_name' => 'attr-a', 'name_de' => 'A', 'data_type' => 'String', 'status' => 'active', 'is_translatable' => false]);
        Attribute::create(['technical_name' => 'attr-b', 'name_de' => 'B', 'data_type' => 'String', 'status' => 'active', 'is_translatable' => false]);
        Attribute::create(['technical_name' => 'attr-c', 'name_de' => 'C', 'data_type' => 'String', 'status' => 'active', 'is_translatable' => false]);

        $response = $this->mcpCall('list_attributes', ['limit' => 2, 'offset' => 0]);

        $response->assertOk()->assertJsonMissing(['error']);
        $decoded = json_decode($response->json('result.content.0.text'), true);
        $this->assertSame(2, $decoded['count']);
        $this->assertSame(3, $decoded['total']);
    }

    // ── list_hierarchies ──────────────────────────────────────────────────────

    public function test_list_hierarchies_gibt_hierarchie_zurueck(): void
    {
        Hierarchy::create([
            'technical_name' => 'test-hierarchie',
            'name_de'        => 'Test Hierarchie',
            'hierarchy_type' => 'master',
        ]);

        $response = $this->mcpCall('list_hierarchies');

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('test-hierarchie', $text);
        $this->assertStringContainsString('Test Hierarchie', $text);
    }

    public function test_list_hierarchies_filter_nach_typ(): void
    {
        Hierarchy::create(['technical_name' => 'master-hier', 'name_de' => 'Master', 'hierarchy_type' => 'master']);
        Hierarchy::create(['technical_name' => 'output-hier', 'name_de' => 'Output', 'hierarchy_type' => 'output']);

        $response = $this->mcpCall('list_hierarchies', ['type' => 'output']);

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('output-hier', $text);
        $this->assertStringNotContainsString('master-hier', $text);
    }

    // ── list_hierarchy_nodes ──────────────────────────────────────────────────

    public function test_list_hierarchy_nodes_fehler_ohne_hierarchy_id(): void
    {
        $response = $this->mcpCall('list_hierarchy_nodes', []);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_list_hierarchy_nodes_fehler_bei_unbekannter_id(): void
    {
        $response = $this->mcpCall('list_hierarchy_nodes', [
            'hierarchy_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_list_hierarchy_nodes_gibt_knoten_zurueck(): void
    {
        $hierarchy = Hierarchy::create([
            'technical_name' => 'node-hier',
            'name_de'        => 'Knoten-Hierarchie',
            'hierarchy_type' => 'master',
        ]);
        HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'name_de'      => 'Knoten 1',
            'depth'        => 0,
            'sort_order'   => 1,
            'path'         => 'knoten-1',
            'is_active'    => true,
        ]);

        $response = $this->mcpCall('list_hierarchy_nodes', [
            'hierarchy_id' => $hierarchy->id,
        ]);

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('Knoten 1', $text);
    }

    // ── list_node_attributes ──────────────────────────────────────────────────

    public function test_list_node_attributes_fehler_ohne_node_id(): void
    {
        $response = $this->mcpCall('list_node_attributes', []);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_list_node_attributes_gibt_zugeordnete_attribute_zurueck(): void
    {
        $hierarchy = Hierarchy::create([
            'technical_name' => 'attr-assign-hier',
            'name_de'        => 'Attribut-Zuordnung-Hierarchie',
            'hierarchy_type' => 'master',
        ]);
        $node = HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'name_de'      => 'Knoten mit Attributen',
            'depth'        => 0,
            'sort_order'   => 1,
            'path'         => 'knoten-attr',
            'is_active'    => true,
        ]);
        $attr = Attribute::create([
            'technical_name'  => 'node-attr',
            'name_de'         => 'Knoten-Attribut',
            'data_type'       => 'String',
            'status'          => 'active',
            'is_translatable' => false,
        ]);
        HierarchyNodeAttributeAssignment::create([
            'hierarchy_node_id' => $node->id,
            'attribute_id'      => $attr->id,
            'is_required'       => false,
            'collection_sort'   => 0,
            'attribute_sort'    => 0,
        ]);

        $response = $this->mcpCall('list_node_attributes', ['node_id' => $node->id]);

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('node-attr', $text);
    }

    // ── list_node_products ────────────────────────────────────────────────────

    public function test_list_node_products_fehler_ohne_node_id(): void
    {
        $response = $this->mcpCall('list_node_products', []);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_list_node_products_findet_master_produkt(): void
    {
        $hierarchy = Hierarchy::create([
            'technical_name' => 'master-prod-hier',
            'name_de'        => 'Master Produkt Hierarchie',
            'hierarchy_type' => 'master',
        ]);
        $node = HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'name_de'      => 'Master Knoten',
            'depth'        => 0,
            'sort_order'   => 1,
            'path'         => 'master-knoten',
            'is_active'    => true,
        ]);
        Product::factory()->active()->create([
            'sku'                      => 'MAST-001',
            'name'                     => 'Master Produkt',
            'master_hierarchy_node_id' => $node->id,
        ]);

        $response = $this->mcpCall('list_node_products', [
            'node_id' => $node->id,
            'type'    => 'master',
        ]);

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('MAST-001', $text);
    }

    public function test_list_node_products_findet_output_zuordnung(): void
    {
        $hierarchy = Hierarchy::create([
            'technical_name' => 'output-prod-hier',
            'name_de'        => 'Output Produkt Hierarchie',
            'hierarchy_type' => 'output',
        ]);
        $node = HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'name_de'      => 'Output Knoten',
            'depth'        => 0,
            'sort_order'   => 1,
            'path'         => 'output-knoten',
            'is_active'    => true,
        ]);
        $product = Product::factory()->active()->create([
            'sku'  => 'OUT-001',
            'name' => 'Output Produkt',
        ]);
        OutputHierarchyProductAssignment::create([
            'hierarchy_node_id' => $node->id,
            'product_id'        => $product->id,
        ]);

        $response = $this->mcpCall('list_node_products', [
            'node_id' => $node->id,
            'type'    => 'output',
        ]);

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('OUT-001', $text);
    }

    // ── update_product_attribute ──────────────────────────────────────────────

    public function test_update_product_attribute_setzt_wert(): void
    {
        $product = Product::factory()->active()->create(['sku' => 'UPD-001']);
        $attr    = Attribute::create([
            'technical_name'  => 'update-attr',
            'name_de'         => 'Update Attribut',
            'data_type'       => 'String',
            'status'          => 'active',
            'is_translatable' => false,
        ]);

        $response = $this->mcpCall('update_product_attribute', [
            'product'   => $product->sku,
            'attribute' => $attr->technical_name,
            'value'     => 'Testwert',
        ]);

        $response->assertOk()->assertJsonMissing(['error']);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id'   => $product->id,
            'attribute_id' => $attr->id,
        ]);
    }

    public function test_update_product_attribute_fehler_bei_unbekannter_sku(): void
    {
        $attr = Attribute::create([
            'technical_name'  => 'unk-prod-attr',
            'name_de'         => 'Unbekanntes Produkt Attribut',
            'data_type'       => 'String',
            'status'          => 'active',
            'is_translatable' => false,
        ]);

        $response = $this->mcpCall('update_product_attribute', [
            'product'   => 'SKU-EXISTIERT-NICHT',
            'attribute' => $attr->technical_name,
            'value'     => 'Wert',
        ]);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_update_product_attribute_fehler_bei_unbekanntem_attribut(): void
    {
        $product = Product::factory()->active()->create(['sku' => 'UPD-002']);

        $response = $this->mcpCall('update_product_attribute', [
            'product'   => $product->sku,
            'attribute' => 'attribut-existiert-nicht',
            'value'     => 'Wert',
        ]);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_update_product_attribute_fehler_ohne_product(): void
    {
        $response = $this->mcpCall('update_product_attribute', [
            'attribute' => 'irgendein-attr',
            'value'     => 'Wert',
        ]);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    // ── graphql_query ─────────────────────────────────────────────────────────

    public function test_graphql_query_fuehrt_query_aus(): void
    {
        $template = $this->makeGraphqlTemplate();
        Product::factory()->active()->create(['sku' => 'GQL-001', 'name' => 'GraphQL Produkt']);

        $response = $this->mcpCall('graphql_query', [
            'slug'  => $template->slug,
            'query' => '{ total }',
        ]);

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $decoded = json_decode($text, true);
        $this->assertArrayHasKey('data', $decoded);
    }

    public function test_graphql_query_fehler_bei_unbekanntem_slug(): void
    {
        $response = $this->mcpCall('graphql_query', [
            'slug'  => 'kein-gql-template',
            'query' => '{ total }',
        ]);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_graphql_query_fehler_bei_json_template(): void
    {
        $template = $this->makeJsonTemplate(['slug' => 'json-fuer-gql']);

        $response = $this->mcpCall('graphql_query', [
            'slug'  => $template->slug,
            'query' => '{ total }',
        ]);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    // ── graphql_mutate ────────────────────────────────────────────────────────

    public function test_graphql_mutate_fehler_bei_export_template(): void
    {
        $template = $this->makeGraphqlTemplate(['slug' => 'gql-export-only', 'direction' => 'export']);

        $response = $this->mcpCall('graphql_mutate', [
            'slug'     => $template->slug,
            'mutation' => 'mutation { placeholder }',
        ]);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_graphql_mutate_fehler_bei_json_template(): void
    {
        $template = $this->makeJsonTemplate(['slug' => 'json-fuer-mutate', 'direction' => 'import']);

        $response = $this->mcpCall('graphql_mutate', [
            'slug'     => $template->slug,
            'mutation' => 'mutation { placeholder }',
        ]);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_graphql_mutate_laeuft_gegen_import_template(): void
    {
        $template = $this->makeGraphqlTemplate([
            'slug'      => 'gql-import',
            'direction' => 'import',
        ]);

        // Eine einfache Mutation -- das Schema hat keinen Mutation-Typ,
        // daher erhalten wir einen GraphQL-Fehler, aber keinen MCP-Fehler (-32602).
        $response = $this->mcpCall('graphql_mutate', [
            'slug'     => $template->slug,
            'mutation' => 'mutation { placeholder }',
        ]);

        $response->assertOk();
        // Kein MCP-Level-Fehler (kein -32602 wegen Richtung/Format)
        $this->assertNull($response->json('error'));
        // Der Inhalt ist ein JSON-String (ggf. mit GraphQL-Fehler, aber kein Crash)
        $text = $response->json('result.content.0.text');
        $this->assertNotEmpty($text);
    }

    // ── get_schema (additional) ───────────────────────────────────────────────

    public function test_get_schema_gibt_sdl_fuer_graphql_template_zurueck(): void
    {
        $template = $this->makeGraphqlTemplate(['slug' => 'gql-schema-test']);

        $response = $this->mcpCall('get_schema', ['slug' => $template->slug]);

        $response->assertOk()->assertJsonMissing(['error']);
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('type', $text);
    }

    public function test_get_schema_fehler_bei_unbekanntem_slug(): void
    {
        $response = $this->mcpCall('get_schema', ['slug' => 'kein-template-vorhanden']);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    // ── JSON-RPC protocol edge cases ──────────────────────────────────────────

    public function test_batch_request_gibt_array_zurueck(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            ['jsonrpc' => '2.0', 'id' => 10, 'method' => 'tools/list'],
            ['jsonrpc' => '2.0', 'id' => 11, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']],
        ], $this->authHeader());

        $response->assertOk();
        $body = $response->json();
        $this->assertIsArray($body);
        $this->assertCount(2, $body);
        $ids = array_column($body, 'id');
        $this->assertContains(10, $ids);
        $this->assertContains(11, $ids);
    }

    public function test_batch_mit_notification_und_normalem_call(): void
    {
        // Notification (kein "id") wird herausgefiltert; nur der regulaere Call bekommt eine Antwort.
        $response = $this->postJson('/api/v1/mcp', [
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['jsonrpc' => '2.0', 'id' => 20, 'method' => 'tools/list'],
        ], $this->authHeader());

        $response->assertOk();
        $body = $response->json();
        $this->assertIsArray($body);
        $this->assertCount(1, $body);
        $this->assertSame(20, $body[0]['id']);
    }

    public function test_ping_gibt_leeres_objekt_zurueck(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => 99, 'method' => 'ping',
        ], $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('id', 99);
        // result soll leer/empty-object sein
        $result = $response->json('result');
        $this->assertEmpty($result);
    }

    // ── testCall endpoint ─────────────────────────────────────────────────────

    public function test_test_call_erfordert_admin_rolle(): void
    {
        // Ohne Auth -> 401
        $response = $this->postJson('/api/v1/mcp-test-call', [
            'tool'      => 'list_templates',
            'arguments' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_test_call_gibt_403_fuer_normalen_nutzer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/mcp-test-call', [
            'tool'      => 'list_templates',
            'arguments' => [],
        ]);

        $response->assertStatus(403);
    }

    public function test_test_call_fuehrt_tool_aus(): void
    {
        ApiTemplate::create([
            'name'           => 'Admin-Test',
            'slug'           => 'admin-test',
            'template_json'  => ['version' => 1, 'groups' => []],
            'output_format'  => 'json',
            'direction'      => 'export',
            'language'       => 'de',
            'is_active'      => true,
            'is_mcp_enabled' => true,
            'auth_type'      => 'none',
            'rate_limit'     => 60,
        ]);

        $user = User::factory()->create();
        $role = Role::findOrCreate('Admin', 'sanctum');
        $user->assignRole($role);
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/mcp-test-call', [
            'tool'      => 'list_templates',
            'arguments' => [],
        ]);

        $response->assertOk()
            ->assertJsonPath('tool', 'list_templates');
        $result = $response->json('result');
        $this->assertNotNull($result);
    }

    public function test_test_call_gibt_fehler_bei_unbekanntem_tool(): void
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate('Admin', 'sanctum');
        $user->assignRole($role);
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/mcp-test-call', [
            'tool'      => 'dieses-tool-existiert-nicht',
            'arguments' => [],
        ]);

        $response->assertOk()
            ->assertJsonPath('is_error', true);
    }
}
