<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Picker-Endpunkte für den Messenger-Empfänger-Dialog -- bewusst OHNE das
 * 'users.view'-Permission-Gate von UserController/TeamController, da jeder Nutzer
 * Kollegen anschreiben können muss. Liefert daher nur minimale Felder (id, name).
 */
class MessengerController extends Controller
{
    public function colleagues(Request $request): JsonResponse
    {
        $colleagues = User::where('is_active', true)
            ->where('id', '!=', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $colleagues]);
    }

    public function teams(): JsonResponse
    {
        $teams = Team::orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $teams]);
    }
}
