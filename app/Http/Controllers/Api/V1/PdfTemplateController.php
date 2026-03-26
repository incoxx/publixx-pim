<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Attribute;
use App\Models\PdfTemplate;
use App\Models\Product;
use App\Services\PdfTemplate\PdfTemplateRenderer;
use App\Services\PdfTemplate\PdfTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PdfTemplateController extends Controller
{
    public function __construct(
        private readonly PdfTemplateService $pdfTemplateService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $templates = PdfTemplate::query()
            ->when($userId, fn ($q) => $q->visibleTo($userId))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string|nullable',
            'template_json' => 'required|array',
            'page_orientation' => 'sometimes|string|in:portrait,landscape',
            'page_size' => 'sometimes|string|max:20',
            'is_shared' => 'sometimes|boolean',
        ]);

        $validated['user_id'] = $request->user()?->id;

        $template = PdfTemplate::create($validated);

        return response()->json(['data' => $template], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $template = PdfTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        return response()->json(['data' => $template]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $template = PdfTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'template_json' => 'sometimes|array',
            'page_orientation' => 'sometimes|string|in:portrait,landscape',
            'page_size' => 'sometimes|string|max:20',
            'is_shared' => 'sometimes|boolean',
        ]);

        $template->update($validated);

        return response()->json(['data' => $template->fresh()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $template = PdfTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $template->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /api/v1/pdf-templates/fields — Available fields for the designer palette.
     */
    public function fields(): JsonResponse
    {
        $baseFields = [
            ['field' => 'sku', 'label_de' => 'Artikelnummer', 'label_en' => 'SKU', 'category' => 'base'],
            ['field' => 'name', 'label_de' => 'Produktname', 'label_en' => 'Product Name', 'category' => 'base'],
            ['field' => 'ean', 'label_de' => 'EAN', 'label_en' => 'EAN', 'category' => 'base'],
            ['field' => 'status', 'label_de' => 'Status', 'label_en' => 'Status', 'category' => 'base'],
            ['field' => 'product_type', 'label_de' => 'Produkttyp', 'label_en' => 'Product Type', 'category' => 'base'],
            ['field' => 'hierarchy_node', 'label_de' => 'Kategorie', 'label_en' => 'Category', 'category' => 'base'],
            ['field' => 'created_at', 'label_de' => 'Erstellt am', 'label_en' => 'Created at', 'category' => 'base'],
            ['field' => 'updated_at', 'label_de' => 'Geändert am', 'label_en' => 'Updated at', 'category' => 'base'],
        ];

        $attributes = Attribute::query()
            ->select(['id', 'technical_name', 'name_de', 'name_en', 'data_type', 'attribute_type_id'])
            ->with('attributeType:id,name_de,name_en')
            ->orderBy('name_de')
            ->get()
            ->map(fn ($attr) => [
                'attributeId' => $attr->id,
                'technical_name' => $attr->technical_name,
                'label_de' => $attr->name_de,
                'label_en' => $attr->name_en,
                'data_type' => $attr->data_type,
                'category' => 'attribute',
                'group_de' => $attr->attributeType?->name_de,
                'group_en' => $attr->attributeType?->name_en,
            ]);

        $layoutElements = [
            ['type' => 'text', 'label_de' => 'Statischer Text', 'label_en' => 'Static Text', 'category' => 'layout'],
            ['type' => 'image', 'label_de' => 'Produktbild', 'label_en' => 'Product Image', 'category' => 'layout'],
            ['type' => 'shape', 'label_de' => 'Form / Rahmen', 'label_en' => 'Shape / Box', 'category' => 'layout'],
            ['type' => 'variant_table', 'label_de' => 'Variantentabelle', 'label_en' => 'Variant Table', 'category' => 'layout'],
            ['type' => 'relation_table', 'label_de' => 'Beziehungstabelle', 'label_en' => 'Relations Table', 'category' => 'layout'],
            ['type' => 'attribute_table', 'label_de' => 'Attribut-Tabelle', 'label_en' => 'Attribute Table', 'category' => 'layout'],
            ['type' => 'smart_table', 'label_de' => 'Smart Table', 'label_en' => 'Smart Table', 'category' => 'layout'],
        ];

        return response()->json([
            'data' => [
                'base_fields' => $baseFields,
                'attributes' => $attributes,
                'layout_elements' => $layoutElements,
            ],
        ]);
    }

    /**
     * POST /api/v1/pdf-templates/{id}/resolve-preview — Resolve elements for canvas preview.
     */
    public function resolvePreview(Request $request, string $id): JsonResponse
    {
        $template = PdfTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $validated = $request->validate([
            'product_id' => 'required|string|exists:products,id',
            'language' => 'sometimes|string|max:5',
        ]);

        $language = $validated['language'] ?? 'de';
        $product = Product::findOrFail($validated['product_id']);
        $product->loadMissing($this->pdfTemplateService->getRelations($template->template_json));

        $renderer = app(PdfTemplateRenderer::class);
        $elements = $renderer->resolveElements($template->template_json, $product, $language);

        return response()->json(['data' => $elements]);
    }

    /**
     * POST /api/v1/pdf-templates/{id}/preview — Preview with a single product.
     */
    public function preview(Request $request, string $id)
    {
        $template = PdfTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $validated = $request->validate([
            'product_id' => 'sometimes|string|exists:products,id',
            'language' => 'sometimes|string|max:5',
        ]);

        $language = $validated['language'] ?? 'de';

        $product = isset($validated['product_id'])
            ? Product::findOrFail($validated['product_id'])
            : Product::where('status', 'active')->inRandomOrder()->first();

        if (!$product) {
            return response()->json(['error' => 'Kein Produkt für die Vorschau gefunden.'], 404);
        }

        $pdfContent = $this->pdfTemplateService->generateForProduct($template, $product, $language);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vorschau.pdf"',
        ]);
    }

    /**
     * POST /api/v1/pdf-templates/{id}/execute — Generate PDF(s) for given products.
     */
    public function execute(Request $request, string $id)
    {
        $template = PdfTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $validated = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'string|exists:products,id',
            'mode' => 'sometimes|string|in:combined,zip',
            'format' => 'sometimes|string|in:pdf,docx,indesign',
            'language' => 'sometimes|string|max:5',
        ]);

        $language = $validated['language'] ?? 'de';
        $mode = $validated['mode'] ?? 'combined';
        $format = $validated['format'] ?? 'pdf';
        $products = Product::whereIn('id', $validated['product_ids'])->get();

        if ($products->isEmpty()) {
            return response()->json(['error' => 'Keine Produkte gefunden.'], 404);
        }

        if ($format === 'indesign') {
            $result = $this->pdfTemplateService->generateInDesignForProducts($template, $products, $language);
        } elseif ($format === 'docx') {
            $result = $this->pdfTemplateService->generateDocxForProducts($template, $products, $mode, $language);
        } else {
            $result = $this->pdfTemplateService->generateForProducts($template, $products, $mode, $language);
        }

        return response()->streamDownload(function () use ($result) {
            readfile($result['path']);
            // Cleanup
            @unlink($result['path']);
            if (!empty($result['temp_dir'])) {
                @array_map('unlink', glob($result['temp_dir'] . '/*'));
                @rmdir($result['temp_dir']);
            }
        }, $result['filename'], [
            'Content-Type' => $result['content_type'],
        ]);
    }

    private function authorizeAccess(Request $request, PdfTemplate $template): void
    {
        $userId = $request->user()?->id;
        if (!$template->is_shared && $template->user_id && $userId !== $template->user_id) {
            abort(403, 'Kein Zugriff auf dieses PDF-Template.');
        }
    }
}
