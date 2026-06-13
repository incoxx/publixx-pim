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
use App\Models\ProductAttributeValue;
use App\Models\ProductSearchIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class McpControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-mcp-secret-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mcp.token' => self::TOKEN]);
    }

    /** @return array<string, string> */
    private function authHeader(): array
    {
        return ['Authorization' => 'Bearer ' . self::TOKEN];
    }

    private function mcpCall(string $tool, array $args = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'tools/call',
            'params'  => ['name' => $tool, 'arguments' => $args],
        ], $this->authHeader());
    }

    private function mcpResult(string $tool, array $args = []): mixed
    {
        $text = $this->mcpCall($tool, $args)->json('result.content.0.text');
        return json_decode((string) $text, true);
    }

    private function createIndexedProduct(array $overrides = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'status' => 'active',
            'name'   => 'Testprodukt',
        ], array_intersect_key($overrides, array_flip(['sku', 'name', 'status']))));

        ProductSearchIndex::create(array_merge([
            'product_id' => $product->id,
            'sku'        => $product->sku,
            'name_de'    => $overrides['name_de'] ?? $product->name ?? 'Testprodukt',
            'status'     => 'active',
        ], array_diff_key($overrides, array_flip(['sku', 'name', 'status']))));

        return $product;
    }

    // ── Auth ───────────────────────────────────────────────────────────────────

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

    // ── Protokoll ─────────────────────────────────────────────────────────────

    public function test_initialize_returns_server_info(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18'],
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

    public function test_unbekanntes_tool_liefert_fehler(): void
    {
        $response = $this->mcpCall('nicht_vorhanden');

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    // ── list_templates ────────────────────────────────────────────────────────

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

    // ── search_products ───────────────────────────────────────────────────────

    private function createSearchTemplate(): ApiTemplate
    {
        return ApiTemplate::create([
            'name'          => 'Suchtemplate',
            'slug'          => 'suchtemplate',
            'template_json' => ['version' => 1, 'groups' => []],
            'output_format' => 'json',
            'direction'     => 'export',
            'is_active'     => true,
            'is_mcp_enabled' => true,
            'auth_type'     => 'none',
            'rate_limit'    => 60,
        ]);
    }

    public function test_search_products_findet_produkt_nach_name(): void
    {
        $this->createSearchTemplate();
        $this->createIndexedProduct(['name_de' => 'Akkubohrer Pro']);
        $this->createIndexedProduct(['name_de' => 'Sägeblatt']);

        $response = $this->mcpCall('search_products', [
            'slug'  => 'suchtemplate',
            'query' => 'Akkubohrer',
            'limit' => 10,
        ]);

        $response->assertOk();
        // Antwort ist "Suchergebnisse für ...\n{json}" — mind. 1 Treffer erwähnt
        $text = (string) $response->json('result.content.0.text');
        $this->assertStringContainsString('1 Treffer', $text);
        $this->assertStringNotContainsString('Sägeblatt', $text);
    }

    public function test_search_products_ohne_treffer_liefert_textmeldung(): void
    {
        $this->createSearchTemplate();

        $response = $this->mcpCall('search_products', [
            'slug'  => 'suchtemplate',
            'query' => 'GibtEsNichtXYZ123',
        ]);

        $response->assertOk();
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('0', $text);
    }

    public function test_search_products_ohne_template_liefert_fehler(): void
    {
        $response = $this->mcpCall('search_products', ['query' => 'test', 'slug' => 'nicht-vorhanden']);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    // ── list_attributes ───────────────────────────────────────────────────────

    public function test_list_attributes_liefert_attribute(): void
    {
        Attribute::factory()->create(['technical_name' => 'gewicht-kg', 'name_de' => 'Gewicht', 'status' => 'active']);

        $result = $this->mcpResult('list_attributes');

        $this->assertArrayHasKey('attributes', $result);
        $names = array_column($result['attributes'], 'technical_name');
        $this->assertContains('gewicht-kg', $names);
    }

    public function test_list_attributes_filtert_nach_data_type(): void
    {
        Attribute::factory()->create(['technical_name' => 'attr-number', 'data_type' => 'Number', 'status' => 'active']);
        Attribute::factory()->create(['technical_name' => 'attr-string', 'data_type' => 'String', 'status' => 'active']);

        $result = $this->mcpResult('list_attributes', ['data_type' => 'Number']);

        $types = array_column($result['attributes'], 'data_type');
        $this->assertContains('Number', $types);
        $this->assertNotContains('String', $types);
    }

    public function test_list_attributes_suche_nach_name(): void
    {
        Attribute::factory()->create(['technical_name' => 'farbe', 'name_de' => 'Farbe', 'status' => 'active']);
        Attribute::factory()->create(['technical_name' => 'groesse', 'name_de' => 'Größe', 'status' => 'active']);

        $result = $this->mcpResult('list_attributes', ['search' => 'farbe']);

        $names = array_column($result['attributes'], 'technical_name');
        $this->assertContains('farbe', $names);
        $this->assertNotContains('groesse', $names);
    }

    // ── list_hierarchies ──────────────────────────────────────────────────────

    public function test_list_hierarchies_liefert_hierarchien(): void
    {
        $h = Hierarchy::factory()->create(['technical_name' => 'master', 'name_de' => 'Master']);

        $result = $this->mcpResult('list_hierarchies');

        $this->assertArrayHasKey('hierarchies', $result);
        $ids = array_column($result['hierarchies'], 'id');
        $this->assertContains($h->id, $ids);
    }

    public function test_list_hierarchies_filtert_nach_type(): void
    {
        Hierarchy::factory()->create(['hierarchy_type' => 'master', 'technical_name' => 'h-master']);
        Hierarchy::factory()->create(['hierarchy_type' => 'output', 'technical_name' => 'h-output']);

        $result = $this->mcpResult('list_hierarchies', ['type' => 'master']);

        $types = array_column($result['hierarchies'], 'hierarchy_type');
        $this->assertContains('master', $types);
        $this->assertNotContains('output', $types);
    }

    // ── list_hierarchy_nodes ──────────────────────────────────────────────────

    public function test_list_hierarchy_nodes_liefert_nodes(): void
    {
        $h    = Hierarchy::factory()->create();
        $node = HierarchyNode::factory()->create(['hierarchy_id' => $h->id, 'name_de' => 'Elektro']);

        $result = $this->mcpResult('list_hierarchy_nodes', ['hierarchy_id' => $h->id]);

        $this->assertArrayHasKey('nodes', $result);
        $ids = array_column($result['nodes'], 'id');
        $this->assertContains($node->id, $ids);
    }

    public function test_list_hierarchy_nodes_ohne_id_liefert_fehler(): void
    {
        $response = $this->mcpCall('list_hierarchy_nodes', []);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_list_hierarchy_nodes_unbekannte_id_liefert_fehler(): void
    {
        $response = $this->mcpCall('list_hierarchy_nodes', ['hierarchy_id' => 'nicht-vorhanden']);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    // ── list_node_attributes ──────────────────────────────────────────────────

    public function test_list_node_attributes_liefert_zugewiesene_attribute(): void
    {
        $h    = Hierarchy::factory()->create();
        $node = HierarchyNode::factory()->create(['hierarchy_id' => $h->id]);
        $attr = Attribute::factory()->create(['technical_name' => 'laenge-mm', 'status' => 'active']);
        HierarchyNodeAttributeAssignment::create([
            'hierarchy_node_id' => $node->id,
            'attribute_id'      => $attr->id,
            'collection_sort'   => 0,
            'attribute_sort'    => 0,
            'access_product'    => 'readwrite',
        ]);

        $result = $this->mcpResult('list_node_attributes', ['node_id' => $node->id]);

        $this->assertArrayHasKey('attributes', $result);
        $names = array_column($result['attributes'], 'technical_name');
        $this->assertContains('laenge-mm', $names);
    }

    public function test_list_node_attributes_ohne_id_liefert_fehler(): void
    {
        $response = $this->mcpCall('list_node_attributes', []);

        $response->assertOk()->assertJsonPath('error.code', -32602);
    }

    // ── list_node_products ────────────────────────────────────────────────────

    public function test_list_node_products_liefert_zugewiesene_produkte(): void
    {
        $h       = Hierarchy::factory()->create();
        $node    = HierarchyNode::factory()->create(['hierarchy_id' => $h->id]);
        $product = Product::factory()->create(['status' => 'active']);

        OutputHierarchyProductAssignment::create([
            'hierarchy_node_id' => $node->id,
            'product_id'        => $product->id,
            'sort_order'        => 0,
        ]);

        $result = $this->mcpResult('list_node_products', ['node_id' => $node->id]);

        $this->assertArrayHasKey('products', $result);
        $ids = array_column($result['products'], 'id');
        $this->assertContains($product->id, $ids);
    }

    public function test_list_node_products_ohne_id_liefert_fehler(): void
    {
        $response = $this->mcpCall('list_node_products', []);

        $response->assertOk()->assertJsonPath('error.code', -32602);
    }

    // ── update_product_attribute ──────────────────────────────────────────────

    public function test_update_product_attribute_legt_wert_an(): void
    {
        $product = Product::factory()->create(['sku' => 'TEST-MCP-001']);
        $attr    = Attribute::factory()->create([
            'technical_name' => 'mcp-gewicht',
            'data_type'      => 'String',
            'is_translatable' => false,
            'status'         => 'active',
        ]);

        $result = $this->mcpResult('update_product_attribute', [
            'product'   => 'TEST-MCP-001',
            'attribute' => 'mcp-gewicht',
            'value'     => 'leicht',
        ]);

        $this->assertArrayHasKey('attribute', $result);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id'   => $product->id,
            'attribute_id' => $attr->id,
        ]);
    }

    public function test_update_product_attribute_unbekannte_sku_liefert_fehler(): void
    {
        Attribute::factory()->create(['technical_name' => 'mcp-attr', 'data_type' => 'String']);

        $response = $this->mcpCall('update_product_attribute', [
            'product'   => 'NICHT-VORHANDEN',
            'attribute' => 'mcp-attr',
            'value'     => 'test',
        ]);

        $response->assertOk()->assertJsonPath('error.code', -32602);
    }

    public function test_update_product_attribute_unbekanntes_attribut_liefert_fehler(): void
    {
        Product::factory()->create(['sku' => 'TEST-MCP-002']);

        $response = $this->mcpCall('update_product_attribute', [
            'product'   => 'TEST-MCP-002',
            'attribute' => 'nicht-vorhanden',
            'value'     => 'test',
        ]);

        $response->assertOk()->assertJsonPath('error.code', -32602);
    }

    public function test_update_product_attribute_fehlende_parameter_liefern_fehler(): void
    {
        $response = $this->mcpCall('update_product_attribute', [
            'product' => 'SKU-OHNE-ATTR',
        ]);

        $response->assertOk()->assertJsonPath('error.code', -32602);
    }

    // ── get_schema ────────────────────────────────────────────────────────────

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

    public function test_get_schema_unbekannter_slug_liefert_fehler(): void
    {
        $response = $this->mcpCall('get_schema', ['slug' => 'nicht-vorhanden']);

        $response->assertOk()->assertJsonPath('error.code', -32602);
    }

    // ── testCall-Endpoint ─────────────────────────────────────────────────────

    public function test_test_call_endpoint_erfordert_admin(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/mcp-test-call', [
            'tool' => 'list_templates',
        ]);

        $response->assertStatus(403);
    }

    public function test_test_call_endpoint_fuehrt_tool_aus(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $user = User::factory()->create();
        $role = \App\Models\Role::findOrCreate('Admin', 'sanctum');
        $user->assignRole($role);
        $this->actingAs($user);

        ApiTemplate::create([
            'name' => 'TC-Test', 'slug' => 'tc-test',
            'template_json' => ['version' => 1, 'groups' => []],
            'output_format' => 'json', 'direction' => 'export',
            'is_active' => true, 'is_mcp_enabled' => true,
            'auth_type' => 'none', 'rate_limit' => 60,
        ]);

        $response = $this->postJson('/api/v1/mcp-test-call', [
            'tool'      => 'list_templates',
            'arguments' => [],
        ]);

        $response->assertOk()
            ->assertJsonPath('tool', 'list_templates')
            ->assertJsonPath('is_error', null);
    }

    // ── log_mcp_call ──────────────────────────────────────────────────────────

    public function test_log_mcp_call_does_not_crash_when_current_request_is_null(): void
    {
        // logMcpCall() nutzt $this->currentRequest?-> (nullable).
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
}
