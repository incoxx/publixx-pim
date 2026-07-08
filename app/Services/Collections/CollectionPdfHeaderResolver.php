<?php

declare(strict_types=1);

namespace App\Services\Collections;

use App\Models\Organization;
use App\Models\PdfTemplate;
use App\Services\Report\ElementRenderer;

/**
 * Loest den Kopf-/Branding-Bereich einer Collection-PDF ueber eine referenzierte PdfTemplate
 * auf -- Nutzerentscheidung: "Eigenes Rendering, aber PdfTemplate als Datenquelle nutzen".
 * Es werden ausschliesslich die Elementtypen 'text' und 'image' unterstuetzt; 'field',
 * 'attribute', 'price' und die *_table-Typen sind Produkt-spezifisch (PdfTemplateRenderer)
 * und haben fuer eine Collection kein Aequivalent -- sie werden ignoriert, nicht gefehlert.
 *
 * ElementRenderer::resolveText() wird unveraendert wiederverwendet (bereits Produkt-agnostisch,
 * ein flaches strtr() ueber einen Aufrufer-Kontext). Die Disk-Aufloesung fuer Bilder dupliziert
 * absichtlich PdfTemplateRenderer::getMediaPath() (4 Zeilen) statt die private Methode dort
 * wiederzuverwenden -- damit bleibt das bestehende PdfTemplate-Feature unangetastet.
 */
class CollectionPdfHeaderResolver
{
    public function __construct(
        private readonly ElementRenderer $elementRenderer,
    ) {}

    /**
     * @param  array<string, string|int|float>  $textContext  Flacher Kontext fuer resolveText()
     *         (z.B. 'collection.name', 'collection.reference', 'organization.name', ...).
     * @return array List aufgeloester Elemente (nur text/image) fuer den PDF-Kopfbereich.
     */
    public function resolve(?PdfTemplate $template, array $textContext, ?Organization $organization): array
    {
        if ($template === null) {
            return [];
        }

        $elements = $template->template_json['elements'] ?? [];
        $resolved = [];

        foreach ($elements as $element) {
            $type = $element['type'] ?? 'text';

            $out = match ($type) {
                'text' => $this->resolveTextElement($element, $textContext),
                'image' => $this->resolveImageElement($element, $organization),
                default => null,
            };

            if ($out !== null) {
                $resolved[] = $out;
            }
        }

        return $resolved;
    }

    private function resolveTextElement(array $element, array $textContext): array
    {
        $content = $this->elementRenderer->resolveText($element['content'] ?? '', $textContext);

        return array_merge($element, ['displayValue' => $content]);
    }

    private function resolveImageElement(array $element, ?Organization $organization): array
    {
        $media = $organization?->logoMedia;

        if ($media === null) {
            return array_merge($element, ['resolvedImages' => []]);
        }

        return array_merge($element, ['resolvedImages' => [$this->getMediaPath($media)]]);
    }

    private function getMediaPath($media): string
    {
        $disk = $media->disk ?? 'public';
        $path = $media->file_path ?? '';

        if ($disk === 'public') {
            return storage_path('app/public/' . $path);
        }

        return storage_path('app/' . $path);
    }
}
