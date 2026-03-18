<?php

declare(strict_types=1);

namespace App\Services\PdfTemplate;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Image as ImageStyle;

class DocxPdfTemplateWriter
{
    private const MM_TO_TWIP = 56.6929;
    private const MM_TO_PT = 2.834645; // 72 / 25.4
    private const PT_TO_HALF_PT = 2;

    /**
     * Generate a DOCX file from resolved template elements.
     */
    public function write(array $resolvedElements, array $templateJson, array $options, string $outputPath): void
    {
        $phpWord = new PhpWord();

        $defaultFont = $templateJson['style']['defaultFontFamily'] ?? 'DejaVu Sans';
        $phpWord->setDefaultFontName($defaultFont);
        $phpWord->setDefaultFontSize(10);

        $orientation = ($options['page_orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $pageW = $orientation === 'landscape' ? 297 : 210;
        $pageH = $orientation === 'landscape' ? 210 : 297;

        $section = $phpWord->addSection([
            'orientation' => $orientation,
            'pageSizeW' => (int) round($pageW * self::MM_TO_TWIP),
            'pageSizeH' => (int) round($pageH * self::MM_TO_TWIP),
            'marginTop' => 0,
            'marginBottom' => 0,
            'marginLeft' => 0,
            'marginRight' => 0,
        ]);

        foreach ($resolvedElements as $element) {
            $this->renderElement($section, $element);
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outputPath);
    }

    /**
     * Write a combined DOCX with multiple pages (one per product).
     */
    public function writeCombined(array $pages, array $templateJson, array $options, string $outputPath): void
    {
        $phpWord = new PhpWord();

        $defaultFont = $templateJson['style']['defaultFontFamily'] ?? 'DejaVu Sans';
        $phpWord->setDefaultFontName($defaultFont);
        $phpWord->setDefaultFontSize(10);

        $orientation = ($options['page_orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $pageW = $orientation === 'landscape' ? 297 : 210;
        $pageH = $orientation === 'landscape' ? 210 : 297;

        $sectionStyle = [
            'orientation' => $orientation,
            'pageSizeW' => (int) round($pageW * self::MM_TO_TWIP),
            'pageSizeH' => (int) round($pageH * self::MM_TO_TWIP),
            'marginTop' => 0,
            'marginBottom' => 0,
            'marginLeft' => 0,
            'marginRight' => 0,
        ];

        foreach ($pages as $index => $elements) {
            if ($index > 0) {
                $sectionStyle['breakType'] = 'nextPage';
            }
            $section = $phpWord->addSection($sectionStyle);

            foreach ($elements as $element) {
                $this->renderElement($section, $element);
            }
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outputPath);
    }

    private function renderElement($section, array $element): void
    {
        $type = $element['type'] ?? 'text';
        $style = $element['style'] ?? [];

        if ($type === 'variant_table' || $type === 'relation_table' || $type === 'attribute_table') {
            $this->renderVariantTableElement($section, $element);
            return;
        }

        // TextBox positioning uses twips, width/height use points
        $xTwip = (int) round(($element['x'] ?? 0) * self::MM_TO_TWIP);
        $yTwip = (int) round(($element['y'] ?? 0) * self::MM_TO_TWIP);
        $widthPt = round(($element['width'] ?? 50) * self::MM_TO_PT, 1);
        $heightPt = round(($element['height'] ?? 10) * self::MM_TO_PT, 1);

        if ($type === 'image') {
            $this->renderImageElement($section, $element, $xTwip, $yTwip);
            return;
        }

        $displayValue = $element['displayValue'] ?? '';
        if ($type === 'shape') {
            $displayValue = '';
        }

        // Use textBox with absolute positioning (Frame style, unit = pt)
        $hasBorder = isset($style['borderWidth']) && (int) $style['borderWidth'] > 0;
        $textBoxStyle = [
            'width' => $widthPt,
            'height' => $heightPt,
            'positioning' => 'absolute',
            'posHorizontalRel' => 'page',
            'posVerticalRel' => 'page',
            'marginLeft' => $xTwip,
            'marginTop' => $yTwip,
            'wrappingStyle' => 'infront',
            'innerMarginTop' => 0,
            'innerMarginBottom' => 0,
            'innerMarginLeft' => 0,
            'innerMarginRight' => 0,
        ];
        if ($hasBorder) {
            $textBoxStyle['borderSize'] = (int) $style['borderWidth'] * self::PT_TO_HALF_PT;
            $textBoxStyle['borderColor'] = ltrim($style['borderColor'] ?? '000000', '#');
        } else {
            $textBoxStyle['borderSize'] = 0;
            $textBoxStyle['borderColor'] = 'FFFFFF';
        }
        if (!empty($style['backgroundColor']) && $style['backgroundColor'] !== 'transparent') {
            $textBoxStyle['bgColor'] = ltrim($style['backgroundColor'], '#');
        }
        $textBox = $section->addTextBox($textBoxStyle);

        if ($displayValue !== '') {
            $fontStyle = $this->buildFontStyle($style);
            $paragraphStyle = $this->buildParagraphStyle($style);
            $textBox->addText($displayValue, $fontStyle, $paragraphStyle);
        }
    }

    private function renderImageElement($section, array $element, int $xTwip, int $yTwip): void
    {
        $images = $element['resolvedImages'] ?? [];
        if (empty($images)) {
            return;
        }

        // Element bounds in points
        $boxWPt = max(1, ($element['width'] ?? 40) * self::MM_TO_PT);
        $boxHPt = max(1, ($element['height'] ?? 40) * self::MM_TO_PT);

        foreach ($images as $imgPath) {
            if (!file_exists($imgPath)) {
                continue;
            }

            try {
                // Calculate contain dimensions: fit image within element bounds preserving aspect ratio
                $imgSize = @getimagesize($imgPath);
                $renderW = $boxWPt;
                $renderH = $boxHPt;

                if ($imgSize && $imgSize[0] > 0 && $imgSize[1] > 0) {
                    $imgAspect = $imgSize[0] / $imgSize[1];
                    $boxAspect = $boxWPt / $boxHPt;

                    if ($imgAspect > $boxAspect) {
                        $renderW = $boxWPt;
                        $renderH = $boxWPt / $imgAspect;
                    } else {
                        $renderH = $boxHPt;
                        $renderW = $boxHPt * $imgAspect;
                    }
                }

                // Wrap image in a textBox for correct absolute positioning.
                // PhpWord textBox uses twips for marginLeft/marginTop (VML),
                // while addImage uses EMU — leading to wrong positions.
                $textBox = $section->addTextBox([
                    'width' => round($boxWPt, 1),
                    'height' => round($boxHPt, 1),
                    'positioning' => 'absolute',
                    'posHorizontalRel' => 'page',
                    'posVerticalRel' => 'page',
                    'marginLeft' => $xTwip,
                    'marginTop' => $yTwip,
                    'wrappingStyle' => 'infront',
                    'borderSize' => 0,
                    'borderColor' => 'FFFFFF',
                    'innerMarginTop' => 0,
                    'innerMarginBottom' => 0,
                    'innerMarginLeft' => 0,
                    'innerMarginRight' => 0,
                ]);

                $textBox->addImage($imgPath, [
                    'width' => round($renderW, 1),
                    'height' => round($renderH, 1),
                ]);

                break; // Only render first valid image per element
            } catch (\Exception $e) {
                \Log::warning('DOCX image render failed', ['path' => $imgPath, 'error' => $e->getMessage()]);
            }
        }
    }

    private function renderVariantTableElement($section, array $element): void
    {
        $tableData = $element['variantTableData'] ?? [];
        $headers = $tableData['headers'] ?? [];
        $rows = $tableData['rows'] ?? [];

        if (empty($headers)) {
            return;
        }

        $tStyle = $element['tableStyle'] ?? [];
        $borderColor = ltrim($tStyle['borderColor'] ?? '#e5e7eb', '#');
        $headerBg = ltrim($tStyle['headerBg'] ?? '#f3f4f6', '#');
        $headerColor = ltrim($tStyle['headerColor'] ?? '#374151', '#');
        $alternateRowBg = ltrim($tStyle['alternateRowBg'] ?? '#f9fafb', '#');
        $fontSize = (int) ($tStyle['fontSize'] ?? 8);
        $headerFontSize = (int) ($tStyle['headerFontSize'] ?? 8);

        // Add vertical spacing to approximate Y position
        $yMm = $element['y'] ?? 0;
        if ($yMm > 0) {
            $spacingTwip = (int) round($yMm * self::MM_TO_TWIP);
            $section->addText('', [], ['spaceBefore' => $spacingTwip, 'spaceAfter' => 0]);
        }

        // Calculate total table width from element width
        $tableWidthTwip = (int) round(($element['width'] ?? 190) * self::MM_TO_TWIP);
        $colCount = count($headers);
        $columnWidths = $element['columnWidths'] ?? [];

        // Calculate per-column widths in twips
        $colWidthsTwip = [];
        if (!empty($columnWidths) && count($columnWidths) === $colCount) {
            foreach ($columnWidths as $pct) {
                $colWidthsTwip[] = (int) round($tableWidthTwip * (int) $pct / 100);
            }
        } else {
            $defaultWidth = $colCount > 0 ? (int) round($tableWidthTwip / $colCount) : $tableWidthTwip;
            $colWidthsTwip = array_fill(0, $colCount, $defaultWidth);
        }

        $tableStyle = [
            'borderSize' => 4,
            'borderColor' => $borderColor,
            'cellMargin' => 40,
        ];

        // Left indent to approximate X position
        $xMm = $element['x'] ?? 0;
        if ($xMm > 0) {
            $tableStyle['indent'] = new \PhpOffice\PhpWord\ComplexType\TblWidth((int) round($xMm * self::MM_TO_TWIP), 'dxa');
        }

        $table = $section->addTable($tableStyle);

        // Header row
        $table->addRow();
        foreach ($headers as $hi => $h) {
            $table->addCell($colWidthsTwip[$hi] ?? $colWidthsTwip[0], ['bgColor' => $headerBg])->addText(
                htmlspecialchars((string) $h, ENT_XML1, 'UTF-8'),
                ['bold' => true, 'size' => $headerFontSize, 'color' => $headerColor],
                ['spaceAfter' => 0]
            );
        }

        // Data rows
        foreach ($rows as $rowIndex => $row) {
            $table->addRow();
            $rowBg = ($rowIndex % 2 === 1) ? $alternateRowBg : null;
            foreach ($row as $ci => $cell) {
                $cellStyle = $rowBg ? ['bgColor' => $rowBg] : [];
                $table->addCell($colWidthsTwip[$ci] ?? $colWidthsTwip[0], $cellStyle)->addText(
                    htmlspecialchars((string) $cell, ENT_XML1, 'UTF-8'),
                    ['size' => $fontSize],
                    ['spaceAfter' => 0]
                );
            }
        }
    }

    private function buildFontStyle(array $style): array
    {
        $fontStyle = [];

        if (!empty($style['fontFamily'])) {
            $fontStyle['name'] = $style['fontFamily'];
        }
        if (!empty($style['fontSize'])) {
            $fontStyle['size'] = (int) $style['fontSize'];
        }
        if (($style['fontWeight'] ?? 'normal') === 'bold') {
            $fontStyle['bold'] = true;
        }
        if (($style['fontStyle'] ?? 'normal') === 'italic') {
            $fontStyle['italic'] = true;
        }
        if (!empty($style['color'])) {
            $fontStyle['color'] = ltrim($style['color'], '#');
        }

        return $fontStyle;
    }

    private function buildParagraphStyle(array $style): array
    {
        $pStyle = [];

        $align = $style['textAlign'] ?? 'left';
        $pStyle['alignment'] = match ($align) {
            'center' => Jc::CENTER,
            'right' => Jc::END,
            default => Jc::START,
        };

        if (!empty($style['lineHeight'])) {
            $pStyle['lineHeight'] = (float) $style['lineHeight'];
        }

        if (isset($style['padding']) && (int) $style['padding'] > 0) {
            $paddingTwip = (int) round((int) $style['padding'] * self::MM_TO_TWIP);
            $pStyle['indentation'] = ['left' => $paddingTwip, 'right' => $paddingTwip];
            $pStyle['spaceBefore'] = $paddingTwip;
            $pStyle['spaceAfter'] = $paddingTwip;
        }

        return $pStyle;
    }
}
