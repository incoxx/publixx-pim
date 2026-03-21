<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ConnectorConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_is_not_expired_when_no_expiry(): void
    {
        $connection = ConnectorConnection::factory()->create([
            'token_expires_at' => null,
        ]);

        $this->assertFalse($connection->isTokenExpired());
    }

    public function test_token_is_expired_when_in_past(): void
    {
        $connection = ConnectorConnection::factory()->create([
            'token_expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($connection->isTokenExpired());
    }

    public function test_token_is_not_expired_when_in_future(): void
    {
        $connection = ConnectorConnection::factory()->create([
            'token_expires_at' => now()->addDay(),
        ]);

        $this->assertFalse($connection->isTokenExpired());
    }

    public function test_active_scope_filters_inactive(): void
    {
        ConnectorConnection::factory()->create(['is_active' => true]);
        ConnectorConnection::factory()->create(['is_active' => false]);

        $this->assertCount(1, ConnectorConnection::active()->get());
    }

    public function test_of_type_scope_filters_by_connector_type(): void
    {
        ConnectorConnection::factory()->create(['connector_type' => 'shopware']);
        ConnectorConnection::factory()->create(['connector_type' => 'deepl']);

        $this->assertCount(1, ConnectorConnection::ofType('shopware')->get());
    }

    public function test_access_token_is_encrypted(): void
    {
        $connection = ConnectorConnection::factory()->create([
            'access_token' => 'my-secret-token',
        ]);

        // Reload from DB to verify encryption
        $raw = \DB::table('connector_connections')
            ->where('id', $connection->id)
            ->value('access_token');

        $this->assertNotEquals('my-secret-token', $raw);
        $this->assertEquals('my-secret-token', $connection->fresh()->access_token);
    }
}
