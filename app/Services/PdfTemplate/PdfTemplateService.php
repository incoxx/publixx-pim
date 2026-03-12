<?php

declare(strict_types=1);

namespace App\Services\PdfTemplate;

use App\Models\PdfTemplate;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use ZipArchive;

class PdfTemplateService
{
    public function __construct(
        private readonly PdfTemplateRenderer $renderer,
    ) {}

    /**
     * Generate a PDF for a single product.
     */
    public function generateForProduct(PdfTemplate $template, Product $product, string $language = 'de'): string
    {
        $product->loadMissing($this->determineRelations($template->template_json));

        $elements = $this->renderer->resolveElements($template->template_json, $product, $language);

        $pdf = Pdf::loadView('pdf-templates.template', [
            'elements' => $elements,
            'templateJson' => $template->template_json,
            'pageOrientation' => $template->page_orientation ?? 'portrait',
            'pageSize' => $template->page_size ?? 'A4',
        ]);

        $pdf->setPaper(
            $template->page_size ?? 'A4',
            $template->page_orientation ?? 'portrait'
        );

        return $pdf->output();
    }

    /**
     * Generate PDFs for multiple products.
     *
     * @param string $mode 'combined' (all in one PDF) or 'zip' (individual PDFs in ZIP)
     */
    public function generateForProducts(PdfTemplate $template, Collection $products, string $mode = 'combined', string $language = 'de'): array
    {
        $relations = $this->determineRelations($template->template_json);
        $products->loadMissing($relations);

        if ($mode === 'zip') {
            return $this->generateZip($template, $products, $language);
        }

        return $this->generateCombined($template, $products, $language);
    }

    private function generateCombined(PdfTemplate $template, Collection $products, string $language): array
    {
        $allElements = [];
        foreach ($products as $product) {
            $allElements[] = $this->renderer->resolveElements($template->template_json, $product, $language);
        }

        $pdf = Pdf::loadView('pdf-templates.template-combined', [
            'pages' => $allElements,
            'templateJson' => $template->template_json,
            'pageOrientation' => $template->page_orientation ?? 'portrait',
            'pageSize' => $template->page_size ?? 'A4',
        ]);

        $pdf->setPaper(
            $template->page_size ?? 'A4',
            $template->page_orientation ?? 'portrait'
        );

        $tempPath = storage_path('app/temp/pdf-template-' . uniqid() . '.pdf');
        $dir = dirname($tempPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($tempPath, $pdf->output());

        return [
            'path' => $tempPath,
            'content_type' => 'application/pdf',
            'filename' => $template->name . '.pdf',
        ];
    }

    private function generateZip(PdfTemplate $template, Collection $products, string $language): array
    {
        $tempDir = storage_path('app/temp/pdf-template-zip-' . uniqid());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir . '/templates.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($products as $product) {
            $pdfContent = $this->generateForProduct($template, $product, $language);
            $filename = ($product->sku ?? $product->id) . '.pdf';
            $zip->addFromString($filename, $pdfContent);
        }

        $zip->close();

        return [
            'path' => $zipPath,
            'content_type' => 'application/zip',
            'filename' => $template->name . '.zip',
            'temp_dir' => $tempDir,
        ];
    }

    /**
     * Determine which relations to eager-load based on template elements.
     */
    private function determineRelations(array $templateJson): array
    {
        $relations = ['productType', 'masterHierarchyNode'];
        $hasAttributes = false;
        $hasImages = false;

        foreach ($templateJson['elements'] ?? [] as $element) {
            if (($element['type'] ?? '') === 'attribute') {
                $hasAttributes = true;
            }
            if (($element['type'] ?? '') === 'image') {
                $hasImages = true;
            }
        }

        if ($hasAttributes) {
            $relations[] = 'attributeValues.attribute';
            $relations[] = 'attributeValues.unit';
            $relations[] = 'attributeValues.valueListEntry';
        }

        if ($hasImages) {
            $relations[] = 'mediaAssignments.media';
            $relations[] = 'mediaAssignments.usageType';
        }

        return $relations;
    }
}
