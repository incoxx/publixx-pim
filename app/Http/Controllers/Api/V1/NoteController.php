<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Note;
use App\Models\NoteComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    /**
     * Eigene + geteilte Notizen (Dashboard).
     */
    public function index(Request $request): JsonResponse
    {
        $notes = Note::visibleTo($request->user()->id)
            ->with(['product:id,name,sku', 'creator:id,name', 'resolver:id,name', 'comments.user:id,name'])
            ->orderByDesc('pinned')
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $notes]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'body'       => 'nullable|string|max:5000',
            'type'       => 'nullable|string|in:task,hint,warning,error',
            'color'      => 'nullable|string|in:yellow,blue,green,pink,purple,orange',
            'pinned'     => 'nullable|boolean',
            'is_shared'  => 'nullable|boolean',
            'product_id' => 'nullable|uuid|exists:products,id',
        ]);

        $note = Note::create([
            ...$validated,
            'type'       => $validated['type'] ?? 'task',
            'status'     => 'open',
            'created_by' => $request->user()->id,
        ]);

        $note->load(['product:id,name,sku', 'creator:id,name', 'comments.user:id,name']);

        return response()->json(['data' => $note], 201);
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        if ($note->created_by !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title'      => 'sometimes|string|max:255',
            'body'       => 'nullable|string|max:5000',
            'color'      => 'nullable|string|in:yellow,blue,green,pink,purple,orange',
            'pinned'     => 'nullable|boolean',
            'is_shared'  => 'nullable|boolean',
            'product_id' => 'nullable|uuid|exists:products,id',
            'sort_order' => 'nullable|integer',
        ]);

        $note->update($validated);
        $note->load(['product:id,name,sku', 'creator:id,name', 'resolver:id,name', 'comments.user:id,name']);

        return response()->json(['data' => $note]);
    }

    /**
     * Notiz abschließen — jeder mit Zugriff darf dies tun.
     */
    public function resolve(Request $request, Note $note): JsonResponse
    {
        $this->authorizeVisibility($note, $request->user()->id);

        if ($note->status === 'done') {
            return response()->json(['data' => $note->load(['creator:id,name', 'resolver:id,name', 'comments.user:id,name'])]);
        }

        $note->update([
            'status'      => 'done',
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        $note->load(['creator:id,name', 'resolver:id,name', 'comments.user:id,name']);

        return response()->json(['data' => $note]);
    }

    /**
     * Erledigte Notiz reaktivieren.
     */
    public function reopen(Request $request, Note $note): JsonResponse
    {
        $this->authorizeVisibility($note, $request->user()->id);

        $note->update([
            'status'      => 'open',
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        $note->load(['creator:id,name', 'resolver:id,name', 'comments.user:id,name']);

        return response()->json(['data' => $note]);
    }

    /**
     * Nur erledigte Notizen können in den Papierkorb.
     */
    public function destroy(Request $request, Note $note): JsonResponse
    {
        if ($note->created_by !== $request->user()->id) {
            abort(403);
        }

        if ($note->status !== 'done') {
            abort(422, 'Nur erledigte Notizen können gelöscht werden.');
        }

        $note->delete();

        return response()->json(null, 204);
    }

    /**
     * Kommentar zu einer Notiz hinzufügen.
     */
    public function addComment(Request $request, Note $note): JsonResponse
    {
        $this->authorizeVisibility($note, $request->user()->id);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $comment = NoteComment::create([
            'note_id' => $note->id,
            'user_id' => $request->user()->id,
            'body'    => $validated['body'],
        ]);

        $comment->load('user:id,name');

        return response()->json(['data' => $comment], 201);
    }

    /**
     * Notizen für ein bestimmtes Produkt (eigene + geteilte).
     */
    public function forProduct(Request $request, string $productId): JsonResponse
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $productId)) {
            abort(404);
        }

        $query = Note::where('product_id', $productId)
            ->visibleTo($request->user()->id)
            ->with(['creator:id,name', 'resolver:id,name', 'comments.user:id,name'])
            ->orderByDesc('pinned')
            ->orderByDesc('updated_at');

        if (!$request->boolean('show_done', false)) {
            $query->where('status', 'open');
        }

        $notes = $query->limit(50)->get();

        $openCounts = Note::where('product_id', $productId)
            ->visibleTo($request->user()->id)
            ->where('status', 'open')
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return response()->json([
            'data'        => $notes,
            'open_counts' => $openCounts,
        ]);
    }

    /**
     * Sortierung mehrerer Notizen aktualisieren.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order'              => 'required|array',
            'order.*.id'         => 'required|uuid|exists:notes,id',
            'order.*.sort_order' => 'required|integer',
        ]);

        foreach ($validated['order'] as $item) {
            Note::where('id', $item['id'])
                ->where('created_by', $request->user()->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Schneller Endpunkt für Header-Badges: nur Zähler offener Notizen.
     */
    public function openCounts(Request $request, string $productId): JsonResponse
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $productId)) {
            abort(404);
        }

        $counts = Note::where('product_id', $productId)
            ->visibleTo($request->user()->id)
            ->where('status', 'open')
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return response()->json(['data' => $counts]);
    }

    private function authorizeVisibility(Note $note, string $userId): void
    {
        if ($note->created_by !== $userId && !$note->is_shared) {
            abort(403);
        }
    }
}
