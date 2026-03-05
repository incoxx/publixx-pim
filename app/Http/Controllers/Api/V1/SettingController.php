<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const CATALOG_THEME_DEFAULTS = [
        'font_family' => 'Inter',
        'font_heading_size' => '1.75rem',
        'font_body_size' => '0.875rem',
        'color_primary' => '#1B3A5C',
        'color_accent' => '#0D9488',
        'color_table_bg' => '#f8fafc',
        'color_body_text' => '#111827',
        'logo_media_id' => null,
        'catalog_title' => 'Produktkatalog',
        'impressum_url' => null,
        'kontakt_url' => null,
        'impressum_text' => null,
        'kontakt_text' => null,
        'footer_text' => null,
    ];

    /**
     * GET /api/v1/catalog/settings (public)
     */
    public function catalogTheme(): JsonResponse
    {
        $payload = Setting::getPayload('catalog_theme') ?? [];
        $merged = array_merge(self::CATALOG_THEME_DEFAULTS, $payload);

        // Resolve logo URL from media ID
        $merged['logo_url'] = null;
        if (!empty($merged['logo_media_id'])) {
            $media = Media::find($merged['logo_media_id']);
            if ($media) {
                $merged['logo_url'] = url('api/v1/catalog/media/' . rawurlencode($media->file_name));
            }
        }

        return response()->json(['data' => $merged]);
    }

    /**
     * PUT /api/v1/settings/catalog-theme (authenticated, admin)
     */
    public function updateCatalogTheme(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'font_family' => 'nullable|string|max:100',
            'font_heading_size' => 'nullable|string|in:1.25rem,1.5rem,1.75rem,2rem,2.25rem',
            'font_body_size' => 'nullable|string|in:0.8125rem,0.875rem,1rem',
            'color_primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_table_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_body_text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_media_id' => 'nullable|uuid|exists:media,id',
            'catalog_title' => 'nullable|string|max:200',
            'impressum_url' => 'nullable|url|max:500',
            'kontakt_url' => 'nullable|url|max:500',
            'impressum_text' => 'nullable|string|max:5000',
            'kontakt_text' => 'nullable|string|max:5000',
            'footer_text' => 'nullable|string|max:500',
        ]);

        // Only store non-null values (keeps payload compact)
        $payload = array_filter($validated, fn ($v) => $v !== null);

        Setting::setPayload('catalog_theme', $payload);

        return response()->json(['message' => 'Catalog theme updated.']);
    }
}
