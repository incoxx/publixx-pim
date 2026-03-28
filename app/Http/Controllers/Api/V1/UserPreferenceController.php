<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    public function show(Request $request, string $group): JsonResponse
    {
        $payload = UserPreference::getPayload($request->user()->id, $group);

        return response()->json(['data' => $payload]);
    }

    public function update(Request $request, string $group): JsonResponse
    {
        $request->validate([
            'preset' => 'sometimes|string|in:light,dark-navy,dark-charcoal,custom',
            'sidebar_bg' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sidebar_text' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sidebar_icon' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sidebar_active_bg' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sidebar_active_text' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sidebar_font_size' => 'sometimes|nullable|integer|min:11|max:18',
            'sidebar_colored_icons' => 'sometimes|boolean',
            'toolbar_bg' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'toolbar_text' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'toolbar_font_size' => 'sometimes|nullable|integer|min:11|max:18',
        ]);

        $pref = UserPreference::setPayload(
            $request->user()->id,
            $group,
            $request->only([
                'preset',
                'sidebar_bg', 'sidebar_text', 'sidebar_icon',
                'sidebar_active_bg', 'sidebar_active_text', 'sidebar_font_size',
                'sidebar_colored_icons',
                'toolbar_bg', 'toolbar_text', 'toolbar_font_size',
            ]),
        );

        return response()->json(['data' => $pref->payload]);
    }
}
