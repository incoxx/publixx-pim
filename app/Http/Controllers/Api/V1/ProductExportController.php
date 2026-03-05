<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Traits\ProductSearchFilters;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductExportController extends Controller
{
    use ProductSearchFilters;

    /**
     * POST /api/v1/products/export/excel
     *
     * Export products as Excel with configurable columns and search filters.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'columns' => 'required|array|min:1',
            'columns.*' => 'string|max:200',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'string|uuid',
            'search' => 'nullable|string|max:500',
            'search_mode' => 'nullable|string|in:like,soundex,regex',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'string|uuid',
            'include_descendants' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,draft,inactive,discontinued',
            'attribute_filters' => 'nullable|array',
            'language' => 'nullable|string|max:5',
        ]);

        $columns = $validated['columns'];
        $language = $validated['language'] ?? 'de';

        // Build product query
        $query = Product::query()
            ->with('productType')
            ->where('product_type_ref', 'product');

        if (!empty($validated['product_ids'])) {
            $query->whereIn('id', $validated['product_ids']);
        } else {
            if (!empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }
            if (!empty($validated['search'])) {
                $this->applyTextSearch($query, $validated['search'], $validated['search_mode'] ?? 'like');
            }
            if (!empty($validated['category_ids'])) {
                $this->applyCategoryFilter($query, $validated['category_ids'], $validated['include_descendants'] ?? true);
            }
            foreach ($validated['attribute_filters'] ?? [] as $idx => $filter) {
                $this->applyAttributeFilter($query, $filter, $idx, $language);
            }
        }

        $products = $query->limit(5000)->get();

        // Identify attribute columns (prefix: attr:)
        $attrColumns = [];
        $baseColumns = [];
        foreach ($columns as $col) {
            if (str_starts_with($col, 'attr:')) {
                $attrColumns[] = substr($col, 5); // attribute ID
            } else {
                $baseColumns[] = $col;
            }
        }

        // Preload attribute values if needed
        $attrValues = [];
        $attrMap = [];
        if (!empty($attrColumns)) {
            $attrMap = Attribute::whereIn('id', $attrColumns)->pluck('name_de', 'id')->toArray();
            $productIds = $products->pluck('id');
            $values = ProductAttributeValue::whereIn('product_id', $productIds)
                ->whereIn('attribute_id', $attrColumns)
                ->where('language', $language)
                ->get();

            foreach ($values as $v) {
                $attrValues[$v->product_id][$v->attribute_id] = $v->value_string
                    ?? $v->value_number
                    ?? $v->value_date
                    ?? ($v->value_flag !== null ? ($v->value_flag ? 'Ja' : 'Nein') : null)
                    ?? $v->value_selection_id
                    ?? '';
            }
        }

        // Column label map
        $labelMap = [
            'sku' => 'SKU',
            'name' => 'Name',
            'status' => 'Status',
            'ean' => 'EAN',
            'product_type.name_de' => 'Produkttyp',
            'updated_at' => 'Geändert',
            'created_at' => 'Erstellt',
        ];

        // Build spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Produkte');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
        ];

        // Write headers
        $colIdx = 1;
        foreach ($baseColumns as $col) {
            $sheet->setCellValue([$colIdx, 1], $labelMap[$col] ?? $col);
            $colIdx++;
        }
        foreach ($attrColumns as $attrId) {
            $sheet->setCellValue([$colIdx, 1], $attrMap[$attrId] ?? $attrId);
            $colIdx++;
        }

        $lastCol = $colIdx - 1;
        if ($lastCol >= 1) {
            $sheet->getStyle([1, 1, $lastCol, 1])->applyFromArray($headerStyle);
        }

        // Write rows
        $row = 2;
        foreach ($products as $product) {
            $colIdx = 1;
            foreach ($baseColumns as $col) {
                $value = $this->getProductColumnValue($product, $col);
                $sheet->setCellValue([$colIdx, $row], $value);
                $colIdx++;
            }
            foreach ($attrColumns as $attrId) {
                $value = $attrValues[$product->id][$attrId] ?? '';
                $sheet->setCellValue([$colIdx, $row], $value);
                $colIdx++;
            }
            $row++;
        }

        // Auto-size columns
        for ($i = 1; $i <= $lastCol; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        $filename = 'produkte-' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function getProductColumnValue(Product $product, string $column): string
    {
        return match ($column) {
            'sku' => $product->sku ?? '',
            'name' => $product->name ?? '',
            'status' => $product->status ?? '',
            'ean' => $product->ean ?? '',
            'product_type.name_de' => $product->productType?->name_de ?? '',
            'updated_at' => $product->updated_at?->format('d.m.Y H:i') ?? '',
            'created_at' => $product->created_at?->format('d.m.Y H:i') ?? '',
            default => '',
        };
    }
}
