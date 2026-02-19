<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Export\PublixxPqlRequest;
use App\Http\Requests\Api\V1\Export\PublixxWebhookRequest;
use App\Http\Resources\Api\V1\DatasetResource;
use App\Services\Export\PublixxDatasetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublixxDatasetController extends Controller
{
    public function __construct(
        protected PublixxDatasetService $publixxDatasetService
    ) {}

    /**
     * GET /api/v1/publixx/datasets/{mapping_id}
     *
     * Get all product datasets for a mapping.
     */
    public function index(Request $request, string $mappingId): JsonResponse
    {
        $result = $this->publixxDatasetService->getAllDatasets(
            $mappingId,
            $request->all()
        );

        return response()->json([
            'data' => DatasetResource::collection($result['data']),
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/v1/publixx/datasets/{mapping_id}/{product_id}
     *
     * Get a single product dataset for a mapping.
     */
    public function show(string $mappingId, string $productId): JsonResponse
    {
        $dataset = $this->publixxDatasetService->getDataset($mappingId, $productId);

        if ($dataset === null) {
            return response()->json([
                'type' => 'https://httpstatuses.com/404',
                'title' => 'Dataset not found',
                'status' => 404,
                'detail' => "No dataset found for mapping {$mappingId} and product {$productId}.",
            ], 404);
        }

        return response()->json([
            'data' => new DatasetResource($dataset),
        ]);
    }

    /**
     * POST /api/v1/publixx/datasets/{mapping_id}/pql
     *
     * Get datasets filtered by PQL query.
     */
    public function pql(PublixxPqlRequest $request, string $mappingId): JsonResponse
    {
        $result = $this->publixxDatasetService->getDatasetsByPql(
            $mappingId,
            $request->validated()['pql'],
            $request->validated()['params'] ?? []
        );

        return response()->json([
            'data' => DatasetResource::collection($result['data']),
            'meta' => $result['meta'],
        ]);
    }

    /**
     * POST /api/v1/publixx/webhook
     *
     * Handle incoming Publixx webhook.
     */
    public function webhook(PublixxWebhookRequest $request): JsonResponse
    {
        $result = $this->publixxDatasetService->handleWebhook($request->validated());

        return response()->json($result);
    }
}
