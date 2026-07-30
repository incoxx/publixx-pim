<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ConnectorConnection;
use App\Models\ConnectorProductChecksum;
use App\Models\ConnectorSyncLog;
use App\Models\Media;
use App\Models\Product;
use App\Models\WebsiteProfile;
use App\Services\Connectors\AnyPim\AnyPimConnector;
use App\Services\Connectors\ConnectorRegistry;
use App\Services\Connectors\Shopify\ShopifyConnector;
use App\Services\Connectors\Shopware\ShopwareConnector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * REST-API für externe Connectoren (Shopware, Shopify, DeepL u.a.).
 *
 *   GET    /api/v1/connectors                              — Verfügbare Connectoren
 *   GET    /api/v1/connectors/connections                  — Aktive Verbindungen
 *   POST   /api/v1/connectors/connections                  — Neue Verbindung anlegen
 *   GET    /api/v1/connectors/connections/{id}             — Verbindungs-Details
 *   DELETE /api/v1/connectors/connections/{id}             — Verbindung löschen
 *   GET    /api/v1/connectors/{type}/authorize             — OAuth-Redirect
 *   POST   /api/v1/connectors/{type}/callback              — OAuth-Callback
 *   POST   /api/v1/connectors/connections/{id}/sync-media       — Einzelnes Asset synchronisieren
 *   POST   /api/v1/connectors/connections/{id}/sync-media-bulk  — Bulk Asset-Sync
 *   POST   /api/v1/connectors/connections/{id}/sync-product      — Einzelnes Produkt synchronisieren
 *   POST   /api/v1/connectors/connections/{id}/sync-product-bulk — Bulk Produkt-Sync
 *   GET    /api/v1/connectors/connections/{id}/sync-logs        — Sync-Protokoll
 *   PUT    /api/v1/connectors/connections/{id}                — Verbindung aktualisieren (Settings/Profil)
 *   POST   /api/v1/connectors/connections/{id}/sync-profile   — Profil-basierter Komplett-Sync
 *   GET    /api/v1/connectors/website-profiles                — Verfügbare Vorschau-Profile
 */
class ConnectorController extends Controller
{
    public function __construct(
        private readonly ConnectorRegistry $registry,
    ) {}

    /**
     * GET /connectors — Alle verfügbaren Connectoren.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => array_values($this->registry->toArray()),
        ]);
    }

    /**
     * GET /connectors/connections — Alle aktiven Verbindungen.
     */
    public function connections(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConnectorConnection::class);

        $connections = ConnectorConnection::query()
            ->with('connectedByUser:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (ConnectorConnection $c) => [
                'id'               => $c->id,
                'connector_type'   => $c->connector_type,
                'name'             => $c->name,
                'is_active'        => $c->is_active,
                'token_expires_at' => $c->token_expires_at,
                'token_expired'    => $c->isTokenExpired(),
                'settings'         => $c->settings,
                'connected_by'     => $c->connectedByUser,
                'created_at'       => $c->created_at,
                'updated_at'       => $c->updated_at,
            ]);

        return response()->json(['data' => $connections]);
    }

    /**
     * GET /connectors/connections/{connection} — Verbindungs-Details.
     */
    public function showConnection(ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('view', $connection);

        $connection->load('connectedByUser:id,name,email');

        return response()->json([
            'data' => [
                'id'               => $connection->id,
                'connector_type'   => $connection->connector_type,
                'name'             => $connection->name,
                'is_active'        => $connection->is_active,
                'token_expires_at' => $connection->token_expires_at,
                'token_expired'    => $connection->isTokenExpired(),
                'settings'         => $connection->settings,
                'connected_by'     => $connection->connectedByUser,
                'created_at'       => $connection->created_at,
                'updated_at'       => $connection->updated_at,
                'sync_stats'       => [
                    'total'   => $connection->syncLogs()->count(),
                    'success' => $connection->syncLogs()->where('status', 'success')->count(),
                    'failed'  => $connection->syncLogs()->where('status', 'failed')->count(),
                ],
            ],
        ]);
    }

    /**
     * POST /connectors/connections — Neue Verbindung speichern (nach OAuth).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ConnectorConnection::class);

        $validated = $request->validate([
            'connector_type' => 'required|string|max:50',
            'name'           => 'required|string|max:255',
            'access_token'   => 'required|string',
            'refresh_token'  => 'sometimes|string|nullable',
            'expires_in'     => 'sometimes|integer|nullable',
            'settings'       => 'sometimes|array|nullable',
        ]);

        $connector = $this->registry->get($validated['connector_type']);
        if (! $connector) {
            return response()->json([
                'message' => "Unbekannter Connector-Typ: {$validated['connector_type']}",
            ], 422);
        }

        $connection = ConnectorConnection::create([
            'connector_type'   => $validated['connector_type'],
            'name'             => $validated['name'],
            'access_token'     => $validated['access_token'],
            'refresh_token'    => $validated['refresh_token'] ?? null,
            'token_expires_at' => isset($validated['expires_in'])
                ? now()->addSeconds($validated['expires_in'])
                : null,
            'settings'     => $validated['settings'] ?? null,
            'is_active'    => true,
            'connected_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $connection->only(['id', 'connector_type', 'name', 'is_active', 'token_expires_at', 'settings', 'created_at']),
        ], 201);
    }

    /**
     * DELETE /connectors/connections/{connection}
     */
    public function destroy(ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('delete', $connection);

        $connection->syncLogs()->delete();
        $connection->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /connectors/{type}/authorize — OAuth-Autorisierung starten.
     */
    public function startAuthorization(string $type): JsonResponse
    {
        $connector = $this->registry->get($type);
        if (! $connector) {
            return response()->json(['message' => "Unbekannter Connector-Typ: {$type}"], 404);
        }

        if (! $connector->isConfigured()) {
            return response()->json(['message' => "Connector '{$type}' ist nicht konfiguriert. Bitte Credentials in .env hinterlegen."], 422);
        }

        $authData = $connector->getAuthorizationUrl();

        return response()->json(['data' => $authData]);
    }

    /**
     * POST /connectors/{type}/callback — OAuth-Callback verarbeiten.
     */
    public function callback(Request $request, string $type): JsonResponse
    {
        $this->authorize('create', ConnectorConnection::class);

        $connector = $this->registry->get($type);
        if (! $connector) {
            return response()->json(['message' => "Unbekannter Connector-Typ: {$type}"], 404);
        }

        $validated = $request->validate([
            'code'          => 'required|string',
            'code_verifier' => 'sometimes|string|nullable',
            'name'          => 'sometimes|string|max:255',
            'settings'      => 'sometimes|array|nullable',
        ]);

        // Remote-URL aus settings (Shopware: shop_url, anyPIM: remote_url)
        $settings = $validated['settings'] ?? null;
        $shopUrl = $settings['shop_url'] ?? $settings['remote_url'] ?? '';

        try {
            $tokens = $connector->handleCallback(
                $validated['code'],
                $validated['code_verifier'] ?? null,
                $shopUrl,
            );
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $body = $e->response?->json();
            $detail = $body['errors'] ?? $body['error'] ?? $e->getMessage();
            if (is_array($detail)) {
                $detail = json_encode($detail, JSON_UNESCAPED_UNICODE);
            }

            return response()->json([
                'message' => "Authentifizierung fehlgeschlagen: {$detail}",
            ], $e->response?->status() === 401 ? 401 : 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Verbindung fehlgeschlagen: ' . $e->getMessage(),
            ], 422);
        }

        // anyPIM: API-Key wird als access_token gespeichert (verschlüsselt via ConnectorConnection-Cast).
        // Keine zusätzliche Verschlüsselung in Settings nötig.

        $connection = ConnectorConnection::create([
            'connector_type'   => $connector->getType(),
            'name'             => $validated['name'] ?? ucfirst($type) . '-Verbindung',
            'access_token'     => $tokens['access_token'],
            'refresh_token'    => $tokens['refresh_token'] ?? null,
            'token_expires_at' => isset($tokens['expires_in'])
                ? now()->addSeconds($tokens['expires_in'])
                : null,
            'settings'     => $settings,
            'is_active'    => true,
            'connected_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $connection->only(['id', 'connector_type', 'name', 'is_active', 'token_expires_at', 'settings', 'created_at']),
        ], 201);
    }

    /**
     * POST /connectors/connections/{connection}/sync-media — Einzelnes Asset sync.
     */
    public function syncMedia(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        $validated = $request->validate([
            'media_id' => 'required|uuid|exists:media,id',
        ]);

        $connector = $this->registry->get($connection->connector_type);
        if (! $connector) {
            return response()->json(['message' => 'Connector nicht gefunden'], 422);
        }

        $media = Media::findOrFail($validated['media_id']);
        $externalId = $connector->uploadAsset($connection, $media);

        return response()->json([
            'data' => [
                'media_id'    => $media->id,
                'external_id' => $externalId,
                'status'      => 'success',
            ],
        ]);
    }

    /**
     * POST /connectors/connections/{connection}/sync-media-bulk — Bulk Asset-Sync.
     */
    public function syncMediaBulk(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        $validated = $request->validate([
            'media_ids'   => 'required|array|min:1|max:100',
            'media_ids.*' => 'uuid|exists:media,id',
        ]);

        $connector = $this->registry->get($connection->connector_type);
        if (! $connector) {
            return response()->json(['message' => 'Connector nicht gefunden'], 422);
        }

        $mediaItems = Media::whereIn('id', $validated['media_ids'])->get()->all();
        $results = $connector->uploadAssetsBulk($connection, $mediaItems);

        return response()->json(['data' => $results]);
    }

    /**
     * POST /connectors/connections/{connection}/sync-product — Einzelnes Produkt sync.
     */
    public function syncProduct(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        $validated = $request->validate([
            'product_id'        => 'required|uuid|exists:products,id',
            'language'          => 'sometimes|string|in:de,en,fr,es,it',
            'task'              => 'sometimes|string|in:description,seo,features,marketing',
            'tonality'          => 'sometimes|string|max:200',
            'custom_prompt'     => 'sometimes|string|max:2000|nullable',
            'attributes'        => 'sometimes|array',
            'attributes.*'      => 'string',
            'include_prices'    => 'sometimes|boolean',
            'include_media'     => 'sometimes|boolean',
            'save_as_attribute' => 'sometimes|boolean',
            'target_attribute'  => 'sometimes|string|max:255|nullable',
            'brand_template_id' => 'sometimes|string|nullable',
        ]);

        $connector = $this->registry->get($connection->connector_type);
        if (! $connector) {
            return response()->json(['message' => 'Connector nicht gefunden'], 422);
        }

        $product = Product::with('media')->findOrFail($validated['product_id']);
        $options = array_diff_key($validated, ['product_id' => true]);

        try {
            $result = $connector->pushProductData($connection, $product, $options);
            return response()->json(['data' => $result]);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $body = $e->response?->json();
            $errors = $body['errors'] ?? [];
            $details = collect($errors)->map(fn ($err) => $err['detail'] ?? $err['code'] ?? '')->filter()->implode(' | ');

            return response()->json([
                'message' => 'Shopware hat den Sync abgelehnt: ' . ($details ?: $e->getMessage()),
            ], $e->response?->status() ?? 500);
        }
    }

    /**
     * POST /connectors/connections/{connection}/sync-product-bulk — Bulk Produkt-Sync.
     */
    public function syncProductBulk(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        $validated = $request->validate([
            'product_ids'       => 'required|array|min:1|max:50',
            'product_ids.*'     => 'uuid|exists:products,id',
            'language'          => 'sometimes|string|in:de,en,fr,es,it',
            'attributes'        => 'sometimes|array',
            'attributes.*'      => 'string',
            'include_prices'    => 'sometimes|boolean',
            'include_media'     => 'sometimes|boolean',
            'brand_template_id' => 'sometimes|string|nullable',
        ]);

        $connector = $this->registry->get($connection->connector_type);
        if (! $connector) {
            return response()->json(['message' => 'Connector nicht gefunden'], 422);
        }

        $products = Product::with('media')->whereIn('id', $validated['product_ids'])->get()->all();
        $options = array_diff_key($validated, ['product_ids' => true]);

        $results = $connector->pushProductDataBulk($connection, $products, $options);

        return response()->json(['data' => $results]);
    }

    /**
     * GET /connectors/connections/{connection}/sync-logs — Sync-Protokoll.
     */
    public function syncLogs(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('view', $connection);

        $logs = $connection->syncLogs()
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('action'), fn ($q, $action) => $q->where('action', $action))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 50));

        return response()->json($logs);
    }

    /**
     * DELETE /connectors/connections/{connection}/sync-logs — Sync-Protokoll löschen.
     */
    public function clearSyncLogs(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        try {
            $count = $connection->syncLogs()->count();
            $connection->syncLogs()->delete();

            return response()->json([
                'message' => "{$count} Sync-Einträge gelöscht.",
                'deleted' => $count,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Fehler beim Löschen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /connectors/connections/{connection} — Verbindung aktualisieren (Settings + Export-Profil).
     */
    public function update(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('update', $connection);

        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'settings' => 'sometimes|array',
            'settings.shop_url'            => 'sometimes|string|url',
            'settings.client_id'           => 'sometimes|string',
            'settings.client_secret'       => 'sometimes|string',
            'settings.website_profile_id'  => 'sometimes|nullable|string',
            'settings.shopware_fields'     => 'sometimes|array',
            'settings.shopware_fields.*'   => 'sometimes|array',
            'settings.shopify_fields'      => 'sometimes|array',
            'settings.shopify_fields.*'    => 'sometimes|array',
            'settings.access_token'        => 'sometimes|string',
        ]);

        if (isset($validated['name'])) {
            $connection->name = $validated['name'];
        }

        if (isset($validated['settings'])) {
            $existingSettings = $connection->settings ?? [];
            $connection->settings = array_merge($existingSettings, $validated['settings']);
        }

        $connection->save();

        return response()->json([
            'data' => [
                'id'             => $connection->id,
                'connector_type' => $connection->connector_type,
                'name'           => $connection->name,
                'settings'       => $connection->settings,
                'updated_at'     => $connection->updated_at,
            ],
        ]);
    }

    /**
     * POST /connectors/connections/{connection}/preview-product — Dry Run: zeigt was an den Shop gesendet würde.
     */
    public function previewProduct(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('view', $connection);

        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'language'   => 'sometimes|string|in:de,en,fr,es,it',
        ]);

        $settings = $connection->settings ?? [];
        $profileId = $settings['website_profile_id'] ?? null;

        $profilePayload = [];
        if ($profileId) {
            $profile = \App\Models\WebsiteProfile::find($profileId);
            $profilePayload = $profile?->payload ?? [];
        }

        $language = $validated['language'] ?? $profilePayload['default_locale'] ?? 'de';
        $product = Product::with(['media', 'masterHierarchyNode'])->findOrFail($validated['product_id']);

        // Connector-spezifische Preview
        if ($connection->connector_type === 'shopify') {
            $shopifyFields = $settings['shopify_fields'] ?? [];
            $productService = app(\App\Services\Connectors\Shopify\ShopifyProductService::class);

            // Metafields auflösen
            $metafields = [];
            $metafieldAttrIds = $shopifyFields['_metafield_attribute_ids'] ?? [];
            if (empty($metafieldAttrIds)) {
                $metafieldAttrIds = $productService->resolveMetafieldAttributeIds($product, $profilePayload);
            }
            if (!empty($metafieldAttrIds)) {
                $selectionValues = \App\Models\ProductAttributeValue::where('product_id', $product->id)
                    ->whereIn('attribute_id', $metafieldAttrIds)
                    ->whereNotNull('value_selection_id')
                    ->with(['attribute', 'valueListEntry'])
                    ->get();

                foreach ($selectionValues as $val) {
                    $metafields[] = [
                        'attribute' => $val->attribute?->name_de ?? $val->attribute_id,
                        'value'     => $val->valueListEntry?->display_value_de ?? $val->value_selection_id,
                    ];
                }
            }

            $payload = $productService->previewProductData($product, $profilePayload, $shopifyFields, $language, []);

            // Kategorie-Zuordnung
            $categoryName = null;
            if ($product->master_hierarchy_node_id) {
                $externalCatId = ConnectorSyncLog::where('connector_connection_id', $connection->id)
                    ->where('action', 'category_sync')
                    ->where('entity_type', 'hierarchy_node')
                    ->where('entity_id', $product->master_hierarchy_node_id)
                    ->where('status', 'success')
                    ->whereNotNull('external_id')
                    ->latest()
                    ->value('external_id');
                if ($externalCatId) {
                    $node = $product->masterHierarchyNode;
                    $categoryName = $node?->name_de ?? $node?->name_en ?? $product->master_hierarchy_node_id;
                }
            }

            return response()->json([
                'data' => [
                    'product_payload' => $payload,
                    'metafields'      => $metafields,
                    'category'        => $categoryName,
                    'media_count'     => $product->media()->count(),
                    'language'        => $language,
                    'profile'         => $profileId ? ($profilePayload['hierarchy_id'] ?? null) : null,
                ],
            ]);
        }

        // Shopware (default)
        $shopwareFields = $settings['shopware_fields'] ?? [];

        // Tax-ID Default auflösen (ohne API-Call — nur Platzhalter anzeigen)
        $taxIdMapping = $shopwareFields['tax_id'] ?? [];
        if (empty($taxIdMapping) || ($taxIdMapping['mode'] ?? '') === 'default') {
            $shopwareFields['tax_id'] = ['mode' => 'fixed', 'value' => '(auto: Standard-Steuer aus Shopware)'];
        }

        $productService = app(\App\Services\Connectors\Shopware\ShopwareProductService::class);

        // Properties auflösen — manuell oder automatisch aus Profil-Attributsichten
        $properties = [];
        $propertyAttrIds = $shopwareFields['_property_attribute_ids'] ?? [];
        if (empty($propertyAttrIds)) {
            $propertyAttrIds = $productService->resolvePropertyAttributeIds($product, $profilePayload);
        }
        if (!empty($propertyAttrIds)) {
            $selectionValues = \App\Models\ProductAttributeValue::where('product_id', $product->id)
                ->whereIn('attribute_id', $propertyAttrIds)
                ->whereNotNull('value_selection_id')
                ->with(['attribute', 'valueListEntry'])
                ->get();

            foreach ($selectionValues as $val) {
                $properties[] = [
                    'group'  => $val->attribute?->name_de ?? $val->attribute_id,
                    'option' => $val->valueListEntry?->display_value_de ?? $val->value_selection_id,
                ];
            }
        }

        // Kategorie-Zuordnung aus Sync-Logs
        $categoryName = null;
        if ($product->master_hierarchy_node_id) {
            $shopwareCategoryId = ConnectorSyncLog::where('connector_connection_id', $connection->id)
                ->where('action', 'category_sync')
                ->where('entity_type', 'hierarchy_node')
                ->where('entity_id', $product->master_hierarchy_node_id)
                ->where('status', 'success')
                ->whereNotNull('external_id')
                ->latest()
                ->value('external_id');
            if ($shopwareCategoryId) {
                $node = $product->masterHierarchyNode;
                $categoryName = $node?->name_de ?? $node?->name_en ?? $product->master_hierarchy_node_id;
            }
        }

        $payload = $productService->previewProductData($product, $profilePayload, $shopwareFields, $language, []);

        return response()->json([
            'data' => [
                'product_payload' => $payload,
                'properties'      => $properties,
                'category'        => $categoryName,
                'media_count'     => $product->media()->count(),
                'language'        => $language,
                'profile'         => $profileId ? ($profilePayload['hierarchy_id'] ?? null) : null,
            ],
        ]);
    }

    /**
     * GET /connectors/connections/{connection}/sync-product-ids — Produkt-IDs für Sync ermitteln.
     */
    public function syncProductIds(ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('view', $connection);

        $settings = $connection->settings ?? [];
        $profileId = $settings['website_profile_id'] ?? null;

        if (!$profileId) {
            return response()->json(['data' => ['product_ids' => [], 'total' => 0]]);
        }

        $profile = WebsiteProfile::find($profileId);
        $payload = $profile?->payload ?? [];

        // Gleiche Logik wie loadProductsFromProfile
        $hierarchyId = $payload['hierarchy_id'] ?? null;
        $linkedOnly = !empty($payload['catalog_linked_products_only']);
        $excludedNodeIds = $payload['catalog_excluded_node_ids'] ?? [];

        $query = Product::query();

        if ($hierarchyId && $linkedOnly) {
            $hierarchy = \App\Models\Hierarchy::find($hierarchyId);
            if ($hierarchy) {
                $nodeQuery = \App\Models\HierarchyNode::where('hierarchy_id', $hierarchy->id)
                    ->where('is_active', true);
                if (!empty($excludedNodeIds)) {
                    $nodeQuery->whereNotIn('id', $excludedNodeIds);
                }
                $nodeIds = $nodeQuery->pluck('id');

                if ($hierarchy->hierarchy_type === 'output') {
                    $productIds = \App\Models\OutputHierarchyProductAssignment::whereIn('hierarchy_node_id', $nodeIds)
                        ->pluck('product_id');
                    $query->whereIn('id', $productIds);
                } else {
                    $query->whereIn('master_hierarchy_node_id', $nodeIds);
                }
            }
        }

        $ids = $query->pluck('id')->toArray();

        return response()->json([
            'data' => [
                'product_ids' => $ids,
                'total'       => count($ids),
            ],
        ]);
    }

    /**
     * POST /connectors/connections/{connection}/sync-hierarchy — Hierarchie-Baum synchronisieren.
     */
    public function syncHierarchy(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        if (!in_array($connection->connector_type, ['shopware', 'shopify'])) {
            return response()->json(['message' => 'Nur für Shopware/Shopify-Verbindungen.'], 422);
        }

        $connector = $this->registry->get($connection->connector_type);
        if (! $connector instanceof ShopwareConnector && ! $connector instanceof ShopifyConnector) {
            return response()->json(['message' => 'Connector nicht gefunden.'], 422);
        }

        try {
            $result = $connector->syncHierarchy($connection);

            return response()->json([
                'data' => [
                    'status'         => 'completed',
                    'hierarchy_name' => $result['hierarchy_name'],
                    'total_nodes'    => $result['total_nodes'],
                    'synced'         => $result['synced'],
                    'errors'         => $result['errors'],
                    'error_details'  => $result['error_details'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Hierarchie-Sync fehlgeschlagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /connectors/connections/{connection}/sync-profile — Profil-basierter Komplett-Sync.
     */
    public function syncFromProfile(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        if (!in_array($connection->connector_type, ['shopware', 'shopify'])) {
            return response()->json([
                'message' => 'Profil-Sync ist nur für Shopware/Shopify-Verbindungen verfügbar.',
            ], 422);
        }

        $connector = $this->registry->get($connection->connector_type);
        if (! $connector instanceof ShopwareConnector && ! $connector instanceof ShopifyConnector) {
            return response()->json(['message' => 'Connector nicht gefunden.'], 422);
        }

        try {
            $result = $connector->syncFromProfile($connection);

            return response()->json([
                'data' => [
                    'status'     => 'completed',
                    'products'   => $result['products'],
                    'media'      => $result['media'],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Sync fehlgeschlagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /connectors/website-profiles — Verfügbare Vorschau-Profile für Connector-Konfiguration.
     */
    public function websiteProfiles(): JsonResponse
    {
        $profiles = WebsiteProfile::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (WebsiteProfile $p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'is_active' => $p->is_active,
                'hierarchy_id' => $p->payload['hierarchy_id'] ?? null,
                'locale'       => $p->payload['default_locale'] ?? 'de',
            ]);

        return response()->json(['data' => $profiles]);
    }

    /**
     * POST /connectors/connections/{connection}/delta-sync — Delta-Sync (nur neue/geänderte Produkte).
     */
    public function deltaSync(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        if (!in_array($connection->connector_type, ['shopware', 'shopify'])) {
            return response()->json([
                'message' => 'Delta-Sync ist nur für Shopware/Shopify-Verbindungen verfügbar.',
            ], 422);
        }

        $connector = $this->registry->get($connection->connector_type);
        if (! $connector instanceof ShopwareConnector && ! $connector instanceof ShopifyConnector) {
            return response()->json(['message' => 'Connector nicht gefunden.'], 422);
        }

        try {
            $result = $connector->deltaSyncFromProfile($connection);

            return response()->json([
                'data' => [
                    'status'     => 'completed',
                    'products'   => $result['products'],
                    'media'      => $result['media'],
                    'orphans'    => $result['orphans'],
                    'properties' => $result['properties'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Delta-Sync fehlgeschlagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /connectors/connections/{connection}/checksums — Produkt-Checksums anzeigen.
     */
    public function checksums(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('view', $connection);

        $checksums = ConnectorProductChecksum::where('connection_id', $connection->id)
            ->join('products', 'products.id', '=', 'connector_product_checksums.product_id')
            ->select([
                'connector_product_checksums.product_id',
                'connector_product_checksums.checksum',
                'connector_product_checksums.synced_at',
                'connector_product_checksums.external_id',
                'products.sku',
                'products.name',
            ])
            ->orderByDesc('connector_product_checksums.synced_at')
            ->paginate($request->input('per_page', 50));

        return response()->json($checksums);
    }

    /**
     * DELETE /connectors/connections/{connection}/checksums — Alle Checksums löschen (erzwingt Full-Sync).
     */
    public function clearChecksums(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        $count = ConnectorProductChecksum::where('connection_id', $connection->id)->count();
        ConnectorProductChecksum::where('connection_id', $connection->id)->delete();

        return response()->json([
            'message' => "{$count} Checksums gelöscht. Der nächste Delta-Sync wird alle Produkte übertragen.",
            'deleted' => $count,
        ]);
    }

    /**
     * DELETE /connectors/connections/{connection}/sync-logs/{syncLog} — Einzelnen Sync-Log löschen.
     */
    public function deleteSyncLog(Request $request, ConnectorConnection $connection, ConnectorSyncLog $syncLog): JsonResponse
    {
        $this->authorize('sync', $connection);

        if ($syncLog->connector_connection_id !== $connection->id) {
            return response()->json(['message' => 'Sync-Log gehört nicht zu dieser Verbindung.'], 403);
        }

        $syncLog->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /connectors/connections/{connection}/reset — Alle PIM-Daten aus Shopware löschen.
     */
    public function resetShop(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        if (!in_array($connection->connector_type, ['shopware', 'shopify'])) {
            return response()->json(['message' => 'Nur für Shopware/Shopify-Verbindungen.'], 422);
        }

        $connector = $this->registry->get($connection->connector_type);
        if (! $connector instanceof ShopwareConnector && ! $connector instanceof ShopifyConnector) {
            return response()->json(['message' => 'Connector nicht gefunden.'], 422);
        }

        try {
            $result = $connector->resetShop($connection);

            return response()->json([
                'data' => [
                    'status'     => 'completed',
                    'products'   => $result['products'],
                    'categories' => $result['categories'],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Reset fehlgeschlagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /connectors/connections/{connection}/purge-categories — Alle Kategorien aus Shopware löschen.
     */
    public function purgeCategories(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        if (!in_array($connection->connector_type, ['shopware', 'shopify'])) {
            return response()->json(['message' => 'Nur für Shopware/Shopify-Verbindungen.'], 422);
        }

        $connector = $this->registry->get($connection->connector_type);
        if (! $connector instanceof ShopwareConnector && ! $connector instanceof ShopifyConnector) {
            return response()->json(['message' => 'Connector nicht gefunden.'], 422);
        }

        try {
            $result = $connector->purgeCategories($connection);

            return response()->json([
                'data' => [
                    'status'  => 'completed',
                    'deleted' => $result['deleted'],
                    'failed'  => $result['failed'],
                    'total'   => $result['total'],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Kategorie-Löschung fehlgeschlagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /connectors/connections/{connection}/generate-thumbnails — Thumbnails in Shopware generieren.
     */
    public function generateThumbnails(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        if (!in_array($connection->connector_type, ['shopware', 'shopify'])) {
            return response()->json(['message' => 'Nur für Shopware/Shopify-Verbindungen.'], 422);
        }

        $connector = $this->registry->get($connection->connector_type);
        if (! $connector instanceof ShopwareConnector && ! $connector instanceof ShopifyConnector) {
            return response()->json(['message' => 'Connector nicht gefunden.'], 422);
        }

        // Shopify generiert Thumbnails automatisch
        if ($connection->connector_type === 'shopify') {
            return response()->json(['data' => ['method' => 'auto', 'success' => true, 'message' => 'Shopify generiert Thumbnails automatisch.']]);
        }

        try {
            $http = $connector->getAuthenticatedRequest($connection);
            $shopUrl = rtrim($connection->settings['shop_url'] ?? config('connectors.shopware.shop_url'), '/');

            $mediaService = app(\App\Services\Connectors\Shopware\ShopwareMediaService::class);
            $result = $mediaService->generateThumbnails($http, $shopUrl);

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Thumbnail-Generierung fehlgeschlagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /connectors/connections/{connection}/purge-media — Alle PIM-Medien aus dem Shop löschen.
     */
    public function purgeMedia(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        if (!in_array($connection->connector_type, ['shopware', 'shopify'])) {
            return response()->json(['message' => 'Nur für Shopware/Shopify-Verbindungen.'], 422);
        }

        $connector = $this->registry->get($connection->connector_type);
        if (! $connector instanceof ShopwareConnector && ! $connector instanceof ShopifyConnector) {
            return response()->json(['message' => 'Connector nicht gefunden.'], 422);
        }

        // Shopify: Bilder gehören zu Produkten und werden mit diesen gelöscht
        if ($connection->connector_type === 'shopify') {
            return response()->json([
                'data' => [
                    'status'  => 'completed',
                    'deleted' => 0,
                    'failed'  => 0,
                    'total'   => 0,
                    'message' => 'Shopify-Bilder werden beim Produkt-Reset automatisch gelöscht.',
                ],
            ]);
        }

        try {
            $result = $connector->purgeMedia($connection);

            return response()->json([
                'data' => [
                    'status'  => 'completed',
                    'deleted' => $result['deleted'],
                    'failed'  => $result['failed'],
                    'total'   => $result['total'],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Media-Löschung fehlgeschlagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /connectors/connections/{connection}/sync-logs/export — Sync-Logs als Excel exportieren.
     */
    public function exportSyncLogs(Request $request, ConnectorConnection $connection): StreamedResponse
    {
        $this->authorize('view', $connection);

        $logs = $connection->syncLogs()
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('action'), fn ($q, $action) => $q->where('action', $action))
            ->orderByDesc('created_at')
            ->limit(10000)
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sync-Logs');

        // Header
        $headers = ['Datum', 'Aktion', 'Entity-Typ', 'Entity-ID', 'External-ID', 'Status', 'Fehler', 'SKU', 'Produktname'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        // Zeilen mit Daten
        $row = 2;
        foreach ($logs as $log) {
            $meta = $log->meta ?? [];
            $sheet->setCellValue([1, $row], $log->created_at?->format('Y-m-d H:i:s'));
            $sheet->setCellValue([2, $row], $log->action);
            $sheet->setCellValue([3, $row], $log->entity_type);
            $sheet->setCellValue([4, $row], $log->entity_id);
            $sheet->setCellValue([5, $row], $log->external_id);
            $sheet->setCellValue([6, $row], $log->status);
            $sheet->setCellValue([7, $row], $log->error_message);
            $sheet->setCellValue([8, $row], $meta['sku'] ?? '');
            $sheet->setCellValue([9, $row], $meta['product_name'] ?? '');
            $row++;
        }

        // Auto-Breite
        foreach (range(1, 9) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $fileName = "sync-logs-{$connection->name}-" . date('Y-m-d') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    // =====================================================================
    // anyPIM Bidirektionaler Sync
    // =====================================================================

    /**
     * Produkte von einer Remote-anyPIM-Instanz pullen.
     *
     * POST /api/v1/connectors/connections/{connection}/pull-products
     */
    public function pullProducts(Request $request, ConnectorConnection $connection, AnyPimConnector $connector): JsonResponse
    {
        $this->authorize('sync', $connection);

        if ($connection->connector_type !== 'anypim') {
            return response()->json(['error' => 'Pull ist nur für anyPIM-Connections verfügbar.'], 422);
        }

        $filters = $request->only(['hierarchy_id', 'product_type_ids', 'skus', 'updated_since']);
        $options = [
            'conflict_resolution' => $request->input('conflict_resolution', $connection->settings['conflict_resolution'] ?? 'remote_wins'),
            'sync_attributes'     => $request->boolean('sync_attributes', true),
            'sync_prices'         => $request->boolean('sync_prices', true),
            'sync_media'          => $request->boolean('sync_media', true),
        ];

        $isDelta = $request->boolean('delta', false);

        $result = $isDelta
            ? $connector->deltaPull($connection, $filters, $options)
            : $connector->pullAll($connection, $filters, $options);

        return response()->json(['data' => $result]);
    }

    /**
     * Nur Übersetzungen von Remote-Instanz pullen.
     *
     * POST /api/v1/connectors/connections/{connection}/pull-translations
     */
    public function pullTranslations(Request $request, ConnectorConnection $connection, AnyPimConnector $connector): JsonResponse
    {
        $this->authorize('sync', $connection);

        if ($connection->connector_type !== 'anypim') {
            return response()->json(['error' => 'Pull ist nur für anyPIM-Connections verfügbar.'], 422);
        }

        $filters = $request->only(['hierarchy_id', 'product_type_ids', 'skus']);
        $options = [
            'conflict_resolution' => 'remote_wins',
            'sync_attributes'     => true,
            'sync_prices'         => false,
            'sync_media'          => false,
        ];

        $result = $connector->pullAll($connection, $filters, $options);

        return response()->json(['data' => $result]);
    }

    /**
     * Bidirektionaler Sync (Push + Pull).
     *
     * POST /api/v1/connectors/connections/{connection}/sync-bidirectional
     */
    public function syncBidirectional(Request $request, ConnectorConnection $connection, AnyPimConnector $connector): JsonResponse
    {
        $this->authorize('sync', $connection);

        if ($connection->connector_type !== 'anypim') {
            return response()->json(['error' => 'Bidirektionaler Sync ist nur für anyPIM-Connections verfügbar.'], 422);
        }

        $filters = $request->only(['hierarchy_id', 'product_type_ids', 'skus']);
        $options = [];
        if ($request->has('conflict_resolution')) {
            $options['conflict_resolution'] = $request->input('conflict_resolution');
        }

        $result = $connector->syncBidirectional($connection, $filters, $options);

        return response()->json(['data' => $result]);
    }

    /**
     * anyPIM-Verbindung testen.
     *
     * POST /api/v1/connectors/connections/{connection}/test-connection
     */
    public function testAnyPimConnection(ConnectorConnection $connection, AnyPimConnector $connector): JsonResponse
    {
        $this->authorize('sync', $connection);

        if ($connection->connector_type !== 'anypim') {
            return response()->json(['error' => 'Test ist nur für anyPIM-Connections verfügbar.'], 422);
        }

        $result = $connector->testConnection($connection);

        $statusCode = $result['status'] === 'ok' ? 200 : 422;

        return response()->json(['data' => $result], $statusCode);
    }
}
