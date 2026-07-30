<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ConnectorConnection;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Happy-Path-Tests fuer die "aktive" Seite des anyPIM-Sync-Connectors
 * (ConnectorController::pullProducts/syncBidirectional/testAnyPimConnection
 * + App\Services\Connectors\AnyPim\*), gegen eine per Http::fake() simulierte
 * Remote-anyPIM-Instanz.
 */
class AnyPimConnectorSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ConnectorConnection $connection;

    protected const REMOTE_URL = 'https://remote-anypim.example.test';

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->mock(LicenseService::class, function ($mock) {
            $mock->shouldReceive('isModuleActive')->andReturn(true);
        });

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::findOrCreate('Admin', 'sanctum'));
        $this->actingAs($this->user);

        $this->connection = ConnectorConnection::factory()->create([
            'connector_type'    => 'anypim',
            'access_token'      => 'remote-api-key',
            'token_expires_at'  => null,
            'is_active'         => true,
            'settings'          => ['remote_url' => self::REMOTE_URL],
        ]);
    }

    public function test_pull_products_importiert_produkte_von_remote_instanz(): void
    {
        $productType = \App\Models\ProductType::factory()->create(['technical_name' => 'werkzeug']);

        Http::fake([
            self::REMOTE_URL . '/api/v1/pim-sync/products*' => Http::response([
                'products' => [[
                    'sku'          => 'REMOTE-001',
                    'ean'          => null,
                    'name'         => 'Remote Produkt',
                    'status'       => 'active',
                    'product_type' => $productType->technical_name,
                    'manufacturer' => null,
                    'attributes'   => [],
                    'prices'       => [],
                    'media'        => [],
                    '_checksum'    => 'abc',
                    '_updated_at'  => now()->toIso8601String(),
                ]],
                'meta' => ['total' => 1, 'per_page' => 100, 'current_page' => 1, 'last_page' => 1],
            ]),
        ]);

        $response = $this->postJson("/api/v1/connectors/connections/{$this->connection->id}/pull-products");

        $response->assertOk()
            ->assertJsonPath('data.pulled', 1)
            ->assertJsonPath('data.errors', 0);

        $this->assertDatabaseHas('products', [
            'sku'  => 'REMOTE-001',
            'name' => 'Remote Produkt',
        ]);

        Http::assertSent(fn ($request) => $request->url() === self::REMOTE_URL . '/api/v1/pim-sync/products?per_page=100&page=1'
            && $request->hasHeader('Authorization', 'Bearer remote-api-key'));
    }

    public function test_pull_products_delta_pullt_nur_produkte_mit_abweichender_checksum(): void
    {
        $productType = \App\Models\ProductType::factory()->create(['technical_name' => 'werkzeug']);

        Http::fake([
            self::REMOTE_URL . '/api/v1/pim-sync/checksums*' => Http::response([
                'checksums' => [
                    'REMOTE-DELTA-001' => ['checksum' => 'remote-checksum', 'updated_at' => now()->toIso8601String()],
                ],
                'total' => 1,
            ]),
            self::REMOTE_URL . '/api/v1/pim-sync/products*' => Http::response([
                'products' => [[
                    'sku'          => 'REMOTE-DELTA-001',
                    'name'         => 'Delta Produkt',
                    'status'       => 'active',
                    'product_type' => $productType->technical_name,
                    'attributes'   => [],
                    'prices'       => [],
                    'media'        => [],
                    '_checksum'    => 'remote-checksum',
                    '_updated_at'  => now()->toIso8601String(),
                ]],
                'meta' => ['total' => 1, 'per_page' => 100, 'current_page' => 1, 'last_page' => 1],
            ]),
        ]);

        $response = $this->postJson("/api/v1/connectors/connections/{$this->connection->id}/pull-products?delta=1");

        $response->assertOk()->assertJsonPath('data.pulled', 1);

        $this->assertDatabaseHas('products', ['sku' => 'REMOTE-DELTA-001']);
    }

    public function test_sync_bidirectional_fuehrt_push_und_pull_gegen_remote_instanz_aus(): void
    {
        $localProduct = Product::factory()->create(['sku' => 'LOCAL-PUSH-001']);

        Http::fake([
            self::REMOTE_URL . '/api/v1/pim-sync/checksums*' => Http::response([
                'checksums' => [],
                'total'     => 0,
            ]),
            self::REMOTE_URL . '/api/v1/pim-sync/products' => Http::response([
                'message' => 'Import abgeschlossen.',
                'stats'   => ['created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []],
            ]),
        ]);

        $response = $this->postJson("/api/v1/connectors/connections/{$this->connection->id}/sync-bidirectional");

        $response->assertOk()
            ->assertJsonPath('data.direction', 'bidirectional')
            ->assertJsonPath('data.push.pushed', 1)
            ->assertJsonPath('data.push.errors', 0)
            ->assertJsonPath('data.pull.errors', 0);

        Http::assertSent(fn ($request) => $request->url() === self::REMOTE_URL . '/api/v1/pim-sync/products'
            && $request->method() === 'POST'
            && collect($request->data()['products'] ?? [])->pluck('sku')->contains($localProduct->sku));
    }

    public function test_test_connection_liefert_ok_bei_erreichbarer_remote_instanz(): void
    {
        Http::fake([
            self::REMOTE_URL . '/api/v1/pim-sync/schema' => Http::response([
                'product_types'    => [],
                'hierarchies'      => [],
                'attribute_count'  => 3,
            ]),
        ]);

        $response = $this->postJson("/api/v1/connectors/connections/{$this->connection->id}/test-connection");

        $response->assertOk()->assertJsonPath('data.status', 'ok');
    }

    public function test_pull_config_holt_und_importiert_konfiguration_von_remote_instanz(): void
    {
        Http::fake([
            self::REMOTE_URL . '/api/v1/json-export' => Http::response([
                '_meta' => ['format' => 'anypim-json', 'version' => '1.0'],
                'product_types' => [[
                    'technical_name' => 'werkzeug-remote',
                    'name_de' => 'Werkzeug (Remote)',
                ]],
            ]),
        ]);

        $response = $this->call(
            'POST',
            "/api/v1/connectors/connections/{$this->connection->id}/pull-config",
            ['sections' => ['product_types']],
        );

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('event: complete', $content);
        $this->assertDatabaseHas('product_types', ['technical_name' => 'werkzeug-remote']);

        Http::assertSent(fn ($request) => $request->url() === self::REMOTE_URL . '/api/v1/json-export'
            && $request->method() === 'POST'
            && $request['sections'] === ['product_types']
            && $request['inline'] === true);
    }

    public function test_pull_config_ohne_sektionsauswahl_nutzt_alle_konfigurations_sektionen(): void
    {
        Http::fake([
            self::REMOTE_URL . '/api/v1/json-export' => Http::response([
                '_meta' => ['format' => 'anypim-json', 'version' => '1.0'],
            ]),
        ]);

        $response = $this->call('POST', "/api/v1/connectors/connections/{$this->connection->id}/pull-config");
        $response->streamedContent();

        Http::assertSent(function ($request) {
            $sections = $request['sections'] ?? [];
            return $request->url() === self::REMOTE_URL . '/api/v1/json-export'
                && in_array('attributes', $sections, true)
                && !in_array('products', $sections, true);
        });
    }

    public function test_pull_products_ist_nur_fuer_anypim_verbindungen_verfuegbar(): void
    {
        $shopwareConnection = ConnectorConnection::factory()->create(['connector_type' => 'shopware']);

        $this->postJson("/api/v1/connectors/connections/{$shopwareConnection->id}/pull-products")
            ->assertStatus(422);
    }

    public function test_missing_media_count_liefert_anzahl_fehlender_dateien(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        // Datei existiert nicht auf dem Disk (typisch nach JSON-Import ohne Dateiübertragung)
        \App\Models\Media::factory()->create(['file_path' => 'media/fehlt-1.jpg']);
        \App\Models\Media::factory()->create(['file_path' => 'media/fehlt-2.jpg']);

        // Diese Datei existiert wirklich → zählt nicht mit
        \Illuminate\Support\Facades\Storage::disk('public')->put('media/vorhanden.jpg', 'inhalt');
        \App\Models\Media::factory()->create(['file_path' => 'media/vorhanden.jpg']);

        $response = $this->getJson("/api/v1/connectors/connections/{$this->connection->id}/missing-media-count");

        $response->assertOk();
        $this->assertSame(2, $response->json('data.missing'));
    }

    public function test_pull_missing_media_laedt_fehlende_dateien_von_remote_instanz(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $media = \App\Models\Media::factory()->create([
            'file_name' => 'produktbild.jpg',
            'file_path' => 'media/produktbild.jpg',
            'file_size' => 0,
        ]);

        Http::fake([
            self::REMOTE_URL . '/storage/media/produktbild.jpg' => Http::response(
                'binaerdaten-des-bildes',
                200,
                ['Content-Type' => 'image/jpeg'],
            ),
        ]);

        $response = $this->call('POST', "/api/v1/connectors/connections/{$this->connection->id}/pull-missing-media");
        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('event: complete', $content);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists('media/produktbild.jpg');

        $media->refresh();
        $this->assertSame(strlen('binaerdaten-des-bildes'), $media->file_size);
        $this->assertSame('image/jpeg', $media->mime_type);
    }
}
