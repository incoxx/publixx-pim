<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\Role;
use Tests\TestCase;

class WorkflowControllerTest extends TestCase
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

    public function test_index_returns_workflows(): void
    {
        Workflow::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/workflows');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_creates_workflow_with_statuses_and_transitions(): void
    {
        $statusA = WorkflowStatus::factory()->create();
        $statusB = WorkflowStatus::factory()->create();

        $response = $this->postJson('/api/v1/workflows', [
            'name' => 'Review Workflow',
            'description' => 'Product review flow',
            'start_status_id' => $statusA->id,
            'end_status_id' => $statusB->id,
            'is_active' => true,
            'status_ids' => [$statusA->id, $statusB->id],
            'transitions' => [
                [
                    'from_status_id' => $statusA->id,
                    'to_status_id' => $statusB->id,
                    'name' => 'Approve',
                ],
            ],
            'product_type_ids' => [],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Review Workflow');

        $workflow = Workflow::where('name', 'Review Workflow')->first();
        $this->assertNotNull($workflow);
        $this->assertCount(2, $workflow->statuses);
        $this->assertCount(1, $workflow->transitions);
    }

    public function test_show_returns_workflow_with_relations(): void
    {
        $workflow = Workflow::factory()->create();

        $response = $this->getJson("/api/v1/workflows/{$workflow->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $workflow->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'statuses', 'transitions']]);
    }

    public function test_update_modifies_workflow(): void
    {
        $workflow = Workflow::factory()->create(['name' => 'Old']);

        $response = $this->putJson("/api/v1/workflows/{$workflow->id}", [
            'name' => 'Updated Workflow',
        ]);

        $response->assertOk();
        $this->assertEquals('Updated Workflow', $workflow->fresh()->name);
    }

    public function test_destroy_deletes_workflow(): void
    {
        $workflow = Workflow::factory()->create();

        $response = $this->deleteJson("/api/v1/workflows/{$workflow->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->postJson('/api/v1/workflows', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_dependencies_returns_info(): void
    {
        $workflow = Workflow::factory()->create();

        $response = $this->getJson("/api/v1/workflows/{$workflow->id}/dependencies");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['has_dependencies', 'total_count', 'dependencies']]);
    }
}
