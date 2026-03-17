<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\TestDataGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestDataGeneratorController extends Controller
{
    public function __construct(
        private readonly TestDataGeneratorService $service,
    ) {
    }

    /**
     * POST /api/v1/admin/test-data/generate
     *
     * Generate dummy products based on existing attributes, product types, and hierarchies.
     *
     * Parameters:
     *   - count (int, default: 1000): Number of products to generate
     *   - with_prices (bool, default: true): Generate prices
     *   - category_count (int, default: 20): Number of categories
     *   - category_depth (int, default: 3): Depth of category tree
     */
    public function generate(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('Admin')) {
            return $this->errorResponse(
                'forbidden',
                'Forbidden',
                403,
                'Nur Administratoren dürfen Testdaten generieren.',
            );
        }

        $request->validate([
            'count' => 'integer|min:1|max:100000',
            'with_prices' => 'boolean',
            'category_count' => 'integer|min:1|max:200',
            'category_depth' => 'integer|min:1|max:10',
            'attributes_per_product' => 'nullable|integer|min:1|max:500',
        ]);

        $count = (int) $request->input('count', 1000);
        $withPrices = $request->boolean('with_prices', true);
        $categoryCount = (int) $request->input('category_count', 20);
        $categoryDepth = (int) $request->input('category_depth', 3);
        $attributesPerProduct = $request->filled('attributes_per_product')
            ? (int) $request->input('attributes_per_product')
            : null;

        try {
            $result = $this->service->generate(
                count: $count,
                withPrices: $withPrices,
                categoryCount: $categoryCount,
                categoryDepth: $categoryDepth,
                attributesPerProduct: $attributesPerProduct,
                userId: $request->user()->id,
            );

            Log::info('Test data generated via API', [
                'user' => $request->user()->email,
                'count' => $count,
                'result' => $result,
            ]);

            return $this->successResponse([
                'message' => sprintf(
                    '%d Testprodukte in %.2fs generiert.',
                    $result['products_created'],
                    $result['duration_seconds'],
                ),
                ...$result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Test data generation failed', [
                'user' => $request->user()->email,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'server_error',
                'Testdaten-Generierung fehlgeschlagen',
                500,
                $e->getMessage(),
            );
        }
    }

    /**
     * DELETE /api/v1/admin/test-data
     *
     * Delete all test products (SKU prefix TEST-) and the test hierarchy.
     */
    public function cleanup(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('Admin')) {
            return $this->errorResponse(
                'forbidden',
                'Forbidden',
                403,
                'Nur Administratoren dürfen Testdaten löschen.',
            );
        }

        try {
            $result = $this->service->cleanup();

            Log::info('Test data cleaned up via API', [
                'user' => $request->user()->email,
                'result' => $result,
            ]);

            return $this->successResponse([
                'message' => sprintf(
                    '%d Testprodukte in %.2fs gelöscht.',
                    $result['products_deleted'],
                    $result['duration_seconds'],
                ),
                ...$result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Test data cleanup failed', [
                'user' => $request->user()->email,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'server_error',
                'Testdaten-Löschung fehlgeschlagen',
                500,
                $e->getMessage(),
            );
        }
    }

    /**
     * GET /api/v1/admin/test-data/stats
     *
     * Get statistics about existing test data.
     */
    public function stats(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('Admin')) {
            return $this->errorResponse(
                'forbidden',
                'Forbidden',
                403,
                'Nur Administratoren dürfen Testdaten-Statistiken einsehen.',
            );
        }

        return $this->successResponse($this->service->stats());
    }

    /**
     * GET /api/v1/admin/test-data/progress
     */
    public function progress(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('Admin')) {
            return $this->errorResponse('forbidden', 'Forbidden', 403);
        }

        $progress = $this->service->progress();

        return $this->successResponse($progress ?? ['phase' => null, 'current' => 0, 'total' => 0, 'percent' => 0]);
    }

    /**
     * POST /api/v1/admin/test-data/cancel
     */
    public function cancel(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('Admin')) {
            return $this->errorResponse('forbidden', 'Forbidden', 403);
        }

        $this->service->cancel();

        return $this->successResponse(['message' => 'Abbruch angefordert.']);
    }
}
