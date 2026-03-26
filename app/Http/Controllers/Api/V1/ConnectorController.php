<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ConnectorConnection;
use App\Models\Media;
use App\Models\Product;
use App\Models\WebsiteProfile;
use App\Services\Connectors\ConnectorRegistry;
use App\Services\Connectors\Shopware\ShopwareConnector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST-API für externe Connectoren (Canva, DeepL, Adobe u.a.).
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

        // Shopware: shop_url aus settings an authenticate übergeben
        $settings = $validated['settings'] ?? null;
        $shopUrl = $settings['shop_url'] ?? '';

        $tokens = $connector->handleCallback(
            $validated['code'],
            $validated['code_verifier'] ?? null,
            $shopUrl,
        );

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

        $product = Product::findOrFail($validated['product_id']);
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

        $products = Product::whereIn('id', $validated['product_ids'])->get()->all();
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
     * POST /connectors/connections/{connection}/sync-profile — Profil-basierter Komplett-Sync.
     */
    public function syncFromProfile(Request $request, ConnectorConnection $connection): JsonResponse
    {
        $this->authorize('sync', $connection);

        if ($connection->connector_type !== 'shopware') {
            return response()->json([
                'message' => 'Profil-Sync ist nur für Shopware-Verbindungen verfügbar.',
            ], 422);
        }

        $connector = $this->registry->get($connection->connector_type);
        if (! $connector instanceof ShopwareConnector) {
            return response()->json(['message' => 'Shopware-Connector nicht gefunden.'], 422);
        }

        try {
            $result = $connector->syncFromProfile($connection);

            return response()->json([
                'data' => [
                    'status'     => 'completed',
                    'categories' => $result['categories'],
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
}
