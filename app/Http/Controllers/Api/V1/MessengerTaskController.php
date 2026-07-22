<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\MessengerConversation;
use App\Models\MessengerTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Eigenständige, leichte Messenger-Aufgabe -- bewusst NICHT an WorkflowTask angedockt
 * (kein Produkt-Zwang, taucht nicht im Workflow-Board auf), siehe Plan-Entscheidung 1.
 */
class MessengerTaskController extends Controller
{
    private const WITH = ['assignee:id,name', 'creator:id,name', 'product:id,sku,name'];

    /**
     * GET /api/v1/messenger/tasks -- eigene (zugewiesene oder erstellte) Aufgaben.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $tasks = MessengerTask::where('assigned_to', $userId)
            ->orWhere('created_by', $userId)
            ->with(self::WITH)
            ->orderByRaw("status = 'done'")
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $tasks]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'due_at' => 'nullable|date',
            'assigned_to' => 'required|uuid|exists:users,id',
            'conversation_id' => 'nullable|uuid|exists:messenger_conversations,id',
            'message_id' => 'nullable|uuid|exists:messenger_messages,id',
            'product_id' => 'nullable|uuid|exists:products,id',
        ]);

        if (!empty($validated['conversation_id'])) {
            $conversation = MessengerConversation::findOrFail($validated['conversation_id']);
            $this->authorizeParticipant($conversation, $request->user()->id);
        }

        $task = MessengerTask::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status' => 'open',
        ]);

        return response()->json(['data' => $task->load(self::WITH)], 201);
    }

    public function show(Request $request, MessengerTask $task): JsonResponse
    {
        $this->authorizeAssigneeOrCreator($task, $request->user()->id);

        return response()->json(['data' => $task->load(self::WITH)]);
    }

    public function update(Request $request, MessengerTask $task): JsonResponse
    {
        $this->authorizeAssigneeOrCreator($task, $request->user()->id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'due_at' => 'nullable|date',
            'status' => 'sometimes|in:open,in_progress,done',
        ]);

        if (isset($validated['status'])) {
            $validated['closed_at'] = $validated['status'] === 'done' ? now() : null;
        }

        $task->update($validated);

        return response()->json(['data' => $task->load(self::WITH)]);
    }

    public function destroy(Request $request, MessengerTask $task): JsonResponse
    {
        if ($task->created_by !== $request->user()->id) {
            abort(403);
        }

        $task->delete();

        return response()->json(null, 204);
    }

    private function authorizeAssigneeOrCreator(MessengerTask $task, string $userId): void
    {
        if ($task->assigned_to !== $userId && $task->created_by !== $userId) {
            abort(403);
        }
    }

    private function authorizeParticipant(MessengerConversation $conversation, string $userId): void
    {
        if ($conversation->user_one_id !== $userId && $conversation->user_two_id !== $userId) {
            abort(403);
        }
    }
}
