<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->user = User::factory()->create();
        $role = Role::findOrCreate('Admin', 'sanctum');
        $this->user->assignRole($role);
        $this->actingAs($this->user);
    }

    public function test_index_gibt_paginierte_einheitengruppen_zurueck(): void
    {
        UnitGroup::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/unit-groups');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_store_legt_einheitengruppe_an(): void
    {
        $response = $this->postJson('/api/v1/unit-groups', [
            'technical_name' => 'laenge',
            'name_de'        => 'Länge',
            'name_en'        => 'Length',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'laenge');

        $this->assertDatabaseHas('unit_groups', ['technical_name' => 'laenge']);
    }

    public function test_store_validierung_schlaegt_bei_fehlendem_name_de_fehl(): void
    {
        $response = $this->postJson('/api/v1/unit-groups', [
            'technical_name' => 'ohne-name',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name_de']);
    }

    public function test_store_validierung_verhindert_doppelten_technical_name(): void
    {
        UnitGroup::factory()->create(['technical_name' => 'gewicht']);

        $response = $this->postJson('/api/v1/unit-groups', [
            'technical_name' => 'gewicht',
            'name_de'        => 'Gewicht nochmal',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name']);
    }

    public function test_show_gibt_einheitengruppe_zurueck(): void
    {
        $group = UnitGroup::factory()->create();

        $response = $this->getJson("/api/v1/unit-groups/{$group->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $group->id);
    }

    public function test_update_aendert_einheitengruppe(): void
    {
        $group = UnitGroup::factory()->create(['name_de' => 'Alt']);

        $response = $this->patchJson("/api/v1/unit-groups/{$group->id}", [
            'name_de' => 'Neu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_de', 'Neu');

        $this->assertDatabaseHas('unit_groups', ['id' => $group->id, 'name_de' => 'Neu']);
    }

    public function test_destroy_loescht_einheitengruppe_ohne_abhaengigkeiten(): void
    {
        $group = UnitGroup::factory()->create();

        $response = $this->deleteJson("/api/v1/unit-groups/{$group->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('unit_groups', ['id' => $group->id]);
    }

    public function test_dependencies_meldet_keine_abhaengigkeiten_wenn_gruppe_frei(): void
    {
        $group = UnitGroup::factory()->create();

        $response = $this->getJson("/api/v1/unit-groups/{$group->id}/dependencies");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['has_dependencies', 'total_count', 'dependencies']])
            ->assertJsonPath('data.has_dependencies', false);
    }

    public function test_dependencies_meldet_abhaengigkeiten_wenn_einheit_vorhanden(): void
    {
        $group = UnitGroup::factory()->create();
        Unit::factory()->create(['unit_group_id' => $group->id]);

        $response = $this->getJson("/api/v1/unit-groups/{$group->id}/dependencies");

        $response->assertOk()
            ->assertJsonPath('data.has_dependencies', true);
    }

    public function test_unauthentifizierter_request_wird_abgelehnt(): void
    {
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/v1/unit-groups');

        $response->assertStatus(401);
    }
}
