<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Services\Preview\ProductCompletenessService;
use App\Services\Preview\ProductPreviewService;
use App\Services\ProductVersioningService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    private const ALLOWED_INCLUDES = [
        'productType', 'attributeValues', 'variants', 'media',
        'prices', 'relations', 'parentProduct', 'masterHierarchyNode',
    ];

    private const ALLOWED_FILTERS = [
        'status', 'product_type_id', 'product_type_ref',
        'master_hierarchy_node_id',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $languages = $this->getRequestedLanguages($request);

        $query = Product::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        // If attributeValues are included, filter by language
        if (in_array('attributeValues', $this->parseIncludes($request, self::ALLOWED_INCLUDES))) {
            $this->constrainAttributeValuesForLanguages($query, $languages);
        }

        $filters = array_intersect_key(
            $request->query('filter', []),
            array_flip(self::ALLOWED_FILTERS)
        );

        // By default, exclude variants from the main product listing
        if (!isset($filters['product_type_ref'])) {
            $query->where('product_type_ref', 'product');
        }

        $this->applyFilters($query, $filters);
        $this->applySearch($query, $request, ['name', 'sku', 'ean']);
        $this->applySorting($query, $request, 'created_at', 'desc');

        return ProductResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $product = Product::create($data);

        try {
            event(new \App\Events\ProductCreated($product));
        } catch (\Throwable $e) {
            Log::warning('ProductCreated event failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
        }

        return (new ProductResource($product->load('productType')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /products/{product}/duplicate
     *
     * Deep-copy a product with selectable parts.
     */
    public function duplicate(Request $request, Product $product): JsonResponse
    {
        $this->authorize('create', Product::class);

        $includeAttributes = $request->boolean('include_attributes', true);
        $includePrices = $request->boolean('include_prices', true);
        $includeMedia = $request->boolean('include_media', true);
        $includeRelations = $request->boolean('include_relations', true);

        $newProduct = null;

        DB::transaction(function () use (
            $product, $request, $includeAttributes, $includePrices, $includeMedia, $includeRelations, &$newProduct
        ) {
            // Generate unique SKU
            $baseSku = $product->sku . '-copy';
            $sku = $baseSku;
            $suffix = 1;
            while (Product::where('sku', $sku)->exists()) {
                $sku = $baseSku . '-' . $suffix;
                $suffix++;
            }

            // Create new product
            $newProduct = Product::create([
                'sku' => $sku,
                'name' => $product->name . ' (Kopie)',
                'ean' => $product->ean,
                'status' => 'draft',
                'product_type_id' => $product->product_type_id,
                'product_type_ref' => 'product',
                'master_hierarchy_node_id' => $product->master_hierarchy_node_id,
                'created_by' => $request->user()?->id,
            ]);

            // Copy attribute values
            if ($includeAttributes) {
                foreach ($product->attributeValues as $av) {
                    $newAv = $av->replicate();
                    $newAv->product_id = $newProduct->id;
                    $newAv->save();
                }
            }

            // Copy prices
            if ($includePrices) {
                foreach ($product->prices as $price) {
                    $newPrice = $price->replicate();
                    $newPrice->product_id = $newProduct->id;
                    $newPrice->save();
                }
            }

            // Copy media assignments
            if ($includeMedia) {
                foreach ($product->mediaAssignments as $ma) {
                    $newMa = $ma->replicate();
                    $newMa->product_id = $newProduct->id;
                    $newMa->save();
                }
            }

            // Copy outgoing relations
            if ($includeRelations) {
                foreach ($product->outgoingRelations as $rel) {
                    $newRel = $rel->replicate();
                    $newRel->source_product_id = $newProduct->id;
                    $newRel->save();
                }
            }
        });

        try {
            event(new \App\Events\ProductCreated($newProduct));
        } catch (\Throwable $e) {
            Log::warning('ProductCreated event failed for duplicate', ['product_id' => $newProduct->id, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Product duplicated successfully.',
            'product' => new ProductResource($newProduct->load('productType')),
        ], 201);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $this->authorize('view', $product);

        $languages = $this->getRequestedLanguages($request);
        $includes = $this->parseIncludes($request, self::ALLOWED_INCLUDES);

        // Build eager loading with language constraint for attribute values
        $eagerLoads = [];
        foreach ($includes as $include) {
            if ($include === 'attributeValues') {
                $eagerLoads['attributeValues'] = function ($q) use ($languages) {
                    $q->where(function ($sub) use ($languages) {
                        $sub->whereNull('language')
                            ->orWhereIn('language', $languages);
                    });
                };
            } else {
                $eagerLoads[] = $include;
            }
        }

        $product->load($eagerLoads);

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        // Auto-create version snapshot before applying changes
        try {
            app(ProductVersioningService::class)->createVersion(
                $product,
                null,
                $request->user()?->id,
            );
        } catch (\Throwable) {
            // Don't break the update if versioning fails
        }

        $data = $request->validated();
        $data['updated_by'] = $request->user()?->id;

        $product->update($data);

        try {
            event(new \App\Events\ProductUpdated($product));
        } catch (\Throwable $e) {
            Log::warning('ProductUpdated event failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
        }

        return new ProductResource($product->fresh());
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $productId = $product->id;
        $product->delete();

        try {
            event(new \App\Events\ProductDeleted($productId));
        } catch (\Throwable $e) {
            Log::warning('ProductDeleted event failed', ['product_id' => $productId, 'error' => $e->getMessage()]);
        }

        return response()->json(null, 204);
    }

    /**
     * GET /products/compare?ids=uuid1,uuid2
     *
     * Compare 2 products across ALL attributes, highlighting differences.
     */
    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|string',
        ]);

        $ids = explode(',', $request->query('ids'));
        if (count($ids) !== 2) {
            return response()->json(['message' => 'Exactly 2 product IDs required.'], 422);
        }

        $products = Product::with('productType')->whereIn('id', $ids)->get();
        if ($products->count() !== 2) {
            return response()->json(['message' => 'One or both products not found.'], 404);
        }

        $language = $this->getPrimaryLanguage($request);
        $productA = $products->firstWhere('id', $ids[0]);
        $productB = $products->firstWhere('id', $ids[1]);

        // Load ALL attribute values for both products
        $valsA = $productA->attributeValues()
            ->with('attribute')
            ->where(function ($q) use ($language) {
                $q->whereNull('language')->orWhere('language', $language);
            })
            ->get();

        $valsB = $productB->attributeValues()
            ->with('attribute')
            ->where(function ($q) use ($language) {
                $q->whereNull('language')->orWhere('language', $language);
            })
            ->get();

        // Build maps: attribute_id -> display value
        $mapA = [];
        foreach ($valsA as $v) {
            $value = $v->value_string ?? $v->value_number ?? $v->value_date ?? $v->value_flag ?? $v->value_selection_id;
            $mapA[$v->attribute_id] = [
                'value' => $value,
                'attribute_name' => $v->attribute?->name_de ?? $v->attribute?->technical_name ?? 'Unknown',
                'technical_name' => $v->attribute?->technical_name ?? '',
                'data_type' => $v->attribute?->data_type ?? '',
                'language' => $v->language,
            ];
        }

        $mapB = [];
        foreach ($valsB as $v) {
            $value = $v->value_string ?? $v->value_number ?? $v->value_date ?? $v->value_flag ?? $v->value_selection_id;
            $mapB[$v->attribute_id] = [
                'value' => $value,
                'attribute_name' => $v->attribute?->name_de ?? $v->attribute?->technical_name ?? 'Unknown',
                'technical_name' => $v->attribute?->technical_name ?? '',
                'data_type' => $v->attribute?->data_type ?? '',
                'language' => $v->language,
            ];
        }

        // Merge all attribute IDs
        $allAttrIds = array_unique(array_merge(array_keys($mapA), array_keys($mapB)));

        // Build base field comparisons
        $rows = [];

        // Compare base fields first
        $baseFields = [
            ['field' => 'sku', 'label' => 'SKU'],
            ['field' => 'name', 'label' => 'Name'],
            ['field' => 'ean', 'label' => 'EAN'],
            ['field' => 'status', 'label' => 'Status'],
            ['field' => 'product_type_ref', 'label' => 'Typ'],
        ];

        foreach ($baseFields as $bf) {
            $valA = $productA->{$bf['field']};
            $valB = $productB->{$bf['field']};
            $rows[] = [
                'attribute_name' => $bf['label'],
                'technical_name' => $bf['field'],
                'data_type' => 'base',
                'value_a' => $valA,
                'value_b' => $valB,
                'is_different' => (string) $valA !== (string) $valB,
            ];
        }

        // Compare attribute values
        foreach ($allAttrIds as $attrId) {
            $a = $mapA[$attrId] ?? null;
            $b = $mapB[$attrId] ?? null;
            $name = $a['attribute_name'] ?? $b['attribute_name'] ?? 'Unknown';
            $techName = $a['technical_name'] ?? $b['technical_name'] ?? '';
            $dataType = $a['data_type'] ?? $b['data_type'] ?? '';
            $valA = $a['value'] ?? null;
            $valB = $b['value'] ?? null;

            $rows[] = [
                'attribute_id' => $attrId,
                'attribute_name' => $name,
                'technical_name' => $techName,
                'data_type' => $dataType,
                'value_a' => $valA,
                'value_b' => $valB,
                'is_different' => (string) $valA !== (string) $valB,
            ];
        }

        return response()->json([
            'data' => [
                'product_a' => [
                    'id' => $productA->id,
                    'sku' => $productA->sku,
                    'name' => $productA->name,
                ],
                'product_b' => [
                    'id' => $productB->id,
                    'sku' => $productB->sku,
                    'name' => $productB->name,
                ],
                'rows' => $rows,
                'total_differences' => collect($rows)->where('is_different', true)->count(),
                'total_attributes' => count($rows),
            ],
        ]);
    }

    /**
     * GET /products/{product}/preview
     *
     * Generic product preview — all data in structured sections.
     */
    public function preview(Request $request, Product $product, ProductPreviewService $previewService): JsonResponse
    {
        $this->authorize('view', $product);

        $lang = $this->getPrimaryLanguage($request);
        $data = $previewService->buildPreviewData($product, $lang);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /products/{product}/preview/export.xlsx
     *
     * Export product preview as Excel file (single sheet, sections stacked).
     */
    public function previewExportExcel(Request $request, Product $product, ProductPreviewService $previewService): StreamedResponse
    {
        $this->authorize('view', $product);

        $lang = $this->getPrimaryLanguage($request);
        $data = $previewService->buildPreviewData($product, $lang);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($lang === 'en' ? 'Product Preview' : 'Produkt-Vorschau');

        $row = 1;

        // Styling constants
        $sectionHeaderStyle = [
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];
        $fieldLabelStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
        ];

        // --- Stammdaten ---
        $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Master Data' : 'Stammdaten');
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($sectionHeaderStyle);
        $row++;

        $stamm = $data['stammdaten'];
        $stammFields = [
            ['SKU', $stamm['sku']],
            ['EAN', $stamm['ean']],
            ['Name', $stamm['name']],
            ['Status', $stamm['status']],
            [$lang === 'en' ? 'Product Type' : 'Produkttyp', $stamm['product_type']['name'] ?? '-'],
            [$lang === 'en' ? 'Category' : 'Kategorie', implode(' > ', array_column($stamm['category_breadcrumb'], 'name'))],
            [$lang === 'en' ? 'Created' : 'Erstellt', $stamm['created_at']],
            [$lang === 'en' ? 'Updated' : 'Aktualisiert', $stamm['updated_at']],
        ];

        foreach ($stammFields as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}")->applyFromArray($fieldLabelStyle);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }

        $row++; // spacer

        // --- Attribute Sections ---
        foreach ($data['attribute_sections'] as $section) {
            $sheet->setCellValue("A{$row}", $section['section_name']);
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($sectionHeaderStyle);
            $row++;

            // Column headers
            $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Attribute' : 'Attribut');
            $sheet->setCellValue("B{$row}", $lang === 'en' ? 'Value' : 'Wert');
            $sheet->setCellValue("C{$row}", $lang === 'en' ? 'Unit' : 'Einheit');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($fieldLabelStyle);
            $row++;

            foreach ($section['attributes'] as $attr) {
                $sheet->setCellValue("A{$row}", $attr['label']);
                $sheet->setCellValue("B{$row}", $attr['display_value'] ?? '-');
                $sheet->setCellValue("C{$row}", $attr['unit'] ?? '');
                $row++;
            }

            $row++; // spacer
        }

        // --- Relations ---
        if (!empty($data['relations'])) {
            $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Relations' : 'Beziehungen');
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($sectionHeaderStyle);
            $row++;

            $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Type' : 'Typ');
            $sheet->setCellValue("B{$row}", $lang === 'en' ? 'Target Product' : 'Zielprodukt');
            $sheet->setCellValue("C{$row}", 'SKU');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($fieldLabelStyle);
            $row++;

            foreach ($data['relations'] as $rel) {
                $sheet->setCellValue("A{$row}", $rel['relation_type'] ?? '-');
                $sheet->setCellValue("B{$row}", $rel['target_product']['name'] ?? '-');
                $sheet->setCellValue("C{$row}", $rel['target_product']['sku'] ?? '-');
                $row++;
            }

            $row++;
        }

        // --- Prices ---
        if (!empty($data['prices'])) {
            $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Prices' : 'Preise');
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($sectionHeaderStyle);
            $row++;

            $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Price Type' : 'Preistyp');
            $sheet->setCellValue("B{$row}", $lang === 'en' ? 'Amount' : 'Betrag');
            $sheet->setCellValue("C{$row}", $lang === 'en' ? 'Currency' : 'Währung');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($fieldLabelStyle);
            $row++;

            foreach ($data['prices'] as $price) {
                $sheet->setCellValue("A{$row}", $price['price_type'] ?? '-');
                $sheet->setCellValue("B{$row}", $price['amount']);
                $sheet->setCellValue("C{$row}", $price['currency']);
                $row++;
            }

            $row++;
        }

        // --- Variants ---
        if (!empty($data['variants'])) {
            $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Variants' : 'Varianten');
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($sectionHeaderStyle);
            $row++;

            $sheet->setCellValue("A{$row}", 'SKU');
            $sheet->setCellValue("B{$row}", 'Name');
            $sheet->setCellValue("C{$row}", 'Status');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($fieldLabelStyle);
            $row++;

            foreach ($data['variants'] as $variant) {
                $sheet->setCellValue("A{$row}", $variant['sku'] ?? '-');
                $sheet->setCellValue("B{$row}", $variant['name'] ?? '-');
                $sheet->setCellValue("C{$row}", $variant['status'] ?? '-');
                $row++;
            }
        }

        // Auto-size columns
        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'product-preview-' . ($stamm['sku'] ?? $product->id) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * GET /products/{product}/preview/export.pdf
     *
     * Export product preview as PDF.
     */
    public function previewExportPdf(Request $request, Product $product, ProductPreviewService $previewService): \Illuminate\Http\Response
    {
        $this->authorize('view', $product);

        $lang = $this->getPrimaryLanguage($request);
        $data = $previewService->buildPreviewData($product, $lang);

        $filename = 'product-preview-' . ($data['stammdaten']['sku'] ?? $product->id) . '.pdf';

        $pdf = Pdf::loadView('exports.product-preview', [
            'data' => $data,
            'lang' => $lang,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    /**
     * GET /products/{product}/completeness
     *
     * Detailed completeness analysis per section.
     * Includes SVG gauge chart for visual representation.
     */
    public function completeness(Request $request, Product $product, ProductCompletenessService $completenessService): JsonResponse
    {
        $this->authorize('view', $product);

        $lang = $this->getPrimaryLanguage($request);
        $data = $completenessService->calculateCompleteness($product, $lang);

        return response()->json(['data' => $data]);
    }
}
