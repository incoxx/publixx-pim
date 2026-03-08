<?php

declare(strict_types=1);

namespace Tests\Feature\DeletionConstraints;

use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeletionConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    // ─── Model Trait: checkDeletionConstraints ──────────────────

    public function test_check_deletion_constraints_returns_empty_when_no_dependencies(): void
    {
        $group = UnitGroup::factory()->create();

        $result = $group->checkDeletionConstraints();

        $this->assertFalse($result['has_dependencies']);
        $this->assertEquals(0, $result['total_count']);
        $this->assertEmpty($result['dependencies']);
    }

    public function test_check_deletion_constraints_returns_counts_when_dependencies_exist(): void
    {
        $group = UnitGroup::factory()->create();
        Unit::factory()->count(3)->create(['unit_group_id' => $group->id]);

        $result = $group->checkDeletionConstraints();

        $this->assertTrue($result['has_dependencies']);
        $this->assertEquals(3, $result['total_count']);
        $this->assertArrayHasKey('units', $result['dependencies']);
        $this->assertEquals(3, $result['dependencies']['units']['count']);
        $this->assertEquals('Einheiten', $result['dependencies']['units']['label']);
    }

    // ─── Dependencies Endpoint ─────────────────────────────────

    public function test_dependencies_endpoint_returns_200_with_no_dependencies(): void
    {
        $group = UnitGroup::factory()->create();

        $response = $this->getJson("/api/v1/unit-groups/{$group->id}/dependencies");

        $response->assertOk()
            ->assertJsonPath('data.has_dependencies', false)
            ->assertJsonPath('data.total_count', 0)
            ->assertJsonPath('data.dependencies', []);
    }

    public function test_dependencies_endpoint_returns_200_with_dependencies(): void
    {
        $group = UnitGroup::factory()->create();
        Unit::factory()->count(2)->create(['unit_group_id' => $group->id]);

        $response = $this->getJson("/api/v1/unit-groups/{$group->id}/dependencies");

        $response->assertOk()
            ->assertJsonPath('data.has_dependencies', true)
            ->assertJsonPath('data.total_count', 2)
            ->assertJsonPath('data.dependencies.units.count', 2)
            ->assertJsonPath('data.dependencies.units.label', 'Einheiten');
    }

    // ─── Delete without dependencies ───────────────────────────

    public function test_delete_without_dependencies_returns_204(): void
    {
        $group = UnitGroup::factory()->create();

        $response = $this->deleteJson("/api/v1/unit-groups/{$group->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('unit_groups', ['id' => $group->id]);
    }

    // ─── Delete with dependencies (no force) ───────────────────

    public function test_delete_with_dependencies_without_force_returns_409(): void
    {
        $group = UnitGroup::factory()->create();
        Unit::factory()->count(2)->create(['unit_group_id' => $group->id]);

        $response = $this->deleteJson("/api/v1/unit-groups/{$group->id}");

        $response->assertStatus(409)
            ->assertJsonPath('type', 'deletion_constraint')
            ->assertJsonPath('status', 409)
            ->assertJsonStructure([
                'type',
                'title',
                'status',
                'detail',
                'dependencies' => [
                    'units' => ['label', 'count'],
                ],
            ]);

        // Ensure the group was NOT deleted
        $this->assertDatabaseHas('unit_groups', ['id' => $group->id]);
    }

    // ─── Delete with dependencies (force=true) ─────────────────

    public function test_delete_with_dependencies_and_force_returns_204(): void
    {
        $group = UnitGroup::factory()->create();
        Unit::factory()->count(2)->create(['unit_group_id' => $group->id]);

        $response = $this->deleteJson("/api/v1/unit-groups/{$group->id}?force=true");

        $response->assertNoContent();
        $this->assertDatabaseMissing('unit_groups', ['id' => $group->id]);
    }

    // ─── Unit dependencies endpoint ────────────────────────────

    public function test_unit_dependencies_endpoint(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->getJson("/api/v1/units/{$unit->id}/dependencies");

        $response->assertOk()
            ->assertJsonPath('data.has_dependencies', false);
    }

    // ─── Auth required ─────────────────────────────────────────

    public function test_dependencies_endpoint_requires_auth(): void
    {
        // Reset auth
        app('auth')->forgetGuards();

        $group = UnitGroup::factory()->create();

        $response = $this->getJson("/api/v1/unit-groups/{$group->id}/dependencies");

        $response->assertUnauthorized();
    }
}
