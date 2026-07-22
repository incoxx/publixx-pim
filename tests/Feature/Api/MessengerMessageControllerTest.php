<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\MessengerConversation;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessengerMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $sender;
    protected User $colleague;
    protected MessengerConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sender = User::factory()->create();
        $this->colleague = User::factory()->create();
        $this->conversation = MessengerConversation::betweenUsers($this->sender->id, $this->colleague->id);
        $this->actingAs($this->sender);
    }

    public function test_store_adds_message_to_existing_conversation(): void
    {
        $response = $this->postJson("/api/v1/messenger/conversations/{$this->conversation->id}/messages", [
            'body' => 'Wie ist der Stand?',
        ]);

        $response->assertCreated()->assertJsonPath('data.body', 'Wie ist der Stand?');
    }

    public function test_store_supports_reply_to_message(): void
    {
        $first = $this->postJson("/api/v1/messenger/conversations/{$this->conversation->id}/messages", [
            'body' => 'Frage',
        ])->json('data');

        $response = $this->postJson("/api/v1/messenger/conversations/{$this->conversation->id}/messages", [
            'body' => 'Antwort',
            'reply_to_message_id' => $first['id'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.reply_to.id', $first['id'])
            ->assertJsonPath('data.reply_to.body', 'Frage');
    }

    public function test_store_attaches_single_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson("/api/v1/messenger/conversations/{$this->conversation->id}/messages", [
            'body' => 'Schau dir das an',
            'attachments' => [
                ['type' => 'product', 'product_id' => $product->id],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.attachments.0.attached_via', 'product')
            ->assertJsonPath('data.attachments.0.product.id', $product->id);
    }

    public function test_store_expands_merkliste_attachment_into_watchlist_products(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        WatchlistItem::create(['user_id' => $this->sender->id, 'product_id' => $productA->id]);
        WatchlistItem::create(['user_id' => $this->sender->id, 'product_id' => $productB->id]);

        $response = $this->postJson("/api/v1/messenger/conversations/{$this->conversation->id}/messages", [
            'attachments' => [
                ['type' => 'merkliste'],
            ],
        ]);

        $response->assertCreated();
        $this->assertCount(2, $response->json('data.attachments'));
        $this->assertSame('merkliste', $response->json('data.attachments.0.attached_via'));
    }

    public function test_store_is_forbidden_for_non_participant(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger);

        $this->postJson("/api/v1/messenger/conversations/{$this->conversation->id}/messages", [
            'body' => 'Darf ich mitreden?',
        ])->assertForbidden();
    }

    public function test_unread_count_reflects_messages_from_other_participant(): void
    {
        $this->postJson("/api/v1/messenger/conversations/{$this->conversation->id}/messages", [
            'body' => 'Hallo',
        ])->assertCreated();

        $this->actingAs($this->colleague);

        $response = $this->getJson('/api/v1/messenger/unread-count');

        $response->assertOk()->assertJsonPath('data.count', 1);
    }
}
