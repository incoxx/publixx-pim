<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\MessengerConversation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessengerConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $sender;
    protected User $colleague;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sender = User::factory()->create();
        $this->colleague = User::factory()->create();
        $this->actingAs($this->sender);
    }

    public function test_store_creates_conversation_and_message_for_single_recipient(): void
    {
        $response = $this->postJson('/api/v1/messenger/conversations', [
            'recipients' => ['mode' => 'users', 'user_ids' => [$this->colleague->id]],
            'body' => 'Hallo Kollege!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.0.message.body', 'Hallo Kollege!');

        $this->assertDatabaseHas('messenger_conversations', [
            'user_one_id' => min($this->sender->id, $this->colleague->id),
            'user_two_id' => max($this->sender->id, $this->colleague->id),
        ]);
        $this->assertDatabaseHas('messenger_messages', [
            'sender_id' => $this->sender->id,
            'body' => 'Hallo Kollege!',
        ]);
    }

    public function test_store_reuses_existing_conversation_between_same_two_users(): void
    {
        $this->postJson('/api/v1/messenger/conversations', [
            'recipients' => ['mode' => 'users', 'user_ids' => [$this->colleague->id]],
            'body' => 'Erste Nachricht',
        ])->assertCreated();

        $this->postJson('/api/v1/messenger/conversations', [
            'recipients' => ['mode' => 'users', 'user_ids' => [$this->colleague->id]],
            'body' => 'Zweite Nachricht',
        ])->assertCreated();

        $this->assertSame(1, MessengerConversation::count());
        $this->assertSame(2, \App\Models\MessengerMessage::count());
    }

    public function test_store_fans_out_to_all_active_users_as_separate_conversations(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $inactive = User::factory()->create(['is_active' => false]);

        $response = $this->postJson('/api/v1/messenger/conversations', [
            'recipients' => ['mode' => 'all'],
            'body' => 'An alle',
        ]);

        $response->assertCreated();
        // sender selbst wird ausgeschlossen, inaktive Nutzer ebenso.
        $this->assertSame(2, MessengerConversation::count());
        $this->assertDatabaseMissing('messenger_conversations', [
            'user_one_id' => $inactive->id,
        ]);
        $this->assertDatabaseMissing('messenger_conversations', [
            'user_two_id' => $inactive->id,
        ]);
    }

    public function test_store_fans_out_to_team_members(): void
    {
        $team = Team::create(['name' => 'Team A']);
        $member = User::factory()->create();
        $team->users()->attach($member->id);

        $response = $this->postJson('/api/v1/messenger/conversations', [
            'recipients' => ['mode' => 'team', 'team_id' => $team->id],
            'body' => 'An das Team',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('messenger_conversations', [
            'user_one_id' => min($this->sender->id, $member->id),
            'user_two_id' => max($this->sender->id, $member->id),
        ]);
    }

    public function test_store_requires_body_or_attachment(): void
    {
        $response = $this->postJson('/api/v1/messenger/conversations', [
            'recipients' => ['mode' => 'users', 'user_ids' => [$this->colleague->id]],
        ]);

        $response->assertUnprocessable();
    }

    public function test_index_returns_conversation_with_unread_count(): void
    {
        $this->postJson('/api/v1/messenger/conversations', [
            'recipients' => ['mode' => 'users', 'user_ids' => [$this->colleague->id]],
            'body' => 'Hallo',
        ])->assertCreated();

        $this->actingAs($this->colleague);

        $response = $this->getJson('/api/v1/messenger/conversations');

        $response->assertOk()
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.0.other_user.id', $this->sender->id);
    }

    public function test_show_is_forbidden_for_non_participant(): void
    {
        $conversation = MessengerConversation::betweenUsers($this->sender->id, $this->colleague->id);
        $stranger = User::factory()->create();
        $this->actingAs($stranger);

        $this->getJson("/api/v1/messenger/conversations/{$conversation->id}")->assertForbidden();
    }

    public function test_mark_read_clears_unread_count(): void
    {
        $this->postJson('/api/v1/messenger/conversations', [
            'recipients' => ['mode' => 'users', 'user_ids' => [$this->colleague->id]],
            'body' => 'Hallo',
        ])->assertCreated();

        $conversation = MessengerConversation::first();
        $this->actingAs($this->colleague);

        $this->postJson("/api/v1/messenger/conversations/{$conversation->id}/read")->assertOk();

        $this->assertDatabaseMissing('messenger_messages', [
            'conversation_id' => $conversation->id,
            'read_at' => null,
        ]);
    }
}
