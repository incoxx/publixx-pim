<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ContentPage;
use App\Models\ContentSection;
use App\Models\ContentType;
use App\Models\Navigation;
use App\Models\NavigationNode;
use App\Models\Product;
use App\Models\ProductWidget;
use App\Models\SectionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SmartOne-Showcase: ein kompletter, sichtbarer Content-Aufbau für das
 * SmartOne-Smartphone-Portfolio (Flaggschiffe, Zubehör, Ersatzteile).
 *
 * Legt SmartOne-spezifische Produkt-Widgets, einen Seitentyp, eine
 * Landingpage mit kuratierten Sektionen (an echte Produkte per SKU gebunden)
 * sowie eine Navigation mit eigenem Theme an. Idempotent: per technical_name
 * bzw. Slug ge-upsertet, Sektions-/Knotenbäume werden sauber neu aufgebaut.
 *
 * Aufruf:  php artisan db:seed --class=Database\\Seeders\\SmartOneShowcaseSeeder
 */
class SmartOneShowcaseSeeder extends Seeder
{
    /** Preis kommt aus dem Nettolistenpreis, Bild aus dem Teaser-Slot. */
    private const PRICE = 'prices:net_list';
    private const IMAGE = 'media:teaser';

    public function run(): void
    {
        // System-Sektionstypen sicherstellen (headline, product-teaser,
        // product-gallery, countdown, cta-banner …). Ohne sie würden die
        // Sektionen mangels section_type_id übersprungen → leere Seite.
        $this->call(SectionTypeSeeder::class);

        $this->seedWidgets();
        $type = $this->seedContentType();
        $skuToId = $this->resolveProducts();

        $this->command?->info('SmartOne-Produkte aufgelöst: ' . count($skuToId) . ' von 16 erwarteten SKUs.');

        $page = $this->seedPage($type);
        $this->seedSections($page, $skuToId);
        $this->seedNavigation($page, $skuToId);

        $this->command?->info("Showcase fertig. Vorschau: /website-preview?nav=smartone (Slug: {$page->slug}).");
    }

    // ─── 1. Produkt-Widgets (SmartOne-spezifisch, löschbar) ───────────

    private function seedWidgets(): void
    {
        $widgets = [
            [
                'technical_name' => 'smartone-hero',
                'name_de' => 'SmartOne Hero',
                'name_en' => 'SmartOne Hero',
                'description' => 'Großes Flaggschiff-Banner: Bild, Name, Kurzbeschreibung, Preis, Button.',
                'sort_order' => 10,
                'config' => [
                    'fields' => [
                        ['role' => 'image', 'source' => self::IMAGE, 'type' => 'media_url'],
                        ['role' => 'title', 'source' => 'product:name', 'type' => 'text'],
                        ['role' => 'subtitle', 'source' => 'attribute:description_short', 'type' => 'text'],
                        ['role' => 'badge', 'source' => 'attribute:netzstandard', 'type' => 'text'],
                        ['role' => 'price', 'source' => self::PRICE, 'type' => 'price'],
                    ],
                    'show_sku' => false,
                    'image_ratio' => '16/9',
                    'cta' => ['enabled' => true, 'label' => 'Jetzt entdecken'],
                ],
            ],
            [
                'technical_name' => 'smartone-phone-card',
                'name_de' => 'SmartOne Modellkarte',
                'name_en' => 'SmartOne Phone Card',
                'description' => 'Smartphone-Kachel mit Bild, Name, Kurzbeschreibung, 5G-Badge und Preis.',
                'sort_order' => 20,
                'config' => [
                    'fields' => [
                        ['role' => 'image', 'source' => self::IMAGE, 'type' => 'media_url'],
                        ['role' => 'badge', 'source' => 'attribute:netzstandard', 'type' => 'text'],
                        ['role' => 'title', 'source' => 'product:name', 'type' => 'text'],
                        ['role' => 'subtitle', 'source' => 'attribute:description_short', 'type' => 'text'],
                        ['role' => 'price', 'source' => self::PRICE, 'type' => 'price'],
                    ],
                    'show_sku' => false,
                    'image_ratio' => '3/4',
                    'cta' => ['enabled' => true, 'label' => 'Details'],
                ],
            ],
            [
                'technical_name' => 'smartone-accessory-card',
                'name_de' => 'SmartOne Zubehörkarte',
                'name_en' => 'SmartOne Accessory Card',
                'description' => 'Kompakte Zubehörkachel: Bild, Name und Preis.',
                'sort_order' => 30,
                'config' => [
                    'fields' => [
                        ['role' => 'image', 'source' => self::IMAGE, 'type' => 'media_url'],
                        ['role' => 'title', 'source' => 'product:name', 'type' => 'text'],
                        ['role' => 'price', 'source' => self::PRICE, 'type' => 'price'],
                    ],
                    'show_sku' => false,
                    'image_ratio' => '1/1',
                    'cta' => ['enabled' => false],
                ],
            ],
            [
                'technical_name' => 'smartone-part-row',
                'name_de' => 'SmartOne Ersatzteil-Zeile',
                'name_en' => 'SmartOne Part Row',
                'description' => 'Ersatzteil-Listenzeile mit Bild, Name, SKU und Preis.',
                'sort_order' => 40,
                'config' => [
                    'fields' => [
                        ['role' => 'image', 'source' => self::IMAGE, 'type' => 'media_url'],
                        ['role' => 'title', 'source' => 'product:name', 'type' => 'text'],
                        ['role' => 'price', 'source' => self::PRICE, 'type' => 'price'],
                    ],
                    'show_sku' => true,
                    'image_ratio' => '1/1',
                    'cta' => ['enabled' => false],
                ],
            ],
        ];

        foreach ($widgets as $w) {
            ProductWidget::updateOrCreate(
                ['technical_name' => $w['technical_name']],
                array_merge($w, ['is_system' => false, 'is_active' => true]),
            );
        }
    }

    // ─── 2. Seitentyp (Produkt-Landingpage) ───────────────────────────

    private function seedContentType(): ContentType
    {
        $allowed = [
            'headline', 'subline', 'teaser', 'text', 'image', 'gallery', 'cta-banner', 'spacer',
            'product-teaser', 'product-gallery', 'product-list', 'countdown', 'promo-carousel',
        ];

        return ContentType::updateOrCreate(
            ['technical_name' => 'smartone-landing'],
            [
                'name_de' => 'SmartOne Landingpage',
                'name_en' => 'SmartOne Landing Page',
                'icon' => 'Smartphone',
                'color' => '#4F46E5',
                'layout_hint' => 'landing',
                'allowed_section_types' => $allowed,
                'default_sections' => [
                    ['section_type' => 'headline'],
                    ['section_type' => 'product-teaser'],
                    ['section_type' => 'product-gallery'],
                    ['section_type' => 'cta-banner'],
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
    }

    // ─── 3. Produkte per SKU auflösen ─────────────────────────────────

    /** @return array<string,string>  sku → product id */
    private function resolveProducts(): array
    {
        $skus = [
            '10001', '10002', '10003', '10004', '10005',          // Smartphones
            '20001', '20002', '20003', '20004', '20005',          // Hüllen
            '20011', '20012', '20013', '20014',                   // Displayschutz
            '20021', '20022', '20023', '20031', '20032',          // Laden & Audio
            '20041', '20042',                                     // Halterung & Dock
            '30001', '30002', '30003', '30004', '30005', '30006', // Ersatzteile
        ];

        return Product::whereIn('sku', $skus)->pluck('id', 'sku')->all();
    }

    // ─── 4. Landingpage ───────────────────────────────────────────────

    private function seedPage(ContentType $type): ContentPage
    {
        return ContentPage::updateOrCreate(
            ['slug' => 'smartone'],
            [
                'content_type_id' => $type->id,
                'title' => 'SmartOne – Dein Smartphone. Neu gedacht.',
                'status' => 'active',
                'valid_from' => null,
                'valid_to' => null,
                'seo_title_json' => ['de' => 'SmartOne Smartphones, Zubehör & Ersatzteile'],
                'seo_description_json' => ['de' => 'Die komplette SmartOne-Serie: Flaggschiffe, passendes Zubehör und Originalersatzteile.'],
            ],
        );
    }

    // ─── 5. Sektionen (kuratiert, an echte Produkte gebunden) ─────────

    private function seedSections(ContentPage $page, array $sku): void
    {
        // Map sku-Liste → vorhandene Produkt-IDs (fehlende SKUs überspringen)
        $ids = fn (array $list) => array_values(array_filter(array_map(fn ($s) => $sku[$s] ?? null, $list)));

        $phones = $ids(['10001', '10002', '10003', '10004', '10005']);
        $cases = $ids(['20001', '20002', '20003', '20004', '20005']);
        $protectors = $ids(['20011', '20012', '20013', '20014']);
        $power = $ids(['20021', '20022', '20023', '20031', '20032', '20041', '20042']);
        $parts = $ids(['30001', '30002', '30003', '30004', '30005', '30006']);
        $heroId = $sku['10005'] ?? $sku['10002'] ?? ($phones[0] ?? null);

        $blocks = [];

        $blocks[] = $this->block('headline',
            de: ['text' => 'SmartOne – Dein Smartphone. Neu gedacht.'],
            neutral: ['level' => 'h1']);

        $blocks[] = $this->block('subline',
            de: ['text' => 'Fünf Modelle, durchdachtes Zubehör und Originalersatzteile – alles aus einer Hand.']);

        if ($heroId) {
            $blocks[] = $this->block('product-teaser',
                neutral: ['product' => $heroId, 'widget' => 'smartone-hero']);
        }

        $blocks[] = $this->block('headline',
            de: ['text' => 'Die Modellreihe'], neutral: ['level' => 'h2']);

        if ($phones) {
            $blocks[] = $this->block('product-gallery',
                de: ['headline' => 'Für jeden das passende SmartOne'],
                neutral: ['products' => $phones, 'widget' => 'smartone-phone-card',
                    'layout' => 'grid', 'columns' => '3', 'show_price' => true]);
        }

        $blocks[] = $this->block('countdown',
            de: ['headline' => 'SmartOne Launch-Wochen', 'subtext' => 'Aktionspreise nur für kurze Zeit.'],
            neutral: ['target_at' => Carbon::now()->addDays(14)->startOfHour()->toIso8601String()]);

        $blocks[] = $this->block('cta-banner',
            de: ['headline' => 'Wechseln leicht gemacht',
                'body' => 'Alte Geräte in Zahlung geben und beim Umstieg auf SmartOne sparen.',
                'cta' => '/smartone'],
            neutral: ['cta_label' => 'Mehr erfahren']);

        $blocks[] = $this->block('headline',
            de: ['text' => 'Passendes Zubehör'], neutral: ['level' => 'h2']);

        if ($cases) {
            $blocks[] = $this->block('product-gallery',
                de: ['headline' => 'Hüllen & Cases'],
                neutral: ['products' => $cases, 'widget' => 'smartone-accessory-card',
                    'layout' => 'grid', 'columns' => '4', 'show_price' => true]);
        }

        if ($protectors) {
            $blocks[] = $this->block('product-gallery',
                de: ['headline' => 'Displayschutz'],
                neutral: ['products' => $protectors, 'widget' => 'smartone-accessory-card',
                    'layout' => 'grid', 'columns' => '4', 'show_price' => true]);
        }

        if ($power) {
            $blocks[] = $this->block('product-gallery',
                de: ['headline' => 'Laden, Audio & Halterungen'],
                neutral: ['products' => $power, 'widget' => 'smartone-accessory-card',
                    'layout' => 'grid', 'columns' => '4', 'show_price' => true]);
        }

        $blocks[] = $this->block('headline',
            de: ['text' => 'Originalersatzteile & Reparatur'], neutral: ['level' => 'h2']);

        if ($parts) {
            $blocks[] = $this->block('product-gallery',
                de: ['headline' => 'Reparieren statt wegwerfen'],
                neutral: ['products' => $parts, 'widget' => 'smartone-part-row',
                    'layout' => 'grid', 'columns' => '3', 'show_price' => true]);
        }

        $blocks[] = $this->block('cta-banner',
            de: ['headline' => 'Fragen zu SmartOne?',
                'body' => 'Unser Support hilft bei Auswahl, Einrichtung und Reparatur weiter.',
                'cta' => 'mailto:support@smartone.example'],
            neutral: ['cta_label' => 'Support kontaktieren']);

        // Sektionen sauber neu aufbauen (idempotent)
        DB::transaction(function () use ($page, $blocks) {
            $page->sections()->delete();

            $typeIdByName = SectionType::pluck('id', 'technical_name');
            foreach ($blocks as $i => $b) {
                $stId = $typeIdByName[$b['type']] ?? null;
                if (!$stId) {
                    continue; // Sektionstyp nicht vorhanden → überspringen
                }
                ContentSection::create([
                    'content_page_id' => $page->id,
                    'section_type_id' => $stId,
                    'parent_section_id' => null,
                    'sort_order' => $i,
                    'is_visible' => true,
                    'values_json' => $b['values'],
                ]);
            }
        });
    }

    /**
     * Sektions-Wert-Buckets bauen: übersetzbare Felder unter "de",
     * sprachneutrale (Produkt-Refs, Widget, Layout) unter "_".
     */
    private function block(string $type, array $de = [], array $neutral = []): array
    {
        $values = [];
        if ($neutral) {
            $values['_'] = $neutral;
        }
        if ($de) {
            $values['de'] = $de;
        }

        return ['type' => $type, 'values' => $values];
    }

    // ─── 6. Navigation + Theme ────────────────────────────────────────

    private function seedNavigation(ContentPage $page, array $sku): void
    {
        $nav = Navigation::updateOrCreate(
            ['technical_name' => 'smartone'],
            [
                'name_de' => 'SmartOne Shop',
                'name_en' => 'SmartOne Shop',
                'locale_set' => ['de', 'en'],
                'is_primary' => false,
                // Modernes Tech-Theme (Indigo/Cyan)
                'theme_json' => [
                    'primary' => '#4F46E5',
                    'on_primary' => '#FFFFFF',
                    'accent' => '#06B6D4',
                    'text' => '#0F172A',
                    'muted' => '#64748B',
                    'background' => '#F1F5F9',
                    'surface' => '#FFFFFF',
                    'border' => '#E2E8F0',
                    'radius' => '1.25rem',
                    'font' => 'system-ui, sans-serif',
                ],
            ],
        );

        DB::transaction(function () use ($nav, $page, $sku) {
            $nav->nodes()->delete();

            // Wurzel: Startseite (verweist auf die Landingpage)
            $start = $this->node($nav->id, null, 0, 'Start', [
                'slug' => $page->slug,
                'target_type' => 'content_page',
                'content_page_id' => $page->id,
                'icon' => 'Home',
            ]);

            // Smartphones (Ordner) mit den fünf Modellen als Produkt-Knoten
            $phones = $this->node($nav->id, null, 1, 'Smartphones', ['icon' => 'Smartphone', 'auto_expand' => true]);
            $labels = [
                '10001' => 'SmartOne X', '10002' => 'SmartOne X Pro', '10003' => 'SmartOne Lite',
                '10004' => 'SmartOne Compact', '10005' => 'SmartOne Ultra',
            ];
            $order = 0;
            foreach ($labels as $s => $label) {
                if (!isset($sku[$s])) {
                    continue;
                }
                $this->node($nav->id, $phones->id, $order++, $label, [
                    'target_type' => 'product',
                    'product_id' => $sku[$s],
                ]);
            }

            // Zubehör & Ersatzteile als Ordner (Inhalt folgt über Kategorien/Seiten)
            $this->node($nav->id, null, 2, 'Zubehör', ['icon' => 'Headphones']);
            $this->node($nav->id, null, 3, 'Ersatzteile & Reparatur', ['icon' => 'Wrench']);
        });
    }

    private function node(string $navId, ?string $parentId, int $sort, string $label, array $extra = []): NavigationNode
    {
        $node = NavigationNode::create(array_merge([
            'navigation_id' => $navId,
            'parent_node_id' => $parentId,
            'path' => '/', // wird durch refreshTreePath() ersetzt
            'sort_order' => $sort,
            'label_de' => $label,
            'is_visible' => true,
            'target_type' => 'folder',
        ], $extra));

        $node->refreshTreePath();

        return $node;
    }
}
