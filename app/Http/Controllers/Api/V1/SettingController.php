<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\BulkReindexSearchJob;
use App\Jobs\UpdateSearchIndex;
use App\Models\Attribute;
use App\Models\AttributeView;
use App\Models\CatalogTemplate;
use App\Models\Hierarchy;
use App\Models\Media;
use App\Models\PdfTemplate;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductRelationType;
use App\Models\Setting;
use App\Models\User;
use App\Models\WebsiteProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

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
        'color_sidebar' => '#1B3A5C',
        'color_button' => '#0D9488',
        'color_table_stripe' => '#f1f5f9',
        'logo_media_id' => null,
        'catalog_title' => 'Produktkatalog',
        'seo_title' => null,
        'seo_description' => null,
        'impressum_url' => null,
        'kontakt_url' => null,
        'impressum_text' => null,
        'kontakt_text' => null,
        'footer_text' => null,
        'hierarchy_id' => null,
        'attribute_view_ids' => [],
        'default_locale' => 'de',
        'color_header_bg' => null,
        'color_header_text' => null,
        'color_mobile_menu_bg' => null,
        'color_mobile_menu_text' => null,
        'color_sidebar_active_bg' => null,
        'color_sidebar_active_text' => null,
        'color_card_top_bg' => null,
        'color_card_bottom_bg' => null,
        'color_grid_bg' => null,
        'color_popup_bg' => null,
        'color_facets_bg' => null,
        'color_facets_text' => null,
        'color_search_bg' => null,
        'color_search_text' => null,
        'popup_max_width' => '4xl',
        'facet_attribute_ids' => [],
        'catalog_tag_facet' => true,
        'detail_layout' => 'classic',
        'card_attribute_ids' => [],
        'card_show_sku' => false,
        'card_show_category' => true,
        'card_show_price' => true,
        'card_price_type_id' => null,
        'card_price_country' => null,
        'card_image_ratio' => '4/3',
        'description_attributes' => [],
        'pdf_display_mode' => 'link',
        'catalog_access_mode' => 'public',
        'catalog_linked_products_only' => false,
        'catalog_pdf_enabled' => false,
        'catalog_pdf_template_id' => null,
        'catalog_compare_enabled' => false,
        'catalog_compare_max_products' => 3,
        'catalog_excel_export_enabled' => false,
        'catalog_share_wishlist_enabled' => false,
        'catalog_relation_type_ids' => [],
        'catalog_category_expand_depth' => 1,
        'search_profile_id' => null,
        'thumbnail_usage_type_id' => null,
        'custom_css' => null,
    ];

    /**
     * GET /api/v1/catalog/settings (public)
     */
    public function catalogTheme(): JsonResponse
    {
        $payload = WebsiteProfile::getActivePayload();
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
        // Only admins may update catalog theme
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        // Strip stale attribute/relation IDs so validation doesn't reject
        // references to deleted entities (settings store IDs as JSON, no FK cascade)
        $existingAttrIds = Attribute::pluck('id')->toArray();
        $attrIdSet = array_flip($existingAttrIds);

        if ($request->has('card_attribute_ids')) {
            $request->merge([
                'card_attribute_ids' => array_values(array_filter(
                    $request->input('card_attribute_ids', []),
                    fn ($id) => isset($attrIdSet[$id])
                )),
            ]);
        }
        if ($request->has('facet_attribute_ids')) {
            $request->merge([
                'facet_attribute_ids' => array_values(array_filter(
                    $request->input('facet_attribute_ids', []),
                    fn ($id) => isset($attrIdSet[$id])
                )),
            ]);
        }
        if ($request->has('primary_card_attribute_id') && $request->input('primary_card_attribute_id') !== null) {
            if (!isset($attrIdSet[$request->input('primary_card_attribute_id')])) {
                $request->merge(['primary_card_attribute_id' => null]);
            }
        }
        if ($request->has('description_attributes')) {
            $request->merge([
                'description_attributes' => array_values(array_filter(
                    $request->input('description_attributes', []),
                    fn ($da) => isset($attrIdSet[$da['attribute_id'] ?? ''])
                )),
            ]);
        }
        if ($request->has('attribute_view_ids')) {
            $existingAvIds = array_flip(AttributeView::pluck('id')->toArray());
            $request->merge([
                'attribute_view_ids' => array_values(array_filter(
                    $request->input('attribute_view_ids', []),
                    fn ($id) => isset($existingAvIds[$id])
                )),
            ]);
        }
        if ($request->has('catalog_relation_type_ids')) {
            $existingRtIds = array_flip(ProductRelationType::pluck('id')->toArray());
            $request->merge([
                'catalog_relation_type_ids' => array_values(array_filter(
                    $request->input('catalog_relation_type_ids', []),
                    fn ($id) => isset($existingRtIds[$id])
                )),
            ]);
        }
        if ($request->has('logo_media_id') && $request->input('logo_media_id') !== null) {
            if (!Media::where('id', $request->input('logo_media_id'))->exists()) {
                $request->merge(['logo_media_id' => null]);
            }
        }
        if ($request->has('hierarchy_id') && $request->input('hierarchy_id') !== null) {
            if (!Hierarchy::where('id', $request->input('hierarchy_id'))->exists()) {
                $request->merge(['hierarchy_id' => null]);
            }
        }
        if ($request->has('card_price_type_id') && $request->input('card_price_type_id') !== null) {
            if (!PriceType::where('id', $request->input('card_price_type_id'))->exists()) {
                $request->merge(['card_price_type_id' => null]);
            }
        }
        if ($request->has('catalog_pdf_template_id') && $request->input('catalog_pdf_template_id') !== null) {
            if (!PdfTemplate::where('id', $request->input('catalog_pdf_template_id'))->exists()) {
                $request->merge(['catalog_pdf_template_id' => null]);
            }
        }

        $validated = $request->validate([
            'font_family' => 'nullable|string|max:100',
            'font_heading_size' => 'nullable|string|in:1.25rem,1.5rem,1.75rem,2rem,2.25rem',
            'font_body_size' => 'nullable|string|in:0.8125rem,0.875rem,1rem',
            'color_primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_table_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_body_text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_sidebar' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_button' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_table_stripe' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_media_id' => 'nullable|uuid|exists:media,id',
            'catalog_title' => 'nullable|string|max:200',
            'seo_title' => 'nullable|string|max:200',
            'seo_description' => 'nullable|string|max:500',
            'impressum_url' => 'nullable|url|max:500',
            'kontakt_url' => 'nullable|url|max:500',
            'impressum_text' => 'nullable|string|max:5000',
            'kontakt_text' => 'nullable|string|max:5000',
            'footer_text' => 'nullable|string|max:500',
            'hierarchy_id' => 'nullable|uuid|exists:hierarchies,id',
            'attribute_view_ids' => 'nullable|array',
            'attribute_view_ids.*' => 'uuid|exists:attribute_views,id',
            'default_locale' => 'nullable|string|in:de,en',
            'color_header_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_header_text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_mobile_menu_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_mobile_menu_text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_sidebar_active_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_sidebar_active_text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_card_top_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_card_bottom_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_grid_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_popup_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_facets_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_facets_text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_search_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_search_text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'popup_max_width' => 'nullable|string|in:4xl,5xl,6xl,7xl,full',
            'facet_attribute_ids' => 'nullable|array',
            'catalog_tag_facet' => 'nullable|boolean',
            'facet_attribute_ids.*' => 'uuid|exists:attributes,id',
            'detail_layout' => 'nullable|string|in:classic,tabs,hero',
            'card_attribute_ids' => 'nullable|array',
            'card_attribute_ids.*' => 'uuid|exists:attributes,id',
            'primary_card_attribute_id' => 'nullable|uuid|exists:attributes,id',
            'card_show_sku' => 'nullable|boolean',
            'card_show_category' => 'nullable|boolean',
            'card_show_price' => 'nullable|boolean',
            'card_price_type_id' => 'nullable|uuid|exists:price_types,id',
            'card_price_country' => 'nullable|string|max:2',
            'card_image_ratio' => 'nullable|string|in:4/3,1/1,3/4,16/9',
            'description_attributes' => 'nullable|array',
            'description_attributes.*.attribute_id' => 'required|uuid|exists:attributes,id',
            'description_attributes.*.typography' => 'required|string|in:xs,sm,base,lg,xl,2xl,3xl',
            'description_attributes.*.live_edit' => 'sometimes|boolean',
            'pdf_display_mode' => 'nullable|string|in:link,embedded',
            'catalog_access_mode' => 'nullable|string|in:public,login',
            'catalog_linked_products_only' => 'nullable|boolean',
            'catalog_pdf_enabled' => 'nullable|boolean',
            'catalog_pdf_template_id' => 'nullable|uuid|exists:pdf_templates,id',
            'catalog_compare_enabled' => 'nullable|boolean',
            'catalog_compare_max_products' => 'nullable|integer|in:2,3',
            'catalog_excel_export_enabled' => 'nullable|boolean',
            'catalog_share_wishlist_enabled' => 'nullable|boolean',
            'catalog_relation_type_ids' => 'nullable|array',
            'catalog_relation_type_ids.*' => 'uuid|exists:product_relation_types,id',
            'catalog_category_expand_depth' => 'nullable|integer|min:0|max:10',
            'search_profile_id' => 'nullable|uuid|exists:search_profiles,id',
            'thumbnail_usage_type_id' => 'nullable|uuid|exists:usage_types,id',
            'catalog_excluded_node_ids' => 'nullable|array',
            'catalog_excluded_node_ids.*' => 'uuid|exists:hierarchy_nodes,id',
            'custom_css' => 'nullable|string|max:50000',
        ]);

        // Merge with existing payload so that unsent keys are preserved
        $activeProfile = WebsiteProfile::where('is_active', true)->first();
        $existing = $activeProfile?->payload ?? [];
        $merged = array_merge($existing, $validated);

        if ($activeProfile) {
            $activeProfile->update(['payload' => $merged]);
        } else {
            WebsiteProfile::create([
                'name' => 'Standard',
                'is_shared' => true,
                'is_active' => true,
                'payload' => $merged,
            ]);
        }

        return response()->json(['message' => 'Catalog theme updated.']);
    }

    /**
     * GET /api/v1/settings/configured-plugins
     *
     * Returns which connector/provider types have API keys configured.
     * Available to all authenticated users (used by sidebar).
     */
    public function configuredPlugins(): JsonResponse
    {
        $payload = Setting::getPayload('connector_credentials') ?? [];

        $configured = [];
        foreach ($payload as $connector => $fields) {
            if (collect($fields)->some(fn ($v) => ! empty($v))) {
                $configured[] = $connector;
            }
        }

        // Also check .env-based config for connectors not stored in DB
        $envChecks = [
            'deepl' => config('connectors.deepl.api_key'),
            'shopware' => config('connectors.shopware.client_id'),
            'claude_ai' => config('connectors.claude_ai.api_key'),
            'openai' => config('connectors.openai.api_key'),
        ];

        foreach ($envChecks as $key => $value) {
            if (! empty($value) && ! in_array($key, $configured)) {
                $configured[] = $key;
            }
        }

        return response()->json(['data' => $configured]);
    }

    /**
     * GET /api/v1/settings/connector-credentials
     *
     * Gibt die gespeicherten Connector-Credentials zurück (Werte maskiert).
     */
    public function connectorCredentials(Request $request): JsonResponse
    {
        if (! $request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $payload = Setting::getPayload('connector_credentials') ?? [];

        // Maskiere sensible Werte für die Anzeige
        $masked = [];
        foreach ($payload as $connector => $fields) {
            $masked[$connector] = [];
            foreach ($fields as $key => $value) {
                $masked[$connector][$key] = $value
                    ? str_repeat('*', min(8, strlen($value))) . substr($value, -4)
                    : '';
            }
        }

        // Zeige welche Felder pro Connector/Provider konfigurierbar sind
        $schema = [
            // ── Connectoren ──
            'deepl'     => ['api_key'],
            'shopware'  => ['shop_url', 'client_id', 'client_secret'],
            'shopify'   => ['shop_url', 'access_token', 'client_id', 'client_secret'],
            'claude_ai' => ['api_key', 'model', 'max_tokens'],
            'openai'    => ['api_key', 'model'],
            // ── Übersetzungsdienste (TMS) ──
            'google_translate' => ['api_key'],
            'anthropic_tms'    => ['api_key', 'model'],
            'openai_tms'       => ['api_key', 'model'],
        ];

        return response()->json([
            'data' => [
                'values' => $masked,
                'schema' => $schema,
                'has_values' => array_map(
                    fn ($fields) => collect($fields)->some(fn ($v) => ! empty($v)),
                    $payload,
                ),
            ],
        ]);
    }

    /**
     * PUT /api/v1/settings/connector-credentials
     *
     * Speichert Connector-Credentials in der Datenbank.
     */
    public function updateConnectorCredentials(Request $request): JsonResponse
    {
        if (! $request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'deepl'              => 'sometimes|array',
            'deepl.api_key'      => 'nullable|string|max:500',
            'shopware'           => 'sometimes|array',
            'shopware.shop_url'  => 'nullable|string|max:500',
            'shopware.client_id' => 'nullable|string|max:500',
            'shopware.client_secret' => 'nullable|string|max:500',
            'claude_ai'             => 'sometimes|array',
            'claude_ai.api_key'     => 'nullable|string|max:500',
            'claude_ai.model'       => 'nullable|string|max:100',
            'claude_ai.max_tokens'  => 'nullable|integer|min:1|max:16384',
            'openai'                => 'sometimes|array',
            'openai.api_key'        => 'nullable|string|max:500',
            'openai.model'          => 'nullable|string|max:100',
            // Übersetzungsdienste (TMS)
            'google_translate'          => 'sometimes|array',
            'google_translate.api_key'  => 'nullable|string|max:500',
            'anthropic_tms'             => 'sometimes|array',
            'anthropic_tms.api_key'     => 'nullable|string|max:500',
            'anthropic_tms.model'       => 'nullable|string|max:100',
            'openai_tms'                => 'sometimes|array',
            'openai_tms.api_key'        => 'nullable|string|max:500',
            'openai_tms.model'          => 'nullable|string|max:100',
        ]);

        // Merge mit bestehenden Werten (leere Felder = nicht überschreiben)
        $existing = Setting::getPayload('connector_credentials') ?? [];

        foreach ($validated as $connector => $fields) {
            if (! is_array($fields)) {
                continue;
            }
            foreach ($fields as $key => $value) {
                // Leerer String oder Sternchen = nicht überschreiben
                if ($value === null || $value === '' || str_starts_with($value, '***')) {
                    continue;
                }
                $existing[$connector][$key] = $value;
            }
        }

        Setting::setPayload('connector_credentials', $existing);

        return response()->json(['message' => 'Connector-Credentials gespeichert.']);
    }

    /**
     * POST /api/v1/admin/search-reindex
     *
     * Trigger a full search index rebuild for all active products.
     */
    public function reindexSearch(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Check if already running
        $progress = Cache::get('search_reindex_progress');
        if ($progress && $progress['status'] === 'running') {
            return response()->json([
                'message' => 'Reindex läuft bereits.',
                'progress' => $progress,
            ], 409);
        }

        $total = Product::where('status', 'active')
            ->where('product_type_ref', 'product')
            ->count();

        // Initialize progress immediately
        Cache::put('search_reindex_progress', [
            'status' => 'running',
            'processed' => 0,
            'total' => $total,
            'percent' => 0,
            'error' => null,
            'updated_at' => now()->toIso8601String(),
        ], 600);

        BulkReindexSearchJob::dispatch();

        return response()->json([
            'message' => "Reindex gestartet für {$total} Produkte.",
            'count' => $total,
        ]);
    }

    /**
     * GET /api/v1/admin/search-reindex/progress
     */
    public function reindexProgress(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $progress = Cache::get('search_reindex_progress');

        return response()->json([
            'progress' => $progress ?? [
                'status' => 'idle',
                'processed' => 0,
                'total' => 0,
                'percent' => 0,
                'error' => null,
                'estimated_seconds' => null,
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/search-reindex/cancel
     */
    public function cancelReindex(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $progress = Cache::get('search_reindex_progress');
        if (!$progress || $progress['status'] !== 'running') {
            return response()->json(['message' => 'Kein laufender Reindex.'], 404);
        }

        Cache::put('search_reindex_cancel', true, 600);

        return response()->json(['message' => 'Abbruch angefordert. Der Job wird beim nächsten Chunk gestoppt.']);
    }

    // ── Firmen-CI: Erzwungenes Erscheinungsbild ──

    /**
     * GET /api/v1/settings/enforced-appearance (public, no auth)
     *
     * Liefert das erzwungene Firmen-Theme (oder null wenn nicht gesetzt).
     */
    public function enforcedAppearance(): JsonResponse
    {
        $payload = Setting::getPayload('enforced_appearance');

        return response()->json(['data' => $payload]);
    }

    /**
     * PUT /api/v1/settings/enforced-appearance (admin only)
     *
     * Setzt das Firmen-CI Theme fuer alle Benutzer.
     * Wenn preset=null, wird die Erzwingung aufgehoben.
     */
    public function updateEnforcedAppearance(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'preset' => 'nullable|string|in:light,dark-navy,dark-charcoal,custom',
            'sidebar_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_icon' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_active_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_active_text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_font_size' => 'nullable|integer|min:11|max:18',
            'sidebar_colored_icons' => 'nullable|boolean',
            'toolbar_bg' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'toolbar_text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'toolbar_font_size' => 'nullable|integer|min:11|max:18',
        ]);

        if (empty($validated['preset'])) {
            // Erzwingung aufheben
            Setting::where('group', 'enforced_appearance')->delete();

            return response()->json(['data' => null, 'message' => 'Firmen-CI aufgehoben.']);
        }

        Setting::setPayload('enforced_appearance', $validated);

        return response()->json(['data' => $validated, 'message' => 'Firmen-CI gespeichert.']);
    }

    // ── TYPO3-Integration: Betriebsmodus (CORS / Reverse-Proxy / API Designer) ──

    private const TYPO3_INTEGRATION_CACHE_KEY = 'typo3_integration_setting';

    // Sanctum-Ability für Embed-Service-Token — bewusst NICHT '*': ein Token mit
    // dieser (und nur dieser) Fähigkeit wird von RestrictScopedApiToken auf die
    // öffentliche Katalog-API beschränkt, unabhängig von der PIM-Rolle des
    // zugehörigen Benutzers.
    private const CATALOG_READ_ABILITY = 'catalog:read';

    /**
     * GET /api/v1/settings/typo3-integration (authenticated)
     *
     * Liefert den gewählten Betriebsmodus für die TYPO3-Integrations-Readme.
     */
    public function typo3Integration(): JsonResponse
    {
        $payload = Setting::getPayload('typo3_integration') ?? ['mode' => 'cors'];

        return response()->json(['data' => $payload]);
    }

    /**
     * PUT /api/v1/settings/typo3-integration (admin only)
     *
     * Speichert den Betriebsmodus. Im Modus "cors" wird die angegebene Origin
     * zusätzlich dynamisch in die CORS-Freigabe übernommen (siehe AppServiceProvider).
     */
    public function updateTypo3Integration(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'mode' => 'required|string|in:cors,reverse_proxy,api_designer',
            'cors_origin' => [
                'nullable', 'string', 'max:255',
                'required_if:mode,cors',
                // Nur "schema://host[:port]" — kein Pfad, keine Wildcard (CORS-Origin-Freigabe!)
                'regex:/^https?:\/\/[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?(:\d{1,5})?$/',
            ],
            'reverse_proxy_path' => 'nullable|string|max:255',
            'api_template_id' => 'nullable|uuid|exists:api_templates,id',
            'catalog_template_id' => 'nullable|uuid|exists:catalog_templates,id',
        ]);

        // Mergen statt überschreiben — sonst gingen embed_user_id/embed_token
        // (separat über generateTypo3IntegrationEmbedToken() gesetzt) verloren.
        $existing = Setting::getPayload('typo3_integration') ?? [];
        $merged = array_merge($existing, $validated);
        Setting::setPayload('typo3_integration', $merged);
        Cache::forget(self::TYPO3_INTEGRATION_CACHE_KEY);

        return response()->json(['data' => $merged, 'message' => 'TYPO3-Integration gespeichert.']);
    }

    /**
     * GET /api/v1/settings/product-versions
     *
     * Liefert das aktuell wirksame Versions-Limit pro Produkt.
     */
    public function productVersions(): JsonResponse
    {
        $max = app(\App\Services\ProductVersioningService::class)->maxVersionsPerProduct();

        return response()->json(['data' => ['max_per_product' => $max]]);
    }

    /**
     * PUT /api/v1/settings/product-versions (admin only)
     *
     * Legt fest, wie viele Versionen pro Produkt maximal behalten werden
     * (0 = unbegrenzt). Ältere Versionen werden beim Anlegen neuer Versionen
     * automatisch entfernt; aktive und geplante Versionen bleiben unberührt.
     */
    public function updateProductVersions(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'max_per_product' => 'required|integer|min:0|max:10000',
        ]);

        Setting::setPayload('product_versions', ['max_per_product' => (int) $validated['max_per_product']]);

        return response()->json([
            'data' => ['max_per_product' => (int) $validated['max_per_product']],
            'message' => 'Versions-Limit gespeichert.',
        ]);
    }

    /**
     * POST /api/v1/settings/typo3-integration/embed-token (admin only)
     *
     * Erzeugt (bzw. erneuert) einen auf catalog:read beschränkten Sanctum-Token
     * für einen frei wählbaren, bereits existierenden anyPIM-Benutzer. Unabhängig
     * von dessen PIM-Rolle darf dieser Token ausschließlich die öffentliche
     * Katalog-API lesen (durchgesetzt von RestrictScopedApiToken) — gedacht zum
     * Einbetten in externe Websites (Starter-Kit, PublixxCatalog.init({ token })),
     * damit der Katalog dort ohne Login-Overlay erscheint, ohne dass ein
     * vollwertiger Benutzer-Token öffentlich im Quelltext landet.
     */
    public function generateTypo3IntegrationEmbedToken(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        $existing = Setting::getPayload('typo3_integration') ?? [];
        if (!empty($existing['embed_token_id'])) {
            PersonalAccessToken::find($existing['embed_token_id'])?->delete();
        }

        $user = User::findOrFail($validated['user_id']);
        $newToken = $user->createToken('catalog-embed', [self::CATALOG_READ_ABILITY]);

        $merged = array_merge($existing, [
            'embed_user_id' => $user->id,
            'embed_token_id' => $newToken->accessToken->id,
            'embed_token' => $newToken->plainTextToken,
        ]);
        Setting::setPayload('typo3_integration', $merged);
        Cache::forget(self::TYPO3_INTEGRATION_CACHE_KEY);

        return response()->json(['data' => $merged, 'message' => 'Service-Token erzeugt.']);
    }

    /**
     * DELETE /api/v1/settings/typo3-integration/embed-token (admin only)
     *
     * Widerruft den Embed-Service-Token (falls vorhanden).
     */
    public function revokeTypo3IntegrationEmbedToken(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $existing = Setting::getPayload('typo3_integration') ?? [];
        if (!empty($existing['embed_token_id'])) {
            PersonalAccessToken::find($existing['embed_token_id'])?->delete();
        }
        unset($existing['embed_user_id'], $existing['embed_token_id'], $existing['embed_token']);
        Setting::setPayload('typo3_integration', $existing);
        Cache::forget(self::TYPO3_INTEGRATION_CACHE_KEY);

        return response()->json(['data' => $existing, 'message' => 'Service-Token widerrufen.']);
    }

    /**
     * GET /api/v1/settings/typo3-integration/starter-kit (authenticated)
     *
     * ZIP mit catalog-embed-Bundle (JS/CSS) + einer lauffähigen Beispielseite
     * (index.html), deren API-Basis-URL bereits auf diese anyPIM-Instanz zeigt —
     * Blaupause für Agenturen, direkt entpackbar und im Browser testbar.
     */
    public function typo3IntegrationStarterKit(): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $jsPath = public_path('catalog-embed-assets/catalog-embed.umd.js');
        $cssPath = public_path('catalog-embed-assets/catalog-embed.css');

        if (!file_exists($jsPath) || !file_exists($cssPath)) {
            return response()->json(['message' => 'catalog-embed-Bundle wurde auf dieser Instanz noch nicht deployed.'], 404);
        }

        $payload = Setting::getPayload('typo3_integration') ?? [];

        // Falls eine Katalog-Vorlage gewählt ist (Integration > Typo 3): deren
        // html_template als Basis nehmen, statt der generischen Beispieldatei —
        // Branding/Layout des Starter-Kits entspricht dann der echten Vorlage.
        $selectedTemplate = null;
        if (!empty($payload['catalog_template_id'])) {
            $selectedTemplate = CatalogTemplate::where('id', $payload['catalog_template_id'])
                ->where('is_active', true)
                ->first();
        }

        if ($selectedTemplate) {
            $sampleHtml = $selectedTemplate->html_template;
            $templateLabel = $selectedTemplate->name;
        } else {
            $samplePath = base_path('catalog-embed/examples/basic.html');
            $sampleHtml = file_exists($samplePath)
                ? file_get_contents($samplePath)
                : '<!DOCTYPE html><html><head><link rel="stylesheet" href="catalog-embed.css"></head>'
                    . '<body><div data-catalog="search"></div><div data-catalog="product-grid"></div>'
                    . '<script src="catalog-embed.umd.js"></script></body></html>';
            $templateLabel = 'Standard-Beispiel (basic.html)';
        }

        // Für den ZIP-Kontext: Assets liegen relativ neben index.html statt unter
        // der absoluten /catalog-embed-assets/-URL dieser Instanz.
        $apiBase = rtrim(config('app.url'), '/') . '/api/v1';
        $sampleHtml = preg_replace('/src=["\'][^"\']*catalog-embed\.umd\.js["\']/', 'src="catalog-embed.umd.js"', $sampleHtml);
        $sampleHtml = preg_replace('/href=["\'][^"\']*catalog-embed\.css["\']/', 'href="catalog-embed.css"', $sampleHtml);

        // Manche Beispieldateien (z.B. basic.html) verlassen sich auf die Auto-Injektion
        // von CatalogEmbedController und referenzieren catalog-embed.css/.umd.js gar nicht
        // explizit — hier nachholen, sonst fehlt im ZIP das Styling bzw. das Widget-Bundle.
        if (!str_contains($sampleHtml, 'catalog-embed.css') && str_contains($sampleHtml, '</head>')) {
            $sampleHtml = str_replace(
                '</head>',
                '  <link rel="stylesheet" href="catalog-embed.css">' . "\n" . '</head>',
                $sampleHtml,
            );
        }
        if (!str_contains($sampleHtml, 'catalog-embed.umd.js') && str_contains($sampleHtml, '</body>')) {
            $sampleHtml = str_replace(
                '</body>',
                '  <script src="catalog-embed.umd.js"></script>' . "\n" . '</body>',
                $sampleHtml,
            );
        }
        $sampleHtml = preg_replace('/api:\s*[\'"]https?:\/\/[^"\']+\/api\/v1[\'"]/', "api: '{$apiBase}'", $sampleHtml);

        // Falls ein Embed-Service-Token konfiguriert ist (Betriebsmodus-Seite):
        // direkt in den init()-Aufruf einfügen, damit der Katalog auch bei
        // catalog_access_mode="login" ohne Login-Overlay erscheint. Der Token ist
        // bewusst auf catalog:read beschränkt (RestrictScopedApiToken).
        $embedToken = $payload['embed_token'] ?? null;
        $tokenNote = 'Kein Service-Token konfiguriert — falls "Katalog-Zugriff" auf "Login erforderlich"'
            . "\n                                 steht, zeigt die Seite ein Login-Overlay (siehe PIM-Menü Integration > Typo 3).";
        if (is_string($embedToken) && $embedToken !== '') {
            $sampleHtml = preg_replace(
                '/PublixxCatalog\.init\(\{/',
                "PublixxCatalog.init({\n    token: '" . addslashes($embedToken) . "',",
                $sampleHtml,
                1,
            );
            $tokenNote = 'Enthält einen auf Katalog-Lesezugriff beschränkten Service-Token — funktioniert auch,'
                . "\n                                 falls \"Katalog-Zugriff\" auf \"Login erforderlich\" steht.";
        }

        $readme = <<<TXT
        anyPIM Catalog-Embed — Starter-Kit
        ===================================

        index.html            – lauffähige Beispielseite (Vorlage: {$templateLabel}), zeigt live Produkte von:
                                 {$apiBase}
                                 {$tokenNote}
        catalog-embed.umd.js  – Widget-Bundle (Script-Tag in index.html)
        catalog-embed.css     – Styles (Link-Tag in index.html)

        Nutzung:
        1. Ordner entpacken, index.html im Browser öffnen — der Katalog läuft sofort live.
        2. Für die eigene CMS-Seite (TYPO3, WordPress, ...): das <div data-catalog="...">-Markup
           aus index.html ins eigene Template übernehmen, Script-/Link-Tags mit einbinden.
        3. Details zu CORS/Reverse-Proxy/API-Designer: PIM-Menü Integration > Typo 3.
        TXT;

        $tempFile = tempnam(sys_get_temp_dir(), 'catalog_embed_kit_');
        if ($tempFile === false) {
            return response()->json(['message' => 'Temp-Datei konnte nicht erstellt werden.'], 500);
        }
        $zipPath = $tempFile . '.zip';
        rename($tempFile, $zipPath);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            @unlink($zipPath);
            return response()->json(['message' => 'ZIP-Erstellung fehlgeschlagen.'], 500);
        }
        $zip->addFile($jsPath, 'catalog-embed.umd.js');
        $zip->addFile($cssPath, 'catalog-embed.css');
        $zip->addFromString('index.html', $sampleHtml);
        $zip->addFromString('README.txt', $readme);
        $zip->close();

        return response()->download($zipPath, 'catalog-embed-starter-kit.zip')->deleteFileAfterSend(true);
    }
}
