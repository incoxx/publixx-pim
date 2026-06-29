<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductWidgetRequest;
use App\Http\Requests\Api\V1\UpdateProductWidgetRequest;
use App\Http\Resources\Api\V1\ProductWidgetResource;
use App\Http\Traits\Filterable;
use App\Models\ProductWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductWidgetController extends Controller
{
    use Filterable;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductWidget::class);

        $query = ProductWidget::query();
        $this->applyFilters($query, array_intersect_key(
            $request->query('filter', []),
            array_flip(['is_active'])
        ));
        $this->applySorting($query, $request, 'sort_order', 'asc');

        return ProductWidgetResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreProductWidgetRequest $request): JsonResponse
    {
        $this->authorize('create', ProductWidget::class);

        $widget = ProductWidget::create($request->validated());

        return (new ProductWidgetResource($widget))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProductWidget $productWidget): ProductWidgetResource
    {
        $this->authorize('view', $productWidget);

        return new ProductWidgetResource($productWidget);
    }

    public function update(UpdateProductWidgetRequest $request, ProductWidget $productWidget): ProductWidgetResource
    {
        $this->authorize('update', $productWidget);

        $productWidget->update($request->validated());

        return new ProductWidgetResource($productWidget->fresh());
    }

    public function destroy(ProductWidget $productWidget): JsonResponse
    {
        $this->authorize('delete', $productWidget);

        if ($productWidget->is_system) {
            return response()->json([
                'message' => 'System-Widgets können nicht gelöscht werden.',
            ], 422);
        }

        $productWidget->delete();

        return response()->json(null, 204);
    }
}
