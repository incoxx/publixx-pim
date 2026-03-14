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

        if ($type === 'variant_table' || $type === 'relation_table') {
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
        $textBox = $section->addTextBox([
            'width' => $widthPt,
            'height' => $heightPt,
            'positioning' => 'absolute',
            'posHorizontalRel' => 'page',
            'posVerticalRel' => 'page',
            'marginLeft' => $xTwip,
            'marginTop' => $yTwip,
            'wrappingStyle' => 'infront',
            'borderSize' => isset($style['borderWidth']) && (int) $style['borderWidth'] > 0
                ? (int) $style['borderWidth'] * self::PT_TO_HALF_PT
                : 0,
            'borderColor' => ltrim($style['borderColor'] ?? '000000', '#'),
        ]);

        if (!empty($style['backgroundColor']) && $style['backgroundColor'] !== 'transparent') {
            $textBox->getStyle()->setBgColor(ltrim($style['backgroundColor'], '#'));
        }

        if ($displayValue !== '') {
            $fontStyle = $this->buildFontStyle($style);
            $paragraphStyle = $this->buildParagraphStyle($style);
            $textBox->addText($displayValue, $fontStyle, $paragraphStyle);
        }
    }

    private function renderImageElement($section, array $element, int $x, int $y): void
    {
        $images = $element['resolvedImages'] ?? [];
        if (empty($images)) {
            return;
        }

        foreach ($images as $imgPath) {
            if (!file_exists($imgPath)) {
                continue;
            }

            try {
                // Calculate contain dimensions: fit image within element bounds preserving aspect ratio
                $imgSize = @getimagesize($imgPath);
                $boxW = max(1, ($element['width'] ?? 40) * (96 / 25.4));
                $boxH = max(1, ($element['height'] ?? 40) * (96 / 25.4));

                if ($imgSize && $imgSize[0] > 0 && $imgSize[1] > 0) {
                    $imgAspect = $imgSize[0] / $imgSize[1];
                    $boxAspect = $boxW / $boxH;

                    if ($imgAspect > $boxAspect) {
                        $renderW = $boxW;
                        $renderH = $boxW / $imgAspect;
                    } else {
                        $renderH = $boxH;
                        $renderW = $boxH * $imgAspect;
                    }
                } else {
                    $renderW = $boxW;
                    $renderH = $boxH;
                }

                $section->addImage($imgPath, [
                    'width' => $renderW,
                    'height' => $renderH,
                    'positioning' => 'absolute',
                    'posHorizontalRel' => 'page',
                    'posVerticalRel' => 'page',
                    'marginLeft' => $x,
                    'marginTop' => $y,
                    'wrappingStyle' => 'infront',
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
        $colWidthTwip = $colCount > 0 ? (int) round($tableWidthTwip / $colCount) : $tableWidthTwip;

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
        foreach ($headers as $h) {
            $table->addCell($colWidthTwip, ['bgColor' => $headerBg])->addText(
                htmlspecialchars((string) $h, ENT_XML1, 'UTF-8'),
                ['bold' => true, 'size' => $headerFontSize, 'color' => $headerColor],
                ['spaceAfter' => 0]
            );
        }

        // Data rows
        foreach ($rows as $rowIndex => $row) {
            $table->addRow();
            $rowBg = ($rowIndex % 2 === 1) ? $alternateRowBg : null;
            foreach ($row as $cell) {
                $cellStyle = $rowBg ? ['bgColor' => $rowBg] : [];
                $table->addCell($colWidthTwip, $cellStyle)->addText(
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
