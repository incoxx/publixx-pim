<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\WatchlistItem;
use App\Services\Preview\ProductPreviewService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class WatchlistController extends Controller
{
    /**
     * GET /api/v1/watchlist
     */
    public function index(Request $request): JsonResponse
    {
        $items = WatchlistItem::where('user_id', $request->user()->id)
            ->with('product.productType')
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $items->map(fn(WatchlistItem $item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'note' => $item->note,
            'created_at' => $item->created_at,
            'product' => $item->product ? [
                'id' => $item->product->id,
                'sku' => $item->product->sku,
                'name' => $item->product->name,
                'status' => $item->product->status,
                'product_type' => $item->product->productType ? [
                    'id' => $item->product->productType->id,
                    'name_de' => $item->product->productType->name_de,
                ] : null,
            ] : null,
        ]);

        return response()->json(['data' => $data]);
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

        $productIds = WatchlistItem::where('user_id', $request->user()->id)
            ->pluck('product_id');

        // Delegate to the existing XLIFF export controller logic
        $products = Product::whereIn('id', $productIds)->get();

        $translatableAttributes = \App\Models\Attribute::where('is_translatable', true)->get();
        $attrIds = $translatableAttributes->pluck('id');

        $sourceValues = \App\Models\ProductAttributeValue::whereIn('product_id', $productIds)
            ->whereIn('attribute_id', $attrIds)
            ->where('language', $sourceLang)
            ->get()
            ->groupBy('product_id');

        $targetValues = \App\Models\ProductAttributeValue::whereIn('product_id', $productIds)
            ->whereIn('attribute_id', $attrIds)
            ->where('language', $targetLang)
            ->get()
            ->groupBy('product_id');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">' . "\n";
        $xml .= '<file source-language="' . $sourceLang . '" target-language="' . $targetLang . '" datatype="plaintext" original="publixx-pim-watchlist">' . "\n";
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
