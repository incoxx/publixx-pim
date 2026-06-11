<?php

declare(strict_types=1);

namespace App\Services\PdfTemplate;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

class DocxPdfTemplateWriter
{
    private const MM_TO_TWIP = 56.6929;
    private const MM_TO_PT = 2.834645;  // 72 / 25.4

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

        // VML uses points for all properties; posHorizontal/posVertical must be
        // 'absolute' so Word uses marginLeft/marginTop as exact coordinates.
        $xPt = round(($element['x'] ?? 0) * self::MM_TO_PT, 1);
        $yPt = round(($element['y'] ?? 0) * self::MM_TO_PT, 1);
        $widthPt = round(($element['width'] ?? 50) * self::MM_TO_PT, 1);
        $heightPt = round(($element['height'] ?? 10) * self::MM_TO_PT, 1);

        if ($type === 'image') {
            $this->renderImageElement($section, $element, $xPt, $yPt);
            return;
        }

        $displayValue = $element['displayValue'] ?? '';
        if ($type === 'shape') {
            $displayValue = '';
        }

        $hasBorder = isset($style['borderWidth']) && (int) $style['borderWidth'] > 0;
        $textBoxStyle = [
            'width' => $widthPt,
            'height' => $heightPt,
            'positioning' => 'absolute',
            'posHorizontal' => 'absolute',
            'posVertical' => 'absolute',
            'posHorizontalRel' => 'page',
            'posVerticalRel' => 'page',
            'marginLeft' => $xPt,
            'marginTop' => $yPt,
            'wrappingStyle' => 'infront',
            'innerMarginTop' => 0,
            'innerMarginBottom' => 0,
            'innerMarginLeft' => 0,
            'innerMarginRight' => 0,
        ];
        if ($hasBorder) {
            $textBoxStyle['borderSize'] = (int) $style['borderWidth'];
            $textBoxStyle['borderColor'] = ltrim($style['borderColor'] ?? '000000', '#');
        } else {
            $textBoxStyle['borderSize'] = 0;
            $textBoxStyle['borderColor'] = 'FFFFFF';
        }
        if (!empty($style['backgroundColor']) && $style['backgroundColor'] !== 'transparent') {
            $textBoxStyle['bgColor'] = ltrim($style['backgroundColor'], '#');
        }
        $textBox = $section->addTextBox($textBoxStyle);

        $showSplitLabel = !empty($element['showLabel']) && !empty($element['rawLabel']);
        if ($showSplitLabel) {
            $this->renderSplitLabel($textBox, $element, $style);
        } elseif ($displayValue !== '') {
            $fontStyle = $this->buildFontStyle($style);
            $paragraphStyle = $this->buildParagraphStyle($style);
            $textBox->addText($displayValue, $fontStyle, $paragraphStyle);
        }
    }

    private function renderImageElement($section, array $element, float $xPt, float $yPt): void
    {
        $images = $element['resolvedImages'] ?? [];
        if (empty($images)) {
            return;
        }

        // Image VML also uses points (same as TextBox)
        $boxWPt = max(1, round(($element['width'] ?? 40) * self::MM_TO_PT, 1));
        $boxHPt = max(1, round(($element['height'] ?? 40) * self::MM_TO_PT, 1));

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

                $section->addImage($imgPath, [
                    'width' => round($renderW, 1),
                    'height' => round($renderH, 1),
                    'positioning' => 'absolute',
                    'posHorizontal' => 'absolute',
                    'posVertical' => 'absolute',
                    'posHorizontalRel' => 'page',
                    'posVerticalRel' => 'page',
                    'marginLeft' => $xPt,
                    'marginTop' => $yPt,
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

    /**
     * Build value text for split-label rendering (price/attribute/field).
     */
    private function buildValueText(array $element): string
    {
        $type = $element['type'] ?? 'text';
        if ($type === 'price') {
            $amount = $element['rawValue'] ?? null;
            return $amount !== null
                ? number_format((float) $amount, 2, ',', '.') . ' ' . ($element['currency'] ?? 'EUR')
                : '';
        }
        if ($type === 'attribute') {
            $value = (string) ($element['rawValue'] ?? '');
            if (!empty($element['showUnit']) && !empty($element['rawUnit'])) {
                $value = trim($value . ' ' . $element['rawUnit']);
            }
            return $value;
        }
        return (string) ($element['rawValue'] ?? '');
    }

    /**
     * Render label + value as separate styled runs/paragraphs in a TextBox.
     */
    private function renderSplitLabel($textBox, array $element, array $style): void
    {
        $rawLabel   = $element['rawLabel'] ?? '';
        $labelSep   = $element['labelSeparator'] ?? ': ';
        $labelPos   = $element['labelPosition'] ?? 'left';
        $labelGapMm = (float) ($element['labelGap'] ?? 2);
        $valueText  = $this->buildValueText($element);
        $labelStyle = $element['labelStyle'] ?? [];

        $mainFont  = $this->buildFontStyle($style);
        $labelFont = $this->buildFontStyle($style, $labelStyle);
        $paraStyle = $this->buildParagraphStyle($style);

        if ($labelPos === 'concat') {
            // concat: label + separator + value in one text run
            $run = $textBox->addTextRun($paraStyle);
            $run->addText(htmlspecialchars($rawLabel . $labelSep, ENT_XML1, 'UTF-8'), $labelFont);
            if ($valueText !== '') {
                $run->addText(htmlspecialchars($valueText, ENT_XML1, 'UTF-8'), $mainFont);
            }
        } elseif ($labelPos === 'top') {
            $gapTwip = (int) round($labelGapMm * self::MM_TO_TWIP);
            $textBox->addText(
                htmlspecialchars($rawLabel, ENT_XML1, 'UTF-8'),
                $labelFont,
                array_merge($paraStyle, ['spaceAfter' => $gapTwip])
            );
            if ($valueText !== '') {
                $textBox->addText(
                    htmlspecialchars($valueText, ENT_XML1, 'UTF-8'),
                    $mainFont,
                    $paraStyle
                );
            }
        } else {
            // left: label + gap + value in one paragraph as text runs
            $run = $textBox->addTextRun($paraStyle);
            $run->addText(htmlspecialchars($rawLabel, ENT_XML1, 'UTF-8'), $labelFont);
            $spaces = max(1, (int) round($labelGapMm * 1.5));
            $run->addText(str_repeat(' ', $spaces), $mainFont);
            if ($valueText !== '') {
                $run->addText(htmlspecialchars($valueText, ENT_XML1, 'UTF-8'), $mainFont);
            }
        }
    }

    private function buildFontStyle(array $style, array $overrides = []): array
    {
        // Override fontSize and color from labelStyle where set
        $merged = $style;
        if (!empty($overrides['fontSize'])) $merged['fontSize'] = $overrides['fontSize'];
        if (!empty($overrides['color'])) $merged['color'] = $overrides['color'];
        if (!empty($overrides['fontWeight'])) $merged['fontWeight'] = $overrides['fontWeight'];

        $fontStyle = [];
        if (!empty($merged['fontFamily'])) {
            $fontStyle['name'] = $merged['fontFamily'];
        }
        if (!empty($merged['fontSize'])) {
            $fontStyle['size'] = (int) $merged['fontSize'];
        }
        if (($merged['fontWeight'] ?? 'normal') === 'bold') {
            $fontStyle['bold'] = true;
        }
        if (($merged['fontStyle'] ?? 'normal') === 'italic') {
            $fontStyle['italic'] = true;
        }
        if (!empty($merged['color'])) {
            $fontStyle['color'] = ltrim($merged['color'], '#');
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
