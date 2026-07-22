<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Services\Messenger\MessengerMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessengerMessageController extends Controller
{
    /**
     * POST /api/v1/messenger/conversations/{conversation}/messages -- neue Nachricht
     * (optional als Antwort auf eine bestehende via reply_to_message_id) in einer
     * bereits vorhandenen 1:1-Konversation.
     */
    public function store(Request $request, MessengerConversation $conversation, MessengerMessageService $service): JsonResponse
    {
        $userId = $request->user()->id;
        $this->authorizeParticipant($conversation, $userId);

        $validated = $request->validate([
            'body' => 'nullable|string|max:5000',
            'reply_to_message_id' => 'nullable|uuid|exists:messenger_messages,id',
            'attachments' => 'nullable|array',
            'attachments.*.type' => 'required_with:attachments|in:product,merkliste',
            'attachments.*.product_id' => 'required_if:attachments.*.type,product|uuid|exists:products,id',
        ]);

        if (empty($validated['body']) && empty($validated['attachments'])) {
            abort(422, 'Nachricht benötigt Text oder Anhang.');
        }

        $message = $service->send(
            $conversation,
            $userId,
            $validated['body'] ?? null,
            $validated['reply_to_message_id'] ?? null,
            $validated['attachments'] ?? []
        );

        return response()->json(['data' => $service->format($message)], 201);
    }

    /**
     * GET /api/v1/messenger/unread-count -- leichter Polling-Endpunkt fürs Header-Badge.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $count = MessengerMessage::whereHas('conversation', fn ($query) => $query->forUser($userId))
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->count();

        return response()->json(['data' => ['count' => $count]]);
    }

    private function authorizeParticipant(MessengerConversation $conversation, string $userId): void
    {
        if ($conversation->user_one_id !== $userId && $conversation->user_two_id !== $userId) {
            abort(403);
        }
    }
}
