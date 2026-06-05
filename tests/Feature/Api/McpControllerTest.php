<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ApiTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonCount(6, 'result.tools');
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
            'params' => ['protocolVersion' => '2025-06-18'],
        ], $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('jsonrpc', '2.0')
            ->assertJsonPath('id', 1)
            ->assertJsonPath('result.serverInfo.name', 'anyPIM')
            ->assertJsonPath('result.protocolVersion', '2025-06-18');
    }

    public function test_tools_list_returns_six_tools(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list',
        ], $this->authHeader());

        $response->assertOk();
        $tools = $response->json('result.tools');
        $this->assertCount(6, $tools);

        $names = array_column($tools, 'name');
        $this->assertEqualsCanonicalizing(
            ['list_templates', 'stream_products', 'search_products', 'graphql_query', 'graphql_mutate', 'get_schema'],
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
}
