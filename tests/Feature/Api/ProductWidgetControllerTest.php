<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ProductWidget;
use App\Models\Role;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductWidgetControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

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
    }

    public function test_store_legt_widget_an(): void
    {
        $this->postJson('/api/v1/product-widgets', [
            'technical_name' => 'card',
            'name_de' => 'Produktkarte',
            'config' => ['fields' => [
                ['role' => 'title', 'source' => 'product:name', 'type' => 'text'],
                ['role' => 'price', 'source' => 'prices:list_price', 'type' => 'price'],
            ]],
        ])->assertCreated()->assertJsonPath('data.technical_name', 'card');
    }

    public function test_config_ohne_felder_ist_invalide(): void
    {
        $this->postJson('/api/v1/product-widgets', [
            'technical_name' => 'leer',
            'name_de' => 'Leer',
            'config' => ['fields' => []],
        ])->assertStatus(422)->assertJsonValidationErrors(['config.fields']);
    }

    public function test_system_widget_nicht_loeschbar(): void
    {
        $w = ProductWidget::create([
            'technical_name' => 'card',
            'name_de' => 'Card',
            'config' => ['fields' => [['role' => 'title', 'source' => 'product:name', 'type' => 'text']]],
            'is_system' => true,
        ]);

        $this->deleteJson("/api/v1/product-widgets/{$w->id}")->assertStatus(422);
        $this->assertDatabaseHas('product_widgets', ['id' => $w->id]);
    }

    public function test_modul_gating_blockt_ohne_lizenz(): void
    {
        $this->mock(LicenseService::class, function ($mock) {
            $mock->shouldReceive('isModuleActive')->andReturn(false);
        });

        $this->getJson('/api/v1/product-widgets')->assertStatus(403);
    }
}
