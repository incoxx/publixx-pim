<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\CanvaExportProfile;
use App\Models\Product;
use App\Services\Connectors\ConnectorRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CanvaExportProfileController extends Controller
{
    public function __construct(
        private readonly ConnectorRegistry $registry,
    ) {}

    /**
     * GET /canva-export-profiles — Alle sichtbaren Profile.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CanvaExportProfile::class);

        $profiles = CanvaExportProfile::visibleTo($request->user()->id)
            ->with(['connectorConnection:id,name,connector_type', 'searchProfile:id,name'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $profiles]);
    }

    /**
     * POST /canva-export-profiles — Neues Profil erstellen.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CanvaExportProfile::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_shared' => 'boolean',
            'connector_connection_id' => 'nullable|string|uuid|exists:connector_connections,id',
            'search_profile_id' => 'nullable|string|uuid|exists:search_profiles,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'string|uuid',
            'attribute_ids' => 'nullable|array',
            'attribute_ids.*' => 'string|uuid',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:5',
            'include_prices' => 'boolean',
            'include_media' => 'boolean',
            'brand_template_id' => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = $request->user()->id;

        $profile = CanvaExportProfile::create($validated);

        return response()->json([
            'data' => $profile->load(['connectorConnection:id,name,connector_type', 'searchProfile:id,name']),
        ], 201);
    }

    /**
     * GET /canva-export-profiles/{profile} — Profil-Details.
     */
    public function show(CanvaExportProfile $canvaExportProfile): JsonResponse
    {
        $this->authorize('view', $canvaExportProfile);

        return response()->json([
            'data' => $canvaExportProfile->load(['connectorConnection:id,name,connector_type', 'searchProfile:id,name']),
        ]);
    }

    /**
     * PUT /canva-export-profiles/{profile} — Profil aktualisieren.
     */
    public function update(Request $request, CanvaExportProfile $canvaExportProfile): JsonResponse
    {
        $this->authorize('update', $canvaExportProfile);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_shared' => 'boolean',
            'connector_connection_id' => 'nullable|string|uuid|exists:connector_connections,id',
            'search_profile_id' => 'nullable|string|uuid|exists:search_profiles,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'string|uuid',
            'attribute_ids' => 'nullable|array',
            'attribute_ids.*' => 'string|uuid',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:5',
            'include_prices' => 'boolean',
            'include_media' => 'boolean',
            'brand_template_id' => 'nullable|string|max:255',
        ]);

        $canvaExportProfile->update($validated);

        return response()->json([
            'data' => $canvaExportProfile->load(['connectorConnection:id,name,connector_type', 'searchProfile:id,name']),
        ]);
    }

    /**
     * DELETE /canva-export-profiles/{profile} — Profil löschen.
     */
    public function destroy(CanvaExportProfile $canvaExportProfile): JsonResponse
    {
        $this->authorize('delete', $canvaExportProfile);

        $canvaExportProfile->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /canva-export-profiles/{profile}/execute — Profil ausführen (an Canva senden).
     */
    public function execute(Request $request, CanvaExportProfile $canvaExportProfile): JsonResponse
    {
        $this->authorize('view', $canvaExportProfile);

        $connection = $canvaExportProfile->connectorConnection;
        if (! $connection) {
            return response()->json(['message' => 'Keine Canva-Verbindung im Profil hinterlegt.'], 422);
        }

        $connector = $this->registry->get($connection->connector_type);
        if (! $connector) {
            return response()->json(['message' => 'Connector nicht gefunden.'], 422);
        }

        // Produkte ermitteln: explizite IDs oder via SearchProfile
        $productIds = $canvaExportProfile->product_ids ?? [];
        if (empty($productIds) && $canvaExportProfile->search_profile_id) {
            $productIds = Product::query()
                ->limit(50)
                ->pluck('id')
                ->toArray();
        }

        if (empty($productIds)) {
            return response()->json(['message' => 'Keine Produkte ausgewählt.'], 422);
        }

        $products = Product::whereIn('id', $productIds)->get()->all();

        $options = [
            'language' => ($canvaExportProfile->languages ?? ['de'])[0],
            'attributes' => $canvaExportProfile->attribute_ids,
            'include_prices' => $canvaExportProfile->include_prices,
            'include_media' => $canvaExportProfile->include_media,
            'brand_template_id' => $canvaExportProfile->brand_template_id,
        ];

        $results = $connector->pushProductDataBulk($connection, $products, $options);

        return response()->json(['data' => $results]);
    }
}
