<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notes = Note::forUser($request->user()->id)
            ->with('product:id,name,sku')
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
            'title' => 'required|string|max:255',
            'body' => 'nullable|string|max:5000',
            'color' => 'nullable|string|in:yellow,blue,green,pink,purple,orange',
            'pinned' => 'nullable|boolean',
            'product_id' => 'nullable|uuid|exists:products,id',
        ]);

        $note = Note::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $note->load('product:id,name,sku');

        return response()->json(['data' => $note], 201);
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        if ($note->created_by !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'body' => 'nullable|string|max:5000',
            'color' => 'nullable|string|in:yellow,blue,green,pink,purple,orange',
            'pinned' => 'nullable|boolean',
            'product_id' => 'nullable|uuid|exists:products,id',
            'sort_order' => 'nullable|integer',
        ]);

        $note->update($validated);
        $note->load('product:id,name,sku');

        return response()->json(['data' => $note]);
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        if ($note->created_by !== $request->user()->id) {
            abort(403);
        }

        $note->delete();

        return response()->json(null, 204);
    }

    /**
     * Notizen für ein bestimmtes Produkt (alle Nutzer sichtbar).
     */
    public function forProduct(Request $request, string $productId): JsonResponse
    {
        $notes = Note::where('product_id', $productId)
            ->with('creator:id,name')
            ->orderByDesc('pinned')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        return response()->json(['data' => $notes]);
    }

    /**
     * Sortierung mehrerer Notizen aktualisieren.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|uuid|exists:notes,id',
            'order.*.sort_order' => 'required|integer',
        ]);

        foreach ($validated['order'] as $item) {
            Note::where('id', $item['id'])
                ->where('created_by', $request->user()->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['status' => 'ok']);
    }
}
