<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\PxfTemplate;
use App\Models\Product;
use App\Models\PublixxExportMapping;
use App\Services\Export\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PxfTemplateController extends Controller
{
    private const ALLOWED_INCLUDES = ['productType', 'exportMapping'];

    public function index(Request $request): JsonResponse
    {
        $query = PxfTemplate::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request, 'name', 'asc');

        $templates = $query->paginate($this->getPerPage($request));

        return response()->json([
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'pxf_data' => ['required', 'array'],
            'version' => ['nullable', 'string', 'max:50'],
            'orientation' => ['nullable', 'string', 'in:a4hoch,a4quer,custom'],
            'product_type_id' => ['nullable', 'uuid', 'exists:product_types,id'],
            'export_mapping_id' => ['nullable', 'uuid', 'exists:publixx_export_mappings,id'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $template = PxfTemplate::create($validated);

        return response()->json(['data' => $template], 201);
    }

    public function show(Request $request, PxfTemplate $pxfTemplate): JsonResponse
    {
        $pxfTemplate->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return response()->json(['data' => $pxfTemplate]);
    }

    public function update(Request $request, PxfTemplate $pxfTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'pxf_data' => ['sometimes', 'array'],
            'version' => ['nullable', 'string', 'max:50'],
            'orientation' => ['nullable', 'string', 'in:a4hoch,a4quer,custom'],
            'product_type_id' => ['nullable', 'uuid', 'exists:product_types,id'],
            'export_mapping_id' => ['nullable', 'uuid', 'exists:publixx_export_mappings,id'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $pxfTemplate->update($validated);

        return response()->json(['data' => $pxfTemplate->fresh()]);
    }

    public function destroy(PxfTemplate $pxfTemplate): JsonResponse
    {
        $pxfTemplate->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /pxf-templates/{pxfTemplate}/preview/{product}
     * Returns a fully data-injected PXF JSON for a specific product.
     */
    public function preview(PxfTemplate $pxfTemplate, Product $product, ExportService $exportService): JsonResponse
    {
        $pxfData = $pxfTemplate->pxf_data;

        if ($pxfTemplate->export_mapping_id) {
            $mapping = PublixxExportMapping::findOrFail($pxfTemplate->export_mapping_id);
            $dataset = $exportService->exportProduct($product, $mapping, ['skipCache' => true]);

            $pxfData['data'] = [$dataset];
            $pxfData['config']['assetBase'] = rtrim(config('app.url'), '/') . '/api/v1/media/file/';
        }

        return response()->json(['data' => $pxfData]);
    }

    /**
     * POST /pxf-templates/import — import a PXF file as a template.
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pxf_data' => ['required', 'array'],
        ]);

        $template = PxfTemplate::create([
            'name' => $validated['name'],
            'pxf_data' => $validated['pxf_data'],
            'is_active' => true,
        ]);

        return response()->json(['data' => $template], 201);
    }
}
