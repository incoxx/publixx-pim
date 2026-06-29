<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\SectionType;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->mock(LicenseService::class, function ($mock) {
            $mock->shouldReceive('isModuleActive')->andReturn(true);
        });

        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Admin', 'sanctum'));
        $this->actingAs($user);
    }

    public function test_store_mit_feldern(): void
    {
        $this->postJson('/api/v1/section-types', [
            'technical_name' => 'mein-block',
            'name_de' => 'Mein Block',
            'category' => 'text',
            'schema' => ['fields' => [['key' => 'titel', 'label' => 'Titel', 'type' => 'String']]],
        ])->assertCreated()->assertJsonPath('data.technical_name', 'mein-block');
    }

    public function test_store_ohne_felder_erlaubt(): void
    {
        // Feldlose Bausteine (z. B. Divider) müssen speicherbar sein.
        $this->postJson('/api/v1/section-types', [
            'technical_name' => 'divider',
            'name_de' => 'Trenner',
            'category' => 'layout',
            'schema' => ['fields' => []],
        ])->assertCreated();

        $this->assertDatabaseHas('section_types', ['technical_name' => 'divider']);
    }

    public function test_store_feld_ohne_key_invalide(): void
    {
        $this->postJson('/api/v1/section-types', [
            'technical_name' => 'kaputt',
            'name_de' => 'Kaputt',
            'schema' => ['fields' => [['label' => 'Ohne Key', 'type' => 'String']]],
        ])->assertStatus(422)->assertJsonValidationErrors(['schema.fields.0.key']);
    }

    public function test_system_typ_nicht_loeschbar(): void
    {
        $st = SectionType::create([
            'technical_name' => 'sys',
            'name_de' => 'Sys',
            'schema' => ['fields' => []],
            'is_system' => true,
        ]);

        $this->deleteJson("/api/v1/section-types/{$st->id}")->assertStatus(422);
        $this->assertDatabaseHas('section_types', ['id' => $st->id]);
    }
}
