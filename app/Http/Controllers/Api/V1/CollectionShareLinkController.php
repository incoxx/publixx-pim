<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Collection;
use App\Models\CollectionShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Verwaltung passwortgeschuetzter Freigabe-Links (oeffentliche Browser-HTML-Ansicht einer
 * Collection). Das eigentliche Ausliefern des Dokuments laeuft ueber CollectionShareLinkPublic-
 * Controller (routes/web.php, kein Sanctum-Auth) -- dieser Controller ist nur fuer die
 * authentifizierte Verwaltung (erstellen/auflisten/widerrufen).
 */
class CollectionShareLinkController extends Controller
{
    public function index(Collection $collection): JsonResponse
    {
        $this->authorize('view', $collection);

        $links = $collection->shareLinks()->latest()->get()->map(fn (CollectionShareLink $link) => $this->present($link));

        return response()->json(['data' => $links]);
    }

    public function store(Request $request, Collection $collection): JsonResponse
    {
        $this->authorize('update', $collection);

        $validated = $request->validate([
            'password' => 'required|string|min:4|max:100',
            // "after_or_equal:today" statt "after:now" -- ein vom Nutzer gewaehltes Datum meint
            // das ENDE dieses Tages (siehe endOfDay() unten), "heute" waehlen muss daher gueltig
            // bleiben statt an einem Mitternacht-vs-Mitternacht-Vergleich zu scheitern.
            'expires_at' => 'nullable|date|after_or_equal:today',
        ]);

        $link = $collection->shareLinks()->create([
            'token' => Str::random(48),
            'password_hash' => Hash::make($validated['password']),
            'expires_at' => isset($validated['expires_at']) ? Carbon::parse($validated['expires_at'])->endOfDay() : null,
            'created_by' => $request->user()?->id,
        ]);

        // Ohne refresh() bliebe view_count im Response null statt des DB-Defaults 0,
        // da das In-Memory-Modell nach create() den Spalten-Default nicht kennt.
        return response()->json(['data' => $this->present($link->refresh())], 201);
    }

    public function destroy(Collection $collection, CollectionShareLink $shareLink): JsonResponse
    {
        $this->authorize('update', $collection);
        abort_if($shareLink->collection_id !== $collection->id, 404);

        $shareLink->delete();

        return response()->json(null, 204);
    }

    /**
     * Liefert nie password_hash zurueck; die volle oeffentliche URL wird hier statt im
     * Frontend zusammengesetzt, damit die Web-Route (routes/web.php, nicht /api/v1) an
     * genau einer Stelle bekannt ist.
     */
    private function present(CollectionShareLink $link): array
    {
        return [
            'id' => $link->id,
            'token' => $link->token,
            'url' => url("/shared/collections/{$link->token}"),
            'expires_at' => $link->expires_at,
            'is_expired' => $link->isExpired(),
            'view_count' => $link->view_count,
            'last_viewed_at' => $link->last_viewed_at,
            'created_at' => $link->created_at,
        ];
    }
}
