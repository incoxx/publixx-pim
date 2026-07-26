<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CollectionShareLink;
use App\Services\Collections\CollectionRenderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * Oeffentliche, passwortgeschuetzte Browser-Ansicht einer Collection (Angebot) -- kein
 * Sanctum-Auth, laeuft ueber routes/web.php mit dem Standard-'web'-Middleware (Session + CSRF).
 * "Entsperrt"-Zustand wird in der Session gehalten (kein signiertes Cookie/JWT noetig, da diese
 * Seite ohnehin nur ueber eine normale Browser-Sitzung aufgerufen wird) -- Passwort-Pruefung
 * einmal pro Sitzung, danach direkter Dokumentzugriff bis Session/Ablauf.
 */
class CollectionShareLinkPublicController extends Controller
{
    public function show(Request $request, string $token, CollectionRenderService $renderService): Response
    {
        $link = CollectionShareLink::where('token', $token)->firstOrFail();

        if ($link->isExpired()) {
            return response()->view('collections.shared.password-gate', ['expired' => true], 410);
        }

        // Passwortlose Links: Der Token allein ist der Zugang — einmalig als Aufruf zählen.
        if ($link->password_hash === null && $request->session()->get("share_unlocked_{$token}") !== true) {
            $request->session()->put("share_unlocked_{$token}", true);
            $link->increment('view_count');
            $link->forceFill(['last_viewed_at' => now()])->save();
        }

        if ($request->session()->get("share_unlocked_{$token}") === true) {
            $html = $renderService->renderHtml($link->collection);

            return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return response()->view('collections.shared.password-gate', [
            'expired' => false,
            'collectionName' => $link->collection->name,
            'error' => $request->session()->pull('share_error', false),
        ]);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $link = CollectionShareLink::where('token', $token)->firstOrFail();

        // Passwortlose Links haben keine Eingabemaske; ein POST wäre hier gegenstandslos.
        if ($link->password_hash === null) {
            return redirect("/shared/collections/{$token}");
        }

        $request->validate(['password' => 'required|string']);

        if ($link->isExpired() || !Hash::check($request->input('password'), $link->password_hash)) {
            $request->session()->flash('share_error', true);

            return redirect("/shared/collections/{$token}");
        }

        $request->session()->put("share_unlocked_{$token}", true);
        $link->increment('view_count');
        $link->update(['last_viewed_at' => now()]);

        return redirect("/shared/collections/{$token}");
    }
}
