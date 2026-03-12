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

        // TextBox uses points (Frame unit='pt'), images use twips for positioning
        $xPt = round(($element['x'] ?? 0) * self::MM_TO_PT, 1);
        $yPt = round(($element['y'] ?? 0) * self::MM_TO_PT, 1);
        $widthPt = round(($element['width'] ?? 50) * self::MM_TO_PT, 1);
        $heightPt = round(($element['height'] ?? 10) * self::MM_TO_PT, 1);

        if ($type === 'image') {
            $xTwip = (int) round(($element['x'] ?? 0) * self::MM_TO_TWIP);
            $yTwip = (int) round(($element['y'] ?? 0) * self::MM_TO_TWIP);
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
            'marginLeft' => $xPt,
            'marginTop' => $yPt,
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
