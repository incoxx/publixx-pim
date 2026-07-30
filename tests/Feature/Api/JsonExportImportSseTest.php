<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Exceptions\ImportCancelledException;
use App\Models\Role;
use App\Models\User;
use App\Services\Import\JsonFormatImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Tests fuer den SSE-Progress/Cancel-Pfad des JSON-Imports
 * (JsonExportImportController::importStreamed()/cancelImport(), JsonFormatImporter).
 */
class JsonExportImportSseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Admin', 'sanctum'));
        $this->actingAs($user);
    }

    public function test_streamed_import_sendet_progress_und_complete_events(): void
    {
        $payload = [
            '_meta' => ['format' => 'anypim-json', 'version' => '1.0'],
            'unit_groups' => [
                ['technical_name' => 'sse-test-gruppe', 'name_de' => 'SSE Test Gruppe', 'name_en' => 'SSE Test Group'],
            ],
            'units' => [
                ['technical_name' => 'sse-test-einheit', 'abbreviation' => 'ste', 'unit_group' => 'sse-test-gruppe', 'is_base_unit' => true],
            ],
        ];

        $response = $this->call(
            'POST',
            '/api/v1/json-import',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => 'text/event-stream', 'CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        );

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));

        $content = $response->streamedContent();

        $this->assertStringContainsString('event: progress', $content);
        $this->assertStringContainsString('event: complete', $content);
        $this->assertStringContainsString('import_id', $content);

        $this->assertDatabaseHas('unit_groups', ['technical_name' => 'sse-test-gruppe']);
    }

    public function test_cancel_endpoint_setzt_abbruch_flag_fuer_import_id(): void
    {
        $importId = '11111111-1111-1111-1111-111111111111';

        $this->postJson('/api/v1/json-import/cancel', ['import_id' => $importId])
            ->assertOk()
            ->assertJsonPath('import_id', $importId);

        $this->assertTrue((bool) Cache::get("bmecat_import_cancel_{$importId}"));
    }

    public function test_import_wird_abgebrochen_wenn_cancel_flag_vor_sheet_gesetzt_ist(): void
    {
        $importId = '22222222-2222-2222-2222-222222222222';
        JsonFormatImporter::cancelImport($importId);

        $importer = app(JsonFormatImporter::class);
        $importer->setImportId($importId);

        $this->expectException(ImportCancelledException::class);

        $importer->importData([
            '_meta' => ['format' => 'anypim-json', 'version' => '1.0'],
            'unit_groups' => [
                ['technical_name' => 'wird-nie-angelegt', 'name_de' => 'Wird nie angelegt'],
            ],
            'units' => [
                ['technical_name' => 'wird-nie-angelegt', 'abbreviation' => 'wna', 'unit_group' => 'wird-nie-angelegt', 'is_base_unit' => true],
            ],
        ]);
    }
}
