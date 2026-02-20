<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Export\ExportProductsRequest;
use App\Http\Requests\Api\V1\Export\ExportBulkRequest;
use App\Http\Requests\Api\V1\Export\ExportQueryRequest;
use App\Http\Resources\Api\V1\DatasetResource;
use App\Models\Product;
use App\Models\PublixxExportMapping;
use App\Services\Export\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exportService
    ) {}

    /**
     * GET /api/v1/export/products
     *
     * Export products as JSON array with filter support.
     */
    public function index(ExportProductsRequest $request): JsonResponse
    {
        Gate::authorize('export.view');

        $filters = $request->validated()['filter'] ?? [];
        $mappingId = $request->input('mapping_id');
        $mapping = $mappingId ? PublixxExportMapping::find($mappingId) : null;

        $options = [
            'perPage' => $request->integer('per_page', 50),
        ];

        $result = $this->exportService->exportFiltered($filters, $mapping, $options);

        return response()->json($result);
    }

    /**
     * GET /api/v1/export/products/{id}
     *
     * Export a single product.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        Gate::authorize('export.view');

        $product = Product::findOrFail($id);
        $mappingId = $request->input('mapping_id');
        $mapping = $mappingId ? PublixxExportMapping::find($mappingId) : null;

        if (!$mapping) {
            $mapping = PublixxExportMapping::first();
        }

        if (!$mapping) {
            return response()->json([
                'type' => 'https://httpstatuses.com/422',
                'title' => 'No export mapping configured',
                'status' => 422,
                'detail' => 'At least one PublixxExportMapping must exist to export products.',
            ], 422);
        }

        $dataset = $this->exportService->exportProduct($product, $mapping);

        return response()->json([
            'data' => new DatasetResource($dataset),
        ]);
    }

    /**
     * POST /api/v1/export/products/bulk
     *
     * Bulk export products by filter criteria.
     */
    public function bulk(ExportBulkRequest $request): JsonResponse
    {
        Gate::authorize('export.execute');

        $filters = $request->validated()['filter'] ?? [];
        $mappingId = $request->input('mapping_id');
        $mapping = $mappingId ? PublixxExportMapping::find($mappingId) : null;

        $datasets = $this->exportService->exportBulk($filters, $mapping);

        return response()->json([
            'data' => DatasetResource::collection($datasets),
            'meta' => [
                'total' => count($datasets),
            ],
        ]);
    }

    /**
     * GET /api/v1/export/products/{id}/publixx
     *
     * Export a single product in Publixx PXF-Dataset format.
     */
    public function publixx(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        // For publixx format, use mapping with flatten_mode=publixx or first available
        $mapping = PublixxExportMapping::where('flatten_mode', 'publixx')->first()
            ?? PublixxExportMapping::first();

        if (!$mapping) {
            return response()->json([
                'type' => 'https://httpstatuses.com/422',
                'title' => 'No Publixx export mapping configured',
                'status' => 422,
                'detail' => 'A PublixxExportMapping with flatten_mode=publixx is required.',
            ], 422);
        }

        $dataset = $this->exportService->exportProduct($product, $mapping);

        return response()->json([
            'data' => new DatasetResource($dataset),
        ]);
    }

    /**
     * POST /api/v1/export/query
     *
     * Export products filtered by PQL query.
     */
    public function query(ExportQueryRequest $request): JsonResponse
    {
        $pqlQuery = $request->validated()['pql'];
        $mappingId = $request->input('mapping_id');
        $mapping = $mappingId ? PublixxExportMapping::find($mappingId) : null;

        if (!$mapping) {
            $mapping = PublixxExportMapping::first();
        }

        if (!$mapping) {
            return response()->json([
                'type' => 'https://httpstatuses.com/422',
                'title' => 'No export mapping configured',
                'status' => 422,
                'detail' => 'At least one PublixxExportMapping must exist.',
            ], 422);
        }

        // Delegate to PQL executor via ExportService
        $app = app();
        if ($app->bound(\App\Services\Pql\PqlExecutor::class)) {
            $executor = $app->make(\App\Services\Pql\PqlExecutor::class);
            $products = $executor->execute($pqlQuery);
            $datasets = [];

            foreach ($products as $product) {
                $datasets[] = $this->exportService->exportProduct($product, $mapping);
            }

            return response()->json([
                'data' => DatasetResource::collection($datasets),
                'meta' => [
                    'pql' => $pqlQuery,
                    'total' => count($datasets),
                ],
            ]);
        }

        return response()->json([
            'type' => 'https://httpstatuses.com/503',
            'title' => 'PQL Engine unavailable',
            'status' => 503,
            'detail' => 'The PQL engine (Agent 5) is not yet available.',
        ], 503);
    }
}
