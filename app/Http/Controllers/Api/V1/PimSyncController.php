<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\ProductType;
use App\Services\PimSync\PimSyncExportService;
use App\Services\PimSync\PimSyncImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PimSyncController extends Controller
{
    public function __construct(
        private readonly PimSyncExportService $exportService,
        private readonly PimSyncImportService $importService,
    ) {}

    /**
     * Produkte exponieren (paginiert, mit Filtern).
     *
     * GET /api/v1/pim-sync/products
     */
    public function products(Request $request): JsonResponse
    {
        $request->validate([
            'hierarchy_id'       => 'sometimes|nullable|uuid',
            'product_type_ids'   => 'sometimes|array',
            'product_type_ids.*' => 'uuid',
            'skus'               => 'sometimes|array',
            'skus.*'             => 'string|max:100',
            'updated_since'      => 'sometimes|nullable|date',
            'per_page'           => 'sometimes|integer|min:1|max:500',
            'page'               => 'sometimes|integer|min:1',
        ]);

        $filters = [
            'hierarchy_id'     => $request->input('hierarchy_id'),
            'product_type_ids' => $request->input('product_type_ids', []),
            'skus'             => $request->input('skus', []),
            'updated_since'    => $request->input('updated_since'),
            'per_page'         => (int) $request->input('per_page', 100),
            'page'             => (int) $request->input('page', 1),
        ];

        $result = $this->exportService->exportProducts($filters);

        return response()->json($result);
    }

    /**
     * Einzelnes Produkt per SKU.
     *
     * GET /api/v1/pim-sync/products/{sku}
     */
    public function product(string $sku): JsonResponse
    {
        $product = $this->exportService->exportProductBySku($sku);

        if (! $product) {
            return response()->json(['error' => 'not_found', 'message' => "Produkt {$sku} nicht gefunden."], 404);
        }

        return response()->json(['data' => $product]);
    }

    /**
     * Produkte empfangen (Batch-Import).
     *
     * POST /api/v1/pim-sync/products
     */
    public function receiveProducts(Request $request): JsonResponse
    {
        $request->validate([
            'products'              => 'required|array|min:1|max:500',
            'products.*.sku'        => 'required|string',
            'conflict_resolution'   => 'sometimes|string|in:remote_wins,local_wins,newer_wins',
            'sync_attributes'       => 'sometimes|boolean',
            'sync_prices'           => 'sometimes|boolean',
            'sync_media'            => 'sometimes|boolean',
        ]);

        $stats = $this->importService->importProducts(
            $request->input('products'),
            [
                'conflict_resolution' => $request->input('conflict_resolution', 'remote_wins'),
                'sync_attributes'     => $request->boolean('sync_attributes', true),
                'sync_prices'         => $request->boolean('sync_prices', true),
                'sync_media'          => $request->boolean('sync_media', true),
            ],
        );

        return response()->json([
            'message' => 'Import abgeschlossen.',
            'stats'   => $stats,
        ]);
    }

    /**
     * Produkt-Checksums für Delta-Sync.
     *
     * GET /api/v1/pim-sync/checksums
     */
    public function checksums(Request $request): JsonResponse
    {
        $request->validate([
            'hierarchy_id'       => 'sometimes|nullable|uuid',
            'product_type_ids'   => 'sometimes|array',
            'product_type_ids.*' => 'uuid',
            'skus'               => 'sometimes|array',
            'skus.*'             => 'string|max:100',
        ]);

        $filters = [
            'hierarchy_id'     => $request->input('hierarchy_id'),
            'product_type_ids' => $request->input('product_type_ids', []),
            'skus'             => $request->input('skus', []),
        ];

        $checksums = $this->exportService->calculateChecksums($filters);

        return response()->json([
            'checksums' => $checksums,
            'total'     => count($checksums),
        ]);
    }

    /**
     * Kategorien/Hierarchie-Knoten exponieren.
     *
     * GET /api/v1/pim-sync/categories
     */
    public function categories(Request $request): JsonResponse
    {
        $hierarchyId = $request->input('hierarchy_id');
        $perPage = min((int) $request->input('per_page', 500), 1000);

        $query = HierarchyNode::with('hierarchy')
            ->orderBy('depth')
            ->orderBy('sort_order');

        if ($hierarchyId) {
            $query->where('hierarchy_id', $hierarchyId);
        }

        $paginator = $query->paginate($perPage);

        $nodes = $paginator->getCollection()->map(fn (HierarchyNode $node) => [
            'id'             => $node->id,
            'hierarchy_name' => $node->hierarchy?->name,
            'parent_node_id' => $node->parent_node_id,
            'name_de'        => $node->name_de,
            'name_en'        => $node->name_en,
            'path'           => $node->path,
            'depth'          => $node->depth,
            'sort_order'     => $node->sort_order,
        ]);

        return response()->json([
            'data' => $nodes,
            'meta' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Kategorien empfangen (aktuell Placeholder).
     *
     * POST /api/v1/pim-sync/categories
     */
    public function receiveCategories(Request $request): JsonResponse
    {
        // Phase 2: Kategorien-Import implementieren
        return response()->json([
            'message' => 'Kategorien-Import wird in einer späteren Version unterstützt.',
        ], 501);
    }

    /**
     * Media-Zuordnungen exponieren.
     *
     * GET /api/v1/pim-sync/media
     */
    public function media(Request $request): JsonResponse
    {
        $sku = $request->input('sku');

        if (! $sku) {
            return response()->json([
                'error'   => 'missing_parameter',
                'message' => 'Parameter "sku" ist erforderlich.',
            ], 422);
        }

        $product = $this->exportService->exportProductBySku($sku);

        if (! $product) {
            return response()->json(['error' => 'not_found', 'message' => "Produkt {$sku} nicht gefunden."], 404);
        }

        return response()->json(['data' => $product['media'] ?? []]);
    }

    /**
     * Media-Datei empfangen (Gegenstück zu AnyPimMediaService::pushMedia()).
     *
     * POST /api/v1/pim-sync/media
     */
    public function receiveMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file'        => ['required', 'file', 'max:204800'], // 200 MB, wie StoreMediaRequest
            'file_name'   => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'mime_type'   => 'sometimes|nullable|string|max:100',
            'title_de'    => 'sometimes|nullable|string|max:255',
            'title_en'    => 'sometimes|nullable|string|max:255',
            'alt_text_de' => 'sometimes|nullable|string|max:255',
            'alt_text_en' => 'sometimes|nullable|string|max:255',
        ]);

        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        if (! in_array($extension, \App\Support\Media\MediaFileTypes::ALLOWED_EXTENSIONS, true)) {
            return response()->json(['message' => "Dateityp \".{$extension}\" ist nicht erlaubt."], 422);
        }

        $media = $this->importService->receiveMediaFile($request->file('file'), $validated);

        return response()->json([
            'message' => 'Media importiert.',
            'data'    => ['id' => $media->id, 'file_name' => $media->file_name],
        ]);
    }

    /**
     * Attribut-Definitionen exponieren (Schema-Sync).
     *
     * GET /api/v1/pim-sync/attributes
     */
    public function attributes(): JsonResponse
    {
        $attributes = $this->exportService->exportAttributes();

        return response()->json([
            'data'  => $attributes,
            'total' => count($attributes),
        ]);
    }

    /**
     * Schema-Info der Instanz (Attribute, Typen, Hierarchien).
     *
     * GET /api/v1/pim-sync/schema
     */
    public function schema(): JsonResponse
    {
        return response()->json([
            'product_types' => ProductType::orderBy('name_de')
                ->get(['id', 'name_de', 'name_en', 'technical_name'])
                ->toArray(),
            'hierarchies' => Hierarchy::orderBy('name_de')
                ->get(['id', 'name_de', 'name_en', 'technical_name', 'hierarchy_type'])
                ->toArray(),
            'attribute_count' => Attribute::count(),
        ]);
    }
}
