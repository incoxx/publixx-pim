<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SectionType;
use Illuminate\Database\Seeder;

/**
 * Standard-Sektionstypen (Bausteine) für strukturierten Content.
 * Im Code geseedet (is_system = true) → nicht löschbar, bilden den Grundstock.
 * Felder nutzen die data_type-Welt der Attribute (gleiche Renderer/i18n).
 */
class SectionTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->types() as $type) {
            SectionType::updateOrCreate(
                ['technical_name' => $type['technical_name']],
                array_merge($type, ['is_system' => true, 'is_active' => true]),
            );
        }
    }

    private function types(): array
    {
        return [
            [
                'technical_name' => 'hero',
                'name_de' => 'Hero-Banner',
                'name_en' => 'Hero Banner',
                'icon' => 'Sparkles',
                'category' => 'layout',
                'preview_component' => 'SectionHero',
                'sort_order' => 5,
                // Großes Aufmacher-Banner (MediaMarkt-Stil): Kicker, Schlagzeile,
                // Subline, Button. Optional ein Produkt (liefert Bild + Preis).
                'schema' => ['fields' => [
                    ['key' => 'eyebrow', 'label' => 'Kicker', 'type' => 'String', 'translatable' => true],
                    ['key' => 'headline', 'label' => 'Schlagzeile', 'type' => 'String', 'translatable' => true, 'required' => true],
                    ['key' => 'subline', 'label' => 'Unterzeile', 'type' => 'String', 'translatable' => true],
                    ['key' => 'cta_label', 'label' => 'Button-Text', 'type' => 'String', 'translatable' => true],
                    ['key' => 'cta_url', 'label' => 'Button-Ziel', 'type' => 'Link', 'link_kind' => 'Hyperlink'],
                    ['key' => 'image', 'label' => 'Bild', 'type' => 'Media'],
                    ['key' => 'product', 'label' => 'Produkt (Bild/Preis)', 'type' => 'product_ref'],
                    ['key' => 'widget', 'label' => 'Anzeige-Widget', 'type' => 'product_widget_ref'],
                ]],
            ],
            [
                'technical_name' => 'usp-strip',
                'name_de' => 'USP-Leiste',
                'name_en' => 'USP Strip',
                'icon' => 'BadgeCheck',
                'category' => 'layout',
                'preview_component' => 'SectionUspStrip',
                'sort_order' => 6,
                // Vertrauensleiste mit bis zu vier Vorteilen (Versand, Garantie …).
                'schema' => ['fields' => [
                    ['key' => 'usp1', 'label' => 'Vorteil 1', 'type' => 'String', 'translatable' => true],
                    ['key' => 'usp2', 'label' => 'Vorteil 2', 'type' => 'String', 'translatable' => true],
                    ['key' => 'usp3', 'label' => 'Vorteil 3', 'type' => 'String', 'translatable' => true],
                    ['key' => 'usp4', 'label' => 'Vorteil 4', 'type' => 'String', 'translatable' => true],
                ]],
            ],
            [
                'technical_name' => 'headline',
                'name_de' => 'Überschrift',
                'name_en' => 'Headline',
                'icon' => 'Heading',
                'category' => 'text',
                'preview_component' => 'SectionHeadline',
                'sort_order' => 10,
                'schema' => ['fields' => [
                    ['key' => 'text', 'label' => 'Text', 'type' => 'String', 'translatable' => true, 'required' => true],
                    ['key' => 'level', 'label' => 'Ebene', 'type' => 'Selection', 'options' => ['h1', 'h2', 'h3'], 'default' => 'h2'],
                ]],
            ],
            [
                'technical_name' => 'subline',
                'name_de' => 'Unterzeile',
                'name_en' => 'Subline',
                'icon' => 'Minus',
                'category' => 'text',
                'preview_component' => 'SectionSubline',
                'sort_order' => 20,
                'schema' => ['fields' => [
                    ['key' => 'text', 'label' => 'Text', 'type' => 'String', 'translatable' => true],
                ]],
            ],
            [
                'technical_name' => 'teaser',
                'name_de' => 'Teaser',
                'name_en' => 'Teaser',
                'icon' => 'Megaphone',
                'category' => 'text',
                'preview_component' => 'SectionTeaser',
                'sort_order' => 30,
                'schema' => ['fields' => [
                    ['key' => 'headline', 'label' => 'Überschrift', 'type' => 'String', 'translatable' => true],
                    ['key' => 'body', 'label' => 'Text', 'type' => 'RichText', 'translatable' => true],
                    ['key' => 'image', 'label' => 'Bild', 'type' => 'Media'],
                    ['key' => 'cta', 'label' => 'Button', 'type' => 'Link', 'link_kind' => 'Hyperlink'],
                ]],
            ],
            [
                'technical_name' => 'text',
                'name_de' => 'Fließtext',
                'name_en' => 'Text',
                'icon' => 'AlignLeft',
                'category' => 'text',
                'preview_component' => 'SectionText',
                'sort_order' => 40,
                'schema' => ['fields' => [
                    ['key' => 'body', 'label' => 'Text', 'type' => 'RichText', 'translatable' => true, 'required' => true],
                ]],
            ],
            [
                'technical_name' => 'image',
                'name_de' => 'Bild',
                'name_en' => 'Image',
                'icon' => 'Image',
                'category' => 'media',
                'preview_component' => 'SectionImage',
                'sort_order' => 50,
                'schema' => ['fields' => [
                    ['key' => 'image', 'label' => 'Bild', 'type' => 'Media', 'required' => true],
                    ['key' => 'caption', 'label' => 'Bildunterschrift', 'type' => 'String', 'translatable' => true],
                ]],
            ],
            [
                'technical_name' => 'gallery',
                'name_de' => 'Galerie',
                'name_en' => 'Gallery',
                'icon' => 'Images',
                'category' => 'media',
                'preview_component' => 'SectionGallery',
                'sort_order' => 60,
                'schema' => ['fields' => [
                    ['key' => 'images', 'label' => 'Bilder', 'type' => 'Media', 'multiple' => true],
                ]],
            ],
            [
                'technical_name' => 'video',
                'name_de' => 'Video',
                'name_en' => 'Video',
                'icon' => 'Video',
                'category' => 'media',
                'preview_component' => 'SectionVideo',
                'sort_order' => 70,
                'schema' => ['fields' => [
                    ['key' => 'video_url', 'label' => 'Video', 'type' => 'Link', 'link_kind' => 'VideoLink', 'required' => true],
                    ['key' => 'poster', 'label' => 'Vorschaubild', 'type' => 'Media'],
                    ['key' => 'caption', 'label' => 'Beschreibung', 'type' => 'String', 'translatable' => true],
                ]],
            ],
            [
                'technical_name' => 'download',
                'name_de' => 'Download',
                'name_en' => 'Download',
                'icon' => 'Download',
                'category' => 'media',
                'preview_component' => 'SectionDownload',
                'sort_order' => 80,
                'schema' => ['fields' => [
                    ['key' => 'file', 'label' => 'Datei', 'type' => 'Link', 'link_kind' => 'PdfLink', 'required' => true],
                    ['key' => 'label', 'label' => 'Bezeichnung', 'type' => 'String', 'translatable' => true],
                ]],
            ],
            [
                'technical_name' => 'link',
                'name_de' => 'Link',
                'name_en' => 'Link',
                'icon' => 'Link',
                'category' => 'layout',
                'preview_component' => 'SectionLink',
                'sort_order' => 90,
                'schema' => ['fields' => [
                    ['key' => 'label', 'label' => 'Beschriftung', 'type' => 'String', 'translatable' => true],
                    ['key' => 'url', 'label' => 'Ziel', 'type' => 'Link', 'link_kind' => 'Hyperlink', 'required' => true],
                ]],
            ],
            [
                'technical_name' => 'countdown',
                'name_de' => 'Countdown',
                'name_en' => 'Countdown',
                'icon' => 'Timer',
                'category' => 'layout',
                'preview_component' => 'SectionCountdown',
                'sort_order' => 105,
                // Zeitgesteuerter Aktions-Countdown (z. B. „Startet in 11h 36m").
                'schema' => ['fields' => [
                    ['key' => 'headline', 'label' => 'Überschrift', 'type' => 'String', 'translatable' => true],
                    ['key' => 'target_at', 'label' => 'Zieldatum/-zeit', 'type' => 'Date', 'required' => true],
                    ['key' => 'subtext', 'label' => 'Zusatztext', 'type' => 'String', 'translatable' => true],
                ]],
            ],
            [
                'technical_name' => 'promo-carousel',
                'name_de' => 'Aktions-Karussell',
                'name_en' => 'Promo Carousel',
                'icon' => 'GalleryHorizontal',
                'category' => 'layout',
                'preview_component' => 'SectionPromoCarousel',
                'is_nestable' => true, // Slides = Kind-Sektionen (z. B. cta-banner/teaser)
                'sort_order' => 112,
                'schema' => ['fields' => [
                    ['key' => 'headline', 'label' => 'Überschrift', 'type' => 'String', 'translatable' => true],
                ]],
            ],
            [
                'technical_name' => 'cta-banner',
                'name_de' => 'Call-to-Action-Banner',
                'name_en' => 'CTA Banner',
                'icon' => 'Sparkles',
                'category' => 'layout',
                'preview_component' => 'SectionCtaBanner',
                'sort_order' => 100,
                'schema' => ['fields' => [
                    ['key' => 'headline', 'label' => 'Überschrift', 'type' => 'String', 'translatable' => true],
                    ['key' => 'body', 'label' => 'Text', 'type' => 'RichText', 'translatable' => true],
                    ['key' => 'cta', 'label' => 'Button', 'type' => 'Link', 'link_kind' => 'Hyperlink'],
                    ['key' => 'background', 'label' => 'Hintergrundbild', 'type' => 'Media'],
                ]],
            ],
            [
                'technical_name' => 'columns',
                'name_de' => 'Spalten',
                'name_en' => 'Columns',
                'icon' => 'Columns',
                'category' => 'layout',
                'preview_component' => 'SectionColumns',
                'is_nestable' => true,
                'sort_order' => 110,
                'schema' => ['fields' => [
                    ['key' => 'count', 'label' => 'Spaltenanzahl', 'type' => 'Selection', 'options' => ['2', '3', '4'], 'default' => '2'],
                ]],
            ],
            [
                'technical_name' => 'spacer',
                'name_de' => 'Abstand',
                'name_en' => 'Spacer',
                'icon' => 'MoveVertical',
                'category' => 'layout',
                'preview_component' => 'SectionSpacer',
                'sort_order' => 120,
                'schema' => ['fields' => [
                    ['key' => 'size', 'label' => 'Größe', 'type' => 'Selection', 'options' => ['s', 'm', 'l', 'xl'], 'default' => 'm'],
                ]],
            ],
            // ── Commerce: native Produktintegration (Killer-Feature) ──
            [
                'technical_name' => 'product-list',
                'name_de' => 'Produktliste',
                'name_en' => 'Product List',
                'icon' => 'LayoutGrid',
                'category' => 'commerce',
                'preview_component' => 'SectionProductList',
                'sort_order' => 200,
                'schema' => ['fields' => [
                    ['key' => 'headline', 'label' => 'Überschrift', 'type' => 'String', 'translatable' => true],
                    ['key' => 'pql', 'label' => 'PQL-Filter', 'type' => 'pql'],
                    ['key' => 'layout', 'label' => 'Darstellung', 'type' => 'Selection', 'options' => ['grid', 'list'], 'default' => 'grid'],
                    ['key' => 'limit', 'label' => 'Maximale Anzahl', 'type' => 'Number', 'default' => 12],
                    ['key' => 'widget', 'label' => 'Anzeige-Widget', 'type' => 'product_widget_ref'],
                ]],
            ],
            [
                'technical_name' => 'product-teaser',
                'name_de' => 'Produkt-Teaser',
                'name_en' => 'Product Teaser',
                'icon' => 'Package',
                'category' => 'commerce',
                'preview_component' => 'SectionProductTeaser',
                'sort_order' => 210,
                'schema' => ['fields' => [
                    ['key' => 'product', 'label' => 'Produkt', 'type' => 'product_ref', 'required' => true],
                    ['key' => 'widget', 'label' => 'Anzeige-Widget', 'type' => 'product_widget_ref', 'default' => 'hero'],
                ]],
            ],
            [
                'technical_name' => 'product-gallery',
                'name_de' => 'Produkt-Galerie (Auswahl)',
                'name_en' => 'Product Gallery (Selection)',
                'icon' => 'GalleryHorizontalEnd',
                'category' => 'commerce',
                'preview_component' => 'SectionProductGallery',
                'sort_order' => 215,
                // Handkuratierte Produkt-Auswahl (Prospekt-Strecke): gezielt
                // ausgewählte, sortierbare Produkte — im Gegensatz zur
                // regelbasierten product-list (PQL).
                'schema' => ['fields' => [
                    ['key' => 'headline', 'label' => 'Überschrift', 'type' => 'String', 'translatable' => true],
                    ['key' => 'products', 'label' => 'Produkte (Auswahl)', 'type' => 'product_ref', 'multiple' => true, 'required' => true],
                    ['key' => 'layout', 'label' => 'Darstellung', 'type' => 'Selection', 'options' => ['grid', 'carousel', 'flyer'], 'default' => 'grid'],
                    ['key' => 'columns', 'label' => 'Spalten', 'type' => 'Selection', 'options' => ['2', '3', '4'], 'default' => '3'],
                    ['key' => 'show_price', 'label' => 'Preis anzeigen', 'type' => 'Flag', 'default' => true],
                    ['key' => 'widget', 'label' => 'Anzeige-Widget', 'type' => 'product_widget_ref', 'default' => 'card'],
                ]],
            ],
            [
                'technical_name' => 'category-teaser',
                'name_de' => 'Kategorie-Teaser',
                'name_en' => 'Category Teaser',
                'icon' => 'FolderTree',
                'category' => 'commerce',
                'preview_component' => 'SectionCategoryTeaser',
                'sort_order' => 220,
                'schema' => ['fields' => [
                    ['key' => 'hierarchy_node', 'label' => 'Kategorie (Hierarchieknoten)', 'type' => 'hierarchy_node_ref', 'required' => true],
                ]],
            ],
            [
                'technical_name' => 'category-grid',
                'name_de' => 'Kategorie-Kachelgrid',
                'name_en' => 'Category Grid',
                'icon' => 'LayoutGrid',
                'category' => 'commerce',
                'preview_component' => 'SectionCategoryGrid',
                'sort_order' => 230,
                // Mehrere Kategorie-Kacheln im Überblick (z. B. „Alle WM-Kategorien").
                'schema' => ['fields' => [
                    ['key' => 'headline', 'label' => 'Überschrift', 'type' => 'String', 'translatable' => true],
                    ['key' => 'categories', 'label' => 'Kategorien', 'type' => 'hierarchy_node_ref', 'multiple' => true, 'required' => true],
                    ['key' => 'columns', 'label' => 'Spalten', 'type' => 'Selection', 'options' => ['2', '3', '4'], 'default' => '2'],
                ]],
            ],
        ];
    }
}
