<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\MessengerConversation;
use App\Models\Team;
use App\Models\User;
use App\Services\Messenger\MessengerMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessengerConversationController extends Controller
{
    /**
     * GET /api/v1/messenger/conversations -- eigene Konversationen, neueste zuerst.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $conversations = MessengerConversation::forUser($userId)
            ->with(['userOne:id,name', 'userTwo:id,name', 'latestMessage'])
            ->withCount(['messages as unread_count' => function ($query) use ($userId) {
                $query->where('sender_id', '!=', $userId)->whereNull('read_at');
            }])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (MessengerConversation $conversation) => $this->formatConversation($conversation, $userId));

        return response()->json(['data' => $conversations]);
    }

    /**
     * POST /api/v1/messenger/conversations -- neue Nachricht an 1..n Empfänger, Team oder
     * alle aktiven Nutzer. Erzeugt/nutzt je Empfänger eine eigene 1:1-Konversation
     * (Fan-out) statt eines gemeinsamen Gruppen-Threads.
     */
    public function store(Request $request, MessengerMessageService $service): JsonResponse
    {
        $validated = $request->validate([
            'recipients' => 'required|array',
            'recipients.mode' => 'required|string|in:users,team,all',
            'recipients.user_ids' => 'required_if:recipients.mode,users|array',
            'recipients.user_ids.*' => 'uuid|exists:users,id',
            'recipients.team_id' => 'required_if:recipients.mode,team|uuid|exists:teams,id',
            'body' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array',
            'attachments.*.type' => 'required_with:attachments|in:product,merkliste',
            'attachments.*.product_id' => 'required_if:attachments.*.type,product|uuid|exists:products,id',
        ]);

        if (empty($validated['body']) && empty($validated['attachments'])) {
            abort(422, 'Nachricht benötigt Text oder Anhang.');
        }

        $senderId = $request->user()->id;
        $targetUserIds = $this->resolveRecipientIds($validated['recipients'], $senderId);

        if (empty($targetUserIds)) {
            abort(422, 'Keine gültigen Empfänger gefunden.');
        }

        $results = [];
        foreach ($targetUserIds as $targetUserId) {
            $conversation = MessengerConversation::betweenUsers($senderId, $targetUserId);
            $message = $service->send(
                $conversation,
                $senderId,
                $validated['body'] ?? null,
                null,
                $validated['attachments'] ?? []
            );

            $results[] = [
                'conversation_id' => $conversation->id,
                'message' => $service->format($message),
            ];
        }

        return response()->json(['data' => $results], 201);
    }

    /**
     * GET /api/v1/messenger/conversations/{conversation} -- Konversation + paginierte Nachrichten.
     */
    public function show(Request $request, MessengerConversation $conversation, MessengerMessageService $service): JsonResponse
    {
        $userId = $request->user()->id;
        $this->authorizeParticipant($conversation, $userId);

        $messages = $conversation->messages()
            ->with(['sender:id,name', 'attachments.attachable', 'replyTo:id,body,sender_id'])
            ->orderBy('created_at')
            ->paginate($this->getPerPage($request));

        $conversation->load(['userOne:id,name', 'userTwo:id,name']);

        return response()->json([
            'data' => [
                'id' => $conversation->id,
                'other_user' => $this->otherUser($conversation, $userId),
                'status' => $conversation->status,
                'is_own' => $conversation->created_by === $userId,
            ],
            'messages' => $messages->through(fn ($message) => $service->format($message)),
        ]);
    }

    /**
     * POST /api/v1/messenger/conversations/{conversation}/read -- markiert alle
     * ungelesenen Nachrichten der Gegenseite als gelesen.
     */
    public function markRead(Request $request, MessengerConversation $conversation): JsonResponse
    {
        $userId = $request->user()->id;
        $this->authorizeParticipant($conversation, $userId);

        $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * DELETE /api/v1/messenger/conversations/{conversation} -- löscht den gesamten Chat
     * (Nachrichten/Anhänge cascaden). Nur der Verfasser (created_by) darf das.
     */
    public function destroy(Request $request, MessengerConversation $conversation): JsonResponse
    {
        if ($conversation->created_by !== $request->user()->id) {
            abort(403, 'Nur der Verfasser darf diesen Chat löschen.');
        }

        $conversation->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/messenger/conversations/{conversation}/resolve -- Chat als erledigt
     * quittieren. Jeder Teilnehmer darf das (nicht nur der Verfasser).
     */
    public function resolve(Request $request, MessengerConversation $conversation): JsonResponse
    {
        $userId = $request->user()->id;
        $this->authorizeParticipant($conversation, $userId);

        if ($conversation->status !== 'done') {
            $conversation->update([
                'status' => 'done',
                'resolved_at' => now(),
                'resolved_by' => $userId,
            ]);
        }

        return response()->json(['data' => ['status' => $conversation->status]]);
    }

    /**
     * POST /api/v1/messenger/conversations/{conversation}/reopen
     */
    public function reopen(Request $request, MessengerConversation $conversation): JsonResponse
    {
        $userId = $request->user()->id;
        $this->authorizeParticipant($conversation, $userId);

        $conversation->update([
            'status' => 'open',
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        return response()->json(['data' => ['status' => $conversation->status]]);
    }

    private function resolveRecipientIds(array $recipients, string $senderId): array
    {
        $ids = match ($recipients['mode']) {
            'users' => $recipients['user_ids'] ?? [],
            'team' => Team::findOrFail($recipients['team_id'])->users()->where('is_active', true)->pluck('users.id')->all(),
            'all' => User::where('is_active', true)->pluck('id')->all(),
        };

        return collect($ids)
            ->unique()
            ->reject(fn ($id) => $id === $senderId)
            ->values()
            ->all();
    }

    private function formatConversation(MessengerConversation $conversation, string $userId): array
    {
        return [
            'id' => $conversation->id,
            'other_user' => $this->otherUser($conversation, $userId),
            'last_message' => $conversation->latestMessage ? [
                'body' => $conversation->latestMessage->body,
                'sender_id' => $conversation->latestMessage->sender_id,
                'created_at' => $conversation->latestMessage->created_at?->toIso8601String(),
            ] : null,
            'unread_count' => $conversation->unread_count,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'status' => $conversation->status,
            'is_own' => $conversation->created_by === $userId,
        ];
    }

    private function otherUser(MessengerConversation $conversation, string $userId): ?array
    {
        $other = $conversation->user_one_id === $userId ? $conversation->userTwo : $conversation->userOne;

        return $other ? ['id' => $other->id, 'name' => $other->name] : null;
    }

    private function authorizeParticipant(MessengerConversation $conversation, string $userId): void
    {
        if ($conversation->user_one_id !== $userId && $conversation->user_two_id !== $userId) {
            abort(403);
        }
    }
}
