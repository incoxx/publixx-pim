<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\MessengerConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessengerTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $creator;
    protected User $assignee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::factory()->create();
        $this->assignee = User::factory()->create();
        $this->actingAs($this->creator);
    }

    public function test_store_creates_standalone_task_without_product(): void
    {
        $response = $this->postJson('/api/v1/messenger/tasks', [
            'title' => 'Bitte Preis prüfen',
            'assigned_to' => $this->assignee->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Bitte Preis prüfen')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.assignee.id', $this->assignee->id);
    }

    public function test_store_can_reference_originating_conversation(): void
    {
        $conversation = MessengerConversation::betweenUsers($this->creator->id, $this->assignee->id);

        $response = $this->postJson('/api/v1/messenger/tasks', [
            'title' => 'Aufgabe aus Chat',
            'assigned_to' => $this->assignee->id,
            'conversation_id' => $conversation->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('messenger_tasks', [
            'title' => 'Aufgabe aus Chat',
            'conversation_id' => $conversation->id,
        ]);
    }

    public function test_index_lists_tasks_assigned_to_current_user(): void
    {
        $this->postJson('/api/v1/messenger/tasks', [
            'title' => 'Für Kollege',
            'assigned_to' => $this->assignee->id,
        ])->assertCreated();

        $this->actingAs($this->assignee);

        $response = $this->getJson('/api/v1/messenger/tasks');

        $response->assertOk()->assertJsonPath('data.0.title', 'Für Kollege');
    }

    public function test_assignee_can_close_task(): void
    {
        $task = $this->postJson('/api/v1/messenger/tasks', [
            'title' => 'Erledigen',
            'assigned_to' => $this->assignee->id,
        ])->json('data');

        $this->actingAs($this->assignee);

        $response = $this->putJson("/api/v1/messenger/tasks/{$task['id']}", [
            'status' => 'done',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'done');
        $this->assertNotNull($response->json('data.closed_at'));
    }

    public function test_unrelated_user_cannot_view_task(): void
    {
        $task = $this->postJson('/api/v1/messenger/tasks', [
            'title' => 'Privat',
            'assigned_to' => $this->assignee->id,
        ])->json('data');

        $stranger = User::factory()->create();
        $this->actingAs($stranger);

        $this->getJson("/api/v1/messenger/tasks/{$task['id']}")->assertForbidden();
    }

    public function test_only_creator_can_delete_task(): void
    {
        $task = $this->postJson('/api/v1/messenger/tasks', [
            'title' => 'Löschen',
            'assigned_to' => $this->assignee->id,
        ])->json('data');

        $this->actingAs($this->assignee);
        $this->deleteJson("/api/v1/messenger/tasks/{$task['id']}")->assertForbidden();

        $this->actingAs($this->creator);
        $this->deleteJson("/api/v1/messenger/tasks/{$task['id']}")->assertNoContent();
    }
}
