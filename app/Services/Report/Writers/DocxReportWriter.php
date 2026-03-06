<?php

declare(strict_types=1);

namespace App\Services\Report\Writers;

use App\Services\Report\ElementRenderer;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

class DocxReportWriter
{
    public function __construct(
        private readonly ElementRenderer $elementRenderer,
    ) {}

    /**
     * Generate a Word document from grouped data.
     */
    public function write(array $groupedData, array $templateJson, array $options, string $outputPath): void
    {
        $phpWord = new PhpWord();

        // Default font
        $defaultFont = $templateJson['style']['font'] ?? 'Arial';
        $defaultSize = $templateJson['style']['size'] ?? 11;
        $phpWord->setDefaultFontName($defaultFont);
        $phpWord->setDefaultFontSize($defaultSize);

        $orientation = ($options['page_orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';

        $sectionStyle = [
            'orientation' => $orientation,
            'pageSizeW' => $orientation === 'landscape' ? 16838 : 11906, // A4
            'pageSizeH' => $orientation === 'landscape' ? 11906 : 16838,
            'marginTop' => 1134,   // ~2cm
            'marginBottom' => 1134,
            'marginLeft' => 1134,
            'marginRight' => 1134,
        ];

        $section = $phpWord->addSection($sectionStyle);
        $language = $options['language'] ?? 'de';

        // Page header
        $this->renderPageHeader($section, $templateJson, $options);

        // Render groups
        $this->renderGroups($section, $groupedData, $templateJson, $language, 0);

        // Page footer
        $this->renderPageFooter($section, $templateJson, $options);

        // Save
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outputPath);
    }

    private function renderPageHeader($section, array $templateJson, array $options): void
    {
        $headerElements = $templateJson['pageHeader']['elements'] ?? [];
        if (empty($headerElements)) {
            // Default header with title
            $header = $section->addHeader();
            $header->addText(
                $options['title'] ?? 'Report',
                ['bold' => true, 'size' => 14],
                ['alignment' => Jc::LEFT]
            );
            return;
        }

        $header = $section->addHeader();
        foreach ($headerElements as $element) {
            $this->renderTextElement($header, $element, $options);
        }
    }

    private function renderPageFooter($section, array $templateJson, array $options): void
    {
        $footerElements = $templateJson['pageFooter']['elements'] ?? [];
        $footer = $section->addFooter();

        if (empty($footerElements)) {
            $footer->addPreserveText(
                '{PAGE} / {NUMPAGES}',
                ['size' => 8, 'color' => '888888'],
                ['alignment' => Jc::CENTER]
            );
            return;
        }

        foreach ($footerElements as $element) {
            $content = $element['content'] ?? '';
            if (str_contains($content, '{page}') || str_contains($content, '{pages}')) {
                $content = str_replace(['{page}', '{pages}'], ['{PAGE}', '{NUMPAGES}'], $content);
                $style = $this->buildFontStyle($element['style'] ?? []);
                $pStyle = $this->buildParagraphStyle($element['style'] ?? []);
                $footer->addPreserveText($content, $style, $pStyle);
            } else {
                $this->renderTextElement($footer, $element, $options);
            }
        }
    }

    private function renderGroups($section, array $groups, array $templateJson, string $language, int $depth): void
    {
        foreach ($groups as $group) {
            $definition = $group['definition'] ?? [];

            // Group header
            $this->renderGroupHeader($section, $group, $definition, $language, $depth);

            // Detail rows (products)
            if (!empty($group['products'])) {
                $this->renderDetailRows($section, $group['products'], $definition, $language);
            }

            // Subgroups
            if (!empty($group['subgroups'])) {
                $this->renderGroups($section, $group['subgroups'], $templateJson, $language, $depth + 1);
            }

            // Group footer
            $this->renderGroupFooter($section, $group, $definition, $language);

            // Page break after group
            if (!empty($definition['pageBreak'])) {
                $section->addPageBreak();
            }
        }
    }

    private function renderGroupHeader($section, array $group, array $definition, string $language, int $depth): void
    {
        $headerElements = $definition['header']['elements'] ?? [];

        if (empty($headerElements) && $group['value']) {
            // Default: show group value as heading
            $fontSize = max(11, 16 - ($depth * 2));
            $section->addText(
                $group['value'],
                ['bold' => true, 'size' => $fontSize],
                ['spaceAfter' => 120]
            );
            return;
        }

        $context = [
            'group.value' => $group['value'] ?? '',
            'group.label' => $group['label'] ?? '',
            'count' => (string) ($group['count'] ?? 0),
        ];

        foreach ($headerElements as $element) {
            $this->renderElementInSection($section, $element, $context, $language);
        }
    }

    private function renderDetailRows($section, array $products, array $definition, string $language): void
    {
        $detailElements = $definition['detail']['elements'] ?? [];

        if (empty($detailElements)) {
            return;
        }

        // Check if we should use table layout
        $fieldElements = array_filter($detailElements, fn ($e) => in_array($e['type'], ['field', 'attribute']));

        if (count($fieldElements) >= 2) {
            $this->renderProductsAsTable($section, $products, $detailElements, $language);
        } else {
            foreach ($products as $product) {
                $this->renderProductBlock($section, $product, $detailElements, $language);
            }
        }
    }

    private function renderProductsAsTable($section, array $products, array $elements, string $language): void
    {
        $tableStyle = [
            'borderSize' => 4,
            'borderColor' => 'CCCCCC',
            'cellMargin' => 60,
        ];

        // Filter to renderable field/attribute elements
        $columns = array_filter($elements, fn ($e) => in_array($e['type'], ['field', 'attribute']));
        $columns = array_values($columns);

        if (empty($columns)) {
            return;
        }

        $table = $section->addTable($tableStyle);

        // Header row
        $table->addRow();
        foreach ($columns as $col) {
            $label = $col['label'] ?? $col['field'] ?? '';
            $table->addCell(null, ['bgColor' => 'F3F4F6'])->addText(
                $label,
                ['bold' => true, 'size' => 9],
                ['spaceAfter' => 0]
            );
        }

        // Data rows
        foreach ($products as $product) {
            $table->addRow();
            foreach ($columns as $col) {
                $value = $this->resolveElementValue($product, $col, $language);
                $table->addCell()->addText(
                    $value,
                    ['size' => 10],
                    ['spaceAfter' => 0]
                );
            }
        }

        $section->addTextBreak();
    }

    private function renderProductBlock($section, $product, array $elements, string $language): void
    {
        foreach ($elements as $element) {
            match ($element['type']) {
                'field' => $this->renderFieldElement($section, $product, $element, $language),
                'attribute' => $this->renderAttributeElement($section, $product, $element, $language),
                'image' => $this->renderImageElement($section, $product, $element),
                'separator' => $section->addText(str_repeat('─', 60), ['size' => 6, 'color' => 'CCCCCC']),
                'pageBreak' => $section->addPageBreak(),
                'text' => $this->renderTextElement($section, $element, ['language' => $language]),
                default => null,
            };
        }
        $section->addTextBreak();
    }

    private function renderFieldElement($section, $product, array $element, string $language): void
    {
        $field = $element['field'] ?? '';
        $value = $this->elementRenderer->resolveFieldValue($product, $field, $language);
        $showLabel = $element['showLabel'] ?? true;
        $label = $element['label'] ?? $field;
        $style = $this->buildFontStyle($element['style'] ?? []);

        if ($showLabel && $label) {
            $textRun = $section->addTextRun();
            $textRun->addText("{$label}: ", array_merge($style, ['bold' => true]));
            $textRun->addText($value, $style);
        } else {
            $section->addText($value, $style);
        }
    }

    private function renderAttributeElement($section, $product, array $element, string $language): void
    {
        $attributeId = $element['attributeId'] ?? '';
        $resolved = $this->elementRenderer->resolveAttributeValue($product, $attributeId, $language);

        if (!$resolved['value'] && !$resolved['label']) {
            return;
        }

        $showLabel = $element['showLabel'] ?? true;
        $showValue = $element['showValue'] ?? true;
        $showUnit = $element['showUnit'] ?? true;
        $style = $this->buildFontStyle($element['style'] ?? []);

        $textRun = $section->addTextRun();

        if ($showLabel && $resolved['label']) {
            $textRun->addText("{$resolved['label']}: ", array_merge($style, ['bold' => true]));
        }

        if ($showValue) {
            $valueText = $resolved['value'];
            if ($showUnit && $resolved['unit']) {
                $valueText .= ' ' . $resolved['unit'];
            }
            $textRun->addText($valueText, $style);
        }
    }

    private function renderGroupFooter($section, array $group, array $definition, string $language): void
    {
        $footerElements = $definition['footer']['elements'] ?? [];

        foreach ($footerElements as $element) {
            if ($element['type'] === 'counter') {
                $count = $group['count'] ?? 0;
                $format = $element['format'] ?? '{count}';
                $text = str_replace('{count}', (string) $count, $format);
                $style = $this->buildFontStyle($element['style'] ?? []);
                $section->addText($text, array_merge($style, ['italic' => true, 'color' => '666666']));
            } elseif ($element['type'] === 'separator') {
                $section->addText(str_repeat('─', 60), ['size' => 6, 'color' => 'CCCCCC']);
            } else {
                $this->renderElementInSection($section, $element, [
                    'count' => (string) ($group['count'] ?? 0),
                ], $language);
            }
        }
    }

    private function renderElementInSection($section, array $element, array $context, string $language): void
    {
        match ($element['type']) {
            'text' => $this->renderTextElement($section, $element, array_merge($context, ['language' => $language])),
            'separator' => $section->addText(str_repeat('─', 60), ['size' => 6, 'color' => 'CCCCCC']),
            'pageBreak' => $section->addPageBreak(),
            default => null,
        };
    }

    private function renderTextElement($container, array $element, array $context): void
    {
        $content = $element['content'] ?? '';
        $content = $this->elementRenderer->resolveText($content, $context);
        $style = $this->buildFontStyle($element['style'] ?? []);
        $pStyle = $this->buildParagraphStyle($element['style'] ?? []);

        $container->addText($content, $style, $pStyle);
    }

    private function resolveElementValue($product, array $element, string $language): string
    {
        if ($element['type'] === 'field') {
            return $this->elementRenderer->resolveFieldValue($product, $element['field'] ?? '', $language);
        }

        if ($element['type'] === 'attribute') {
            $resolved = $this->elementRenderer->resolveAttributeValue($product, $element['attributeId'] ?? '', $language);
            $parts = [];
            if (($element['showValue'] ?? true) && $resolved['value']) {
                $parts[] = $resolved['value'];
            }
            if (($element['showUnit'] ?? true) && $resolved['unit']) {
                $parts[] = $resolved['unit'];
            }
            return implode(' ', $parts);
        }

        return '';
    }

    private function buildFontStyle(array $style): array
    {
        $fontStyle = [];

        if (isset($style['fontSize'])) {
            $fontStyle['size'] = (int) $style['fontSize'];
        }
        if (!empty($style['bold'])) {
            $fontStyle['bold'] = true;
        }
        if (!empty($style['italic'])) {
            $fontStyle['italic'] = true;
        }
        if (isset($style['color'])) {
            $fontStyle['color'] = ltrim($style['color'], '#');
        }

        return $fontStyle;
    }

    private function renderImageElement($section, $product, array $element): void
    {
        $media = $product->mediaAssignments?->first()?->media;
        if (!$media || !$media->file_name) {
            return;
        }

        $imgPath = storage_path('app/public/media/' . $media->file_name);
        if (!file_exists($imgPath)) {
            return;
        }

        $width = $element['width'] ?? 80;
        $height = $element['height'] ?? 80;

        $section->addImage($imgPath, [
            'width' => $width,
            'height' => $height,
            'wrappingStyle' => 'inline',
        ]);
    }

    private function buildParagraphStyle(array $style): array
    {
        $pStyle = [];

        if (isset($style['align'])) {
            $pStyle['alignment'] = match ($style['align']) {
                'center' => Jc::CENTER,
                'right' => Jc::END,
                default => Jc::START,
            };
        }
        if (isset($style['marginTop'])) {
            $pStyle['spaceBefore'] = (int) $style['marginTop'] * 20; // twips
        }
        if (isset($style['marginBottom'])) {
            $pStyle['spaceAfter'] = (int) $style['marginBottom'] * 20;
        }

        return $pStyle;
    }
}
