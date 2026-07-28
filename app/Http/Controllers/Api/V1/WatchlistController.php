<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MediaResource;
use App\Models\Media;
use App\Models\PdfTemplate;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\WatchlistItem;
use App\Services\PdfTemplate\PdfTemplateService;
use App\Services\Preview\ProductPreviewService;
use App\Support\AttributeValueFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class WatchlistController extends Controller
{
    private const ALLOWED_FILTERS = ['status', 'product_type_id'];

    // Felder, die per Präfix-Suche (LIKE 'wert%') statt Exakt-Match gefiltert werden,
    // für das Quick-Lookup in der Merkliste (Spalten liegen auf der products-Tabelle).
    private const ALLOWED_PREFIX_FILTERS = ['sku', 'name', 'ean'];

    // Sortier-Spalte (Frontend-Key → tabellenqualifizierte Spalte) — nötig, weil
    // sowohl watchlist_items als auch products eine created_at-Spalte haben und
    // die übrigen Sortierfelder auf products liegen, nicht auf watchlist_items.
    private const SORT_COLUMN_MAP = [
        'sku' => 'products.sku',
        'name' => 'products.name',
        'status' => 'products.status',
        'created_at' => 'watchlist_items.created_at',
    ];

    /**
     * GET /api/v1/watchlist
     */
    public function index(Request $request): JsonResponse
    {
        $query = WatchlistItem::query()
            ->where('watchlist_items.user_id', $request->user()->id)
            ->join('products', 'products.id', '=', 'watchlist_items.product_id')
            ->with('product.productType')
            ->select('watchlist_items.*');

        $rawFilters = $request->query('filter', []);

        $this->applyPrefixFilters($query, $rawFilters, self::ALLOWED_PREFIX_FILTERS, 'products');

        $this->applyFilters($query, array_intersect_key(
            $rawFilters,
            array_flip(self::ALLOWED_FILTERS)
        ));

        // Quick-Lookup auf dynamische Attribut-Spalten: filter[attributes][<attribute_id>]=wert
        // (Präfix-Suche auf value_string, analog zu den übrigen Textfeldern oben).
        $attributeFilters = $rawFilters['attributes'] ?? [];
        if (is_array($attributeFilters)) {
            foreach ($attributeFilters as $attributeId => $value) {
                if (!is_string($value) || $value === '') {
                    continue;
                }
                $escapedValue = addcslashes($value, '%_');
                $query->whereExists(function ($q) use ($attributeId, $escapedValue) {
                    $q->select(DB::raw(1))
                        ->from('product_attribute_values')
                        ->whereColumn('product_attribute_values.product_id', 'watchlist_items.product_id')
                        ->where('product_attribute_values.attribute_id', $attributeId)
                        ->where('product_attribute_values.value_string', 'LIKE', $escapedValue.'%');
                });
            }
        }

        $sort = $request->query('sort', 'created_at');
        $order = strtolower($request->query('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy(self::SORT_COLUMN_MAP[$sort] ?? 'watchlist_items.created_at', $order);

        $paginated = $query->paginate($this->getPerPage($request));

        // Optionally load attribute column values
        $attributeColumns = $request->input('attribute_columns', []);
        $attrValuesMap = collect();

        if (!empty($attributeColumns) && is_array($attributeColumns)) {
            $language = $request->input('language', 'de');
            $productIds = collect($paginated->items())->pluck('product_id')->filter();
            $attrValuesMap = ProductAttributeValue::whereIn('product_id', $productIds)
                ->whereIn('attribute_id', $attributeColumns)
                ->where(fn ($q) => $q->where('language', $language)->orWhereNull('language'))
                ->with('valueListEntry')
                ->get()
                ->groupBy('product_id');
        }

        $data = collect($paginated->items())->map(function (WatchlistItem $item) use ($attributeColumns, $attrValuesMap) {
            $productData = $item->product ? [
                'id' => $item->product->id,
                'sku' => $item->product->sku,
                'name' => $item->product->name,
                'status' => $item->product->status,
                'ean' => $item->product->ean,
                'product_type' => $item->product->productType ? [
                    'id' => $item->product->productType->id,
                    'name_de' => $item->product->productType->name_de,
                ] : null,
            ] : null;

            // Append attribute values if requested
            if ($productData && !empty($attributeColumns)) {
                $attrs = [];
                $productAttrValues = $attrValuesMap->get($item->product_id, collect());
                foreach ($attributeColumns as $attrId) {
                    $av = $productAttrValues->firstWhere('attribute_id', $attrId);
                    if (!$av) {
                        $attrs[$attrId] = null;
                    } elseif ($av->value_selection_id && $av->valueListEntry) {
                        $attrs[$attrId] = $av->valueListEntry->display_value_de ?? $av->valueListEntry->code ?? '';
                    } elseif ($av->value_flag !== null) {
                        $attrs[$attrId] = $av->value_flag ? 'Ja' : 'Nein';
                    } elseif ($av->value_date !== null) {
                        $attrs[$attrId] = $av->value_date;
                    } elseif ($av->value_number !== null) {
                        $attrs[$attrId] = AttributeValueFormatter::number($av->value_number);
                    } else {
                        $attrs[$attrId] = $av->value_string ?? '';
                    }
                }
                $productData['attributes'] = $attrs;
            }

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'note' => $item->note,
                'created_at' => $item->created_at,
                'product' => $productData,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/watchlist
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'note' => 'nullable|string|max:500',
        ]);

        $existing = WatchlistItem::where('user_id', $request->user()->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existing) {
            return response()->json(['data' => $existing, 'message' => 'Bereits auf Merkliste'], 200);
        }

        $item = WatchlistItem::create([
            'user_id' => $request->user()->id,
            'product_id' => $validated['product_id'],
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json(['data' => $item], 201);
    }

    /**
     * POST /api/v1/watchlist/bulk
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'uuid|exists:products,id',
        ]);

        $userId = $request->user()->id;
        $added = 0;

        foreach ($validated['product_ids'] as $productId) {
            $exists = WatchlistItem::where('user_id', $userId)
                ->where('product_id', $productId)
                ->exists();

            if (!$exists) {
                WatchlistItem::create([
                    'user_id' => $userId,
                    'product_id' => $productId,
                ]);
                $added++;
            }
        }

        return response()->json([
            'message' => "{$added} Produkt(e) zur Merkliste hinzugefügt",
            'added' => $added,
        ]);
    }

    /**
     * DELETE /api/v1/watchlist/{watchlistItem}
     */
    public function destroy(Request $request, WatchlistItem $watchlistItem): JsonResponse
    {
        if ($watchlistItem->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Nicht berechtigt'], 403);
        }

        $watchlistItem->delete();

        return response()->json(null, 204);
    }

    /**
     * DELETE /api/v1/watchlist/product/{productId}
     */
    public function removeByProduct(Request $request, string $productId): JsonResponse
    {
        WatchlistItem::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/watchlist/bulk-remove
     */
    public function bulkRemove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'uuid',
        ]);

        $deleted = WatchlistItem::where('user_id', $request->user()->id)
            ->whereIn('id', $validated['ids'])
            ->delete();

        return response()->json(['message' => "{$deleted} Einträge entfernt", 'deleted' => $deleted]);
    }

    /**
     * DELETE /api/v1/watchlist/all
     */
    public function removeAll(Request $request): JsonResponse
    {
        $deleted = WatchlistItem::where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => "{$deleted} Einträge entfernt", 'deleted' => $deleted]);
    }

    /**
     * GET /api/v1/watchlist/product-ids
     *
     * Quick lookup: returns array of product IDs on watchlist.
     */
    public function productIds(Request $request): JsonResponse
    {
        $ids = WatchlistItem::where('user_id', $request->user()->id)
            ->pluck('product_id');

        return response()->json(['data' => $ids]);
    }

    /**
     * GET /api/v1/watchlist/completeness
     *
     * Aggregierter Produktfüllstand über die eigene Merkliste (Arbeitsvorrat).
     * Nutzt die vorab berechnete Spalte products_search_index.attribute_completeness
     * (per Observer aktuell gehalten) → eine einzige Aggregat-Abfrage.
     * Liefert dasselbe Schema wie das Dashboard-Feld completeness_summary.
     */
    public function completeness(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Subquery vermeidet Platzhalter-Limit bei großen Merklisten.
        $productIdQuery = WatchlistItem::where('user_id', $userId)->select('product_id');
        $total = WatchlistItem::where('user_id', $userId)->count();

        // Gleiche Definition wie das Dashboard (countCompleteProducts), auf die
        // Merkliste eingegrenzt: aktiv + SKU + Name + mindestens ein Attributwert.
        $fullyComplete = Product::whereIn('id', $productIdQuery)
            ->where('status', 'active')
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('name')->where('name', '!=', '')
            ->whereHas('attributeValues')
            ->count();

        $average = $total > 0 ? (int) round(($fullyComplete / $total) * 100) : 0;

        return response()->json(['data' => [
            'fully_complete' => $fullyComplete,
            'incomplete' => max(0, $total - $fullyComplete),
            'total' => $total,
            'average_percentage' => $average,
        ]]);
    }

    /**
     * GET /api/v1/watchlist/data-quality
     *
     * Mehrdimensionale Datenqualität über die eigene Merkliste (gleiche Logik wie
     * das Dashboard, auf den Arbeitsvorrat eingegrenzt).
     */
    public function dataQuality(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $productIdQuery = WatchlistItem::where('user_id', $userId)->select('product_id');
        $total = WatchlistItem::where('user_id', $userId)->count();

        if ($total === 0) {
            return response()->json(['data' => ['overall' => 0, 'total_products' => 0, 'dimensions' => []]]);
        }

        // Produkte mit Mehrsprachigkeit (≥ 2 Sprachen mit Attributwerten)
        $multiLangIds = DB::table('product_attribute_values')
            ->whereIn('product_id', $productIdQuery)
            ->whereNotNull('language')
            ->groupBy('product_id')
            ->havingRaw('COUNT(DISTINCT language) > 1')
            ->pluck('product_id');

        // "Erfüllt"-Bedingung je Dimension als Closure auf einer Produkt-Query.
        $met = [
            'master_data' => fn ($q) => $q->whereNotNull('sku')->where('sku', '!=', '')->whereNotNull('name')->where('name', '!=', ''),
            'attributes' => fn ($q) => $q->whereHas('attributeValues'),
            'media' => fn ($q) => $q->whereHas('media'),
            'prices' => fn ($q) => $q->whereHas('prices'),
            'translations' => fn ($q) => $q->whereIn('id', $multiLangIds),
        ];
        $labels = [
            'master_data' => ['Stammdaten', 'SKU & Name ergänzen'],
            'attributes' => ['Attribute', 'Attribute pflegen'],
            'media' => ['Medien', 'Bilder/Medien hinzufügen'],
            'prices' => ['Preise', 'Preise pflegen'],
            'translations' => ['Übersetzungen', 'Übersetzungen ergänzen'],
        ];

        $missingLimit = 50;
        $dimensions = [];
        foreach ($met as $key => $condition) {
            $count = Product::whereIn('id', $productIdQuery)->where($condition)->count();

            // Betroffene (nicht erfüllte) Produkte — konkret WELCHE.
            $missing = Product::whereIn('id', $productIdQuery)
                ->whereNot($condition)
                ->orderBy('sku')
                ->limit($missingLimit)
                ->get(['id', 'sku', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'sku' => $p->sku, 'name' => $p->name])
                ->all();

            $dimensions[] = [
                'key' => $key,
                'label' => $labels[$key][0],
                'action' => $labels[$key][1],
                'count' => $count,
                'missing_count' => $total - $count,
                'percentage' => (int) round(($count / $total) * 100),
                'missing' => $missing,
            ];
        }

        $overall = (int) round(array_sum(array_column($dimensions, 'percentage')) / count($dimensions));

        return response()->json(['data' => [
            'overall' => $overall,
            'total_products' => $total,
            'dimensions' => $dimensions,
        ]]);
    }

    /**
     * GET /api/v1/watchlist/media
     *
     * Medien der Produkte auf der eigenen Merkliste (für das Medien-Spotlight im
     * Cockpit) — statt beliebiger Medien über alle Produkte.
     */
    public function media(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $productIdQuery = WatchlistItem::where('user_id', $userId)->select('product_id');

        // Distinkte Medien-IDs der Merklisten-Produkte (über die Zuordnungstabelle).
        $mediaIdQuery = DB::table('product_media_assignments')
            ->whereIn('product_id', $productIdQuery)
            ->select('media_id');

        $total = Media::whereIn('id', $mediaIdQuery)->count();
        $items = Media::whereIn('id', $mediaIdQuery)
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        return response()->json([
            'data' => MediaResource::collection($items),
            'meta' => ['total' => $total],
        ]);
    }

    /**
     * GET /api/v1/watchlist/export/excel
     */
    public function exportExcel(Request $request, ProductPreviewService $previewService): StreamedResponse
    {
        $lang = $request->query('lang', 'de');

        $items = WatchlistItem::where('user_id', $request->user()->id)
            ->with('product')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Merkliste');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
        ];

        // Header
        $sheet->setCellValue('A1', 'SKU');
        $sheet->setCellValue('B1', 'Name');
        $sheet->setCellValue('C1', 'Status');
        $sheet->setCellValue('D1', 'Produkttyp');
        $sheet->setCellValue('E1', 'EAN');
        $sheet->setCellValue('F1', 'Notiz');
        $sheet->setCellValue('G1', 'Hinzugefügt am');
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

        $row = 2;
        foreach ($items as $item) {
            $p = $item->product;
            if (!$p) {
                continue;
            }

            $sheet->setCellValue("A{$row}", $p->sku ?? '-');
            $sheet->setCellValue("B{$row}", $p->name ?? '-');
            $sheet->setCellValue("C{$row}", $p->status ?? '-');
            $sheet->setCellValue("D{$row}", $p->productType?->name_de ?? '-');
            $sheet->setCellValue("E{$row}", $p->ean ?? '-');
            $sheet->setCellValue("F{$row}", $item->note ?? '');
            $sheet->setCellValue("G{$row}", $item->created_at?->format('d.m.Y H:i') ?? '-');
            $row++;
        }

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'merkliste-' . now()->format('Y-m-d') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * GET /api/v1/watchlist/export/pdf
     *
     * Export all watchlist products in one PDF.
     */
    public function exportPdf(Request $request, ProductPreviewService $previewService): \Illuminate\Http\Response
    {
        $lang = $request->query('lang', 'de');

        $items = WatchlistItem::where('user_id', $request->user()->id)
            ->with('product')
            ->get();

        $products = $items->map(fn($item) => $item->product)->filter();

        $allData = [];
        foreach ($products as $product) {
            $allData[] = $previewService->buildPreviewData($product, $lang);
        }

        $pdf = Pdf::loadView('exports.watchlist-pdf', [
            'products' => $allData,
            'lang' => $lang,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download('merkliste-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * GET /api/v1/watchlist/export/pdf-zip
     *
     * Export each watchlist product as individual PDF, bundled in ZIP.
     */
    public function exportPdfZip(Request $request, ProductPreviewService $previewService): StreamedResponse
    {
        $lang = $request->query('lang', 'de');

        $items = WatchlistItem::where('user_id', $request->user()->id)
            ->with('product')
            ->get();

        $tempDir = storage_path('app/temp/watchlist-' . uniqid());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir . '/merkliste.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($items as $item) {
            $product = $item->product;
            if (!$product) {
                continue;
            }

            $data = $previewService->buildPreviewData($product, $lang);

            $pdf = Pdf::loadView('exports.product-preview', [
                'data' => $data,
                'lang' => $lang,
            ]);
            $pdf->setPaper('a4', 'portrait');

            $filename = ($product->sku ?? $product->id) . '.pdf';
            $pdfContent = $pdf->output();
            $zip->addFromString($filename, $pdfContent);
        }

        $zip->close();

        return response()->streamDownload(function () use ($zipPath, $tempDir) {
            readfile($zipPath);
            // Cleanup
            array_map('unlink', glob("{$tempDir}/*"));
            rmdir($tempDir);
        }, 'merkliste-' . now()->format('Y-m-d') . '.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * POST /api/v1/watchlist/export/pdf-template
     *
     * Export watchlist products using a PDF template.
     */
    public function exportPdfTemplate(Request $request, PdfTemplateService $pdfTemplateService): StreamedResponse
    {
        $validated = $request->validate([
            'pdf_template_id' => 'required|string|exists:pdf_templates,id',
            'mode' => 'sometimes|string|in:combined,zip',
            'lang' => 'sometimes|string|max:5',
            'format' => 'sometimes|string|in:pdf,docx,indesign',
        ]);

        $template = PdfTemplate::findOrFail($validated['pdf_template_id']);
        $mode = $validated['mode'] ?? 'combined';
        $lang = $validated['lang'] ?? 'de';
        $format = $validated['format'] ?? 'pdf';

        $items = WatchlistItem::where('user_id', $request->user()->id)
            ->with('product')
            ->get();

        $products = $items->map(fn ($item) => $item->product)->filter()->values();

        if ($products->isEmpty()) {
            abort(404, 'Keine Produkte auf der Merkliste.');
        }

        if ($format === 'indesign') {
            $result = $pdfTemplateService->generateInDesignForProducts($template, $products, $lang);
        } elseif ($format === 'docx') {
            $result = $pdfTemplateService->generateDocxForProducts($template, $products, $mode, $lang);
        } else {
            $result = $pdfTemplateService->generateForProducts($template, $products, $mode, $lang);
        }

        return response()->streamDownload(function () use ($result) {
            readfile($result['path']);
            @unlink($result['path']);
            if (!empty($result['temp_dir'])) {
                @array_map('unlink', glob($result['temp_dir'] . '/*'));
                @rmdir($result['temp_dir']);
            }
        }, $result['filename'], [
            'Content-Type' => $result['content_type'],
        ]);
    }

    /**
     * GET /api/v1/watchlist/export/xliff
     */
    public function exportXliff(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'source_lang' => 'required|string|max:5',
            'target_lang' => 'required|string|max:5',
        ]);

        $sourceLang = $validated['source_lang'];
        $targetLang = $validated['target_lang'];

        // Use subquery to avoid too many placeholders with large watchlists
        $productIdQuery = WatchlistItem::where('user_id', $request->user()->id)
            ->select('product_id');

        $products = Product::whereIn('id', $productIdQuery)->get();

        $translatableAttributes = \App\Models\Attribute::where('is_translatable', true)->get();
        $attrIds = $translatableAttributes->pluck('id');

        $sourceValues = \App\Models\ProductAttributeValue::whereIn('product_id', $productIdQuery)
            ->whereIn('attribute_id', $attrIds)
            ->where('language', $sourceLang)
            ->get()
            ->groupBy('product_id');

        $targetValues = \App\Models\ProductAttributeValue::whereIn('product_id', $productIdQuery)
            ->whereIn('attribute_id', $attrIds)
            ->where('language', $targetLang)
            ->get()
            ->groupBy('product_id');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">' . "\n";
        $xml .= '<file source-language="' . $sourceLang . '" target-language="' . $targetLang . '" datatype="plaintext" original="anypim-watchlist">' . "\n";
        $xml .= '<body>' . "\n";

        foreach ($products as $product) {
            $sources = $sourceValues[$product->id] ?? collect();
            $targets = $targetValues[$product->id] ?? collect();
            $targetMap = $targets->keyBy(function ($v) {
                return $v->attribute_id . '|' . ($v->multiplied_index ?? 0);
            });

            foreach ($sources as $sv) {
                $key = $sv->attribute_id . '|' . ($sv->multiplied_index ?? 0);
                $tv = $targetMap[$key] ?? null;

                $unitId = $product->id . '|' . $sv->attribute_id . '|' . ($sv->multiplied_index ?? 0);
                $sourceText = htmlspecialchars($sv->value_string ?? '', ENT_XML1, 'UTF-8');
                $targetText = $tv ? htmlspecialchars($tv->value_string ?? '', ENT_XML1, 'UTF-8') : '';
                $state = $tv && $tv->value_string ? 'translated' : 'needs-translation';

                $attr = $translatableAttributes->firstWhere('id', $sv->attribute_id);
                $note = ($product->sku ?? '') . ' — ' . ($attr->name_de ?? $attr->technical_name ?? '');

                $xml .= '<trans-unit id="' . htmlspecialchars($unitId, ENT_XML1, 'UTF-8') . '">' . "\n";
                $xml .= '  <source>' . $sourceText . '</source>' . "\n";
                $xml .= '  <target state="' . $state . '">' . $targetText . '</target>' . "\n";
                $xml .= '  <note>' . htmlspecialchars($note, ENT_XML1, 'UTF-8') . '</note>' . "\n";
                $xml .= '</trans-unit>' . "\n";
            }
        }

        $xml .= '</body>' . "\n";
        $xml .= '</file>' . "\n";
        $xml .= '</xliff>';

        $filename = "merkliste-{$sourceLang}-{$targetLang}.xliff";

        return response()->streamDownload(function () use ($xml) {
            echo $xml;
        }, $filename, [
            'Content-Type' => 'application/xliff+xml',
        ]);
    }
}
