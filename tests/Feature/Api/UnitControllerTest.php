<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitControllerTest extends TestCase
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

    public function test_index_gibt_einheiten_der_gruppe_zurueck(): void
    {
        $group = UnitGroup::factory()->create();
        Unit::factory()->count(2)->create(['unit_group_id' => $group->id]);

        // Einheit einer anderen Gruppe — darf nicht erscheinen
        Unit::factory()->create();

        $response = $this->getJson("/api/v1/unit-groups/{$group->id}/units");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_legt_einheit_in_gruppe_an(): void
    {
        $group = UnitGroup::factory()->create();

        $response = $this->postJson("/api/v1/unit-groups/{$group->id}/units", [
            'technical_name'  => 'meter',
            'abbreviation'    => 'm',
            'conversion_factor' => 1,
            'is_base_unit'    => true,
            'is_translatable' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'meter');

        $this->assertDatabaseHas('units', [
            'technical_name' => 'meter',
            'unit_group_id'  => $group->id,
        ]);
    }

    public function test_store_validierung_schlaegt_bei_fehlendem_abbreviation_fehl(): void
    {
        $group = UnitGroup::factory()->create();

        $response = $this->postJson("/api/v1/unit-groups/{$group->id}/units", [
            'technical_name'  => 'ohne-abbr',
            'is_base_unit'    => false,
            'is_translatable' => false,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['abbreviation']);
    }

    public function test_show_gibt_einzelne_einheit_zurueck(): void
    {
        $unit = Unit::factory()->create();

        // Shallow-Route: GET /units/{unit}
        $response = $this->getJson("/api/v1/units/{$unit->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $unit->id);
    }

    public function test_update_aendert_einheit(): void
    {
        $unit = Unit::factory()->create(['abbreviation' => 'old']);

        $response = $this->patchJson("/api/v1/units/{$unit->id}", [
            'abbreviation' => 'new',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.abbreviation', 'new');

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'abbreviation' => 'new']);
    }

    public function test_basiseinheit_wird_korrekt_gesetzt(): void
    {
        $group = UnitGroup::factory()->create();

        $response = $this->postJson("/api/v1/unit-groups/{$group->id}/units", [
            'technical_name'    => 'kilogramm',
            'abbreviation'      => 'kg',
            'conversion_factor' => 1,
            'is_base_unit'      => true,
            'is_translatable'   => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_base_unit', true);
    }

    public function test_conversion_factor_wird_gespeichert(): void
    {
        $group = UnitGroup::factory()->create();

        $this->postJson("/api/v1/unit-groups/{$group->id}/units", [
            'technical_name'    => 'gramm',
            'abbreviation'      => 'g',
            'conversion_factor' => 0.001,
            'is_base_unit'      => false,
            'is_translatable'   => false,
        ]);

        $this->assertDatabaseHas('units', [
            'technical_name'    => 'gramm',
            'conversion_factor' => 0.001,
        ]);
    }

    public function test_destroy_loescht_einheit_ohne_abhaengigkeiten(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->deleteJson("/api/v1/units/{$unit->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }

    public function test_dependencies_meldet_keine_abhaengigkeiten_wenn_einheit_frei(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->getJson("/api/v1/units/{$unit->id}/dependencies");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['has_dependencies', 'total_count', 'dependencies']])
            ->assertJsonPath('data.has_dependencies', false);
    }

    public function test_unauthentifizierter_request_wird_abgelehnt(): void
    {
        $group = UnitGroup::factory()->create();
        app('auth')->forgetGuards();

        $response = $this->getJson("/api/v1/unit-groups/{$group->id}/units");

        $response->assertStatus(401);
    }
}
