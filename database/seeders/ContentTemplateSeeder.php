<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ContentPage;
use App\Models\ContentSection;
use App\Models\ContentType;
use App\Models\HierarchyNode;
use App\Models\Navigation;
use App\Models\NavigationNode;
use App\Models\Product;
use App\Models\SectionType;
use Illuminate\Database\Seeder;

/**
 * Vordefinierte Seiten-Templates (Startvorlagen) für strukturierten Content.
 *
 * Erstellt fertig komponierte Beispielseiten (Startseite, MediaMarkt-artige
 * Kampagnenseite, Impressum) und hängt sie in die 'main'-Navigation ein —
 * macht die Website-Vorschau sofort erlebbar und dient als Kopiervorlage.
 *
 * Idempotent: Seiten werden per Slug nur einmal angelegt; Sektionen nur bei
 * Neuanlage. Referenziert vorhandene Demo-Produkte/-Kategorien, falls vorhanden.
 */
class ContentTemplateSeeder extends Seeder
{
    /** @var list<string> */
    private array $productIds = [];

    /** @var list<string> */
    private array $categoryIds = [];

    public function run(): void
    {
        // Demo-Referenzen einsammeln (leere Arrays sind unkritisch).
        $this->productIds = Product::query()->limit(4)->pluck('id')->all();
        $this->categoryIds = HierarchyNode::query()->whereNull('parent_node_id')->limit(4)->pluck('id')->all();

        $this->seedHome();
        $this->seedCampaign();
        $this->seedImprint();
        $this->seedNavigation();
    }

    // ─── Startseite (Teaserseite) ───────────────────────────────────

    private function seedHome(): void
    {
        $page = $this->page('teaser-page', 'home', 'Startseite');
        if (!$page || !$page->wasRecentlyCreated) {
            return;
        }

        $o = 0;
        $this->section($page, 'headline', ['de' => ['text' => 'Willkommen', 'level' => 'h1'], 'en' => ['text' => 'Welcome', 'level' => 'h1']], $o++);
        $this->section($page, 'teaser', ['de' => ['headline' => 'Alles aus einer Quelle', 'body' => 'Produkte, Inhalte und Navigation – strukturiert in einem System.']], $o++);
        $this->section($page, 'product-gallery', [
            'de' => ['headline' => 'Unsere Empfehlungen'],
            '_' => ['products' => $this->productIds, 'layout' => 'grid', 'columns' => '3', 'show_price' => true],
        ], $o++);
        $this->section($page, 'category-grid', [
            'de' => ['headline' => 'Kategorien im Überblick'],
            '_' => ['categories' => $this->categoryIds, 'columns' => '2'],
        ], $o++);
        $this->section($page, 'cta-banner', ['de' => ['headline' => 'Jetzt entdecken', 'body' => 'Zur kompletten Produktwelt.']], $o++);
    }

    // ─── Kampagnenseite (MediaMarkt-artig) ──────────────────────────

    private function seedCampaign(): void
    {
        $page = $this->page('campaign-page', 'wm-aktion', 'WM-Aktion', ['valid_to' => now()->addDays(7)]);
        if (!$page || !$page->wasRecentlyCreated) {
            return;
        }

        $o = 0;
        $this->section($page, 'cta-banner', ['de' => ['headline' => 'Wahnsinns-WM-Angebote', 'body' => 'Nur solange der Ball rollt!']], $o++);
        $this->section($page, 'countdown', [
            'de' => ['headline' => 'Deals zum Spiel', 'subtext' => 'Nur für kurze Zeit!'],
            '_' => ['target_at' => now()->addDay()->toIso8601String()],
        ], $o++);
        $this->section($page, 'category-grid', [
            'de' => ['headline' => 'Alle WM-Kategorien im Überblick'],
            '_' => ['categories' => $this->categoryIds, 'columns' => '2'],
        ], $o++);
        $this->section($page, 'product-gallery', [
            'de' => ['headline' => 'Unsere WM-Highlights'],
            '_' => ['products' => $this->productIds, 'layout' => 'flyer', 'columns' => '3', 'show_price' => true],
        ], $o++);

        // Aktions-Karussell mit zwei Slides (Kind-Sektionen).
        $carousel = $this->section($page, 'promo-carousel', ['de' => ['headline' => 'Weitere Angebote & Aktionen']], $o++);
        if ($carousel) {
            $this->section($page, 'cta-banner', ['de' => ['headline' => 'Deals zum Spiel', 'body' => 'Jetzt mitfiebern und sparen …']], 0, $carousel->id);
            $this->section($page, 'cta-banner', ['de' => ['headline' => 'Unsere Services zur WM', 'body' => 'Aufbau, Lieferung & mehr.']], 1, $carousel->id);
        }

        $this->section($page, 'teaser', ['de' => ['headline' => 'Unser Prospekt', 'body' => 'Jetzt durchblättern.']], $o++);
    }

    // ─── Impressum (rechtliche Seite) ───────────────────────────────

    private function seedImprint(): void
    {
        $page = $this->page('imprint', 'impressum', 'Impressum');
        if (!$page || !$page->wasRecentlyCreated) {
            return;
        }

        $o = 0;
        $this->section($page, 'headline', ['de' => ['text' => 'Impressum', 'level' => 'h1']], $o++);
        $this->section($page, 'text', ['de' => ['body' => 'Angaben gemäß § 5 TMG. Diese Seite ist eine Vorlage und kann im Content-Editor angepasst werden.']], $o++);
    }

    // ─── Navigation verdrahten ──────────────────────────────────────

    private function seedNavigation(): void
    {
        $nav = Navigation::where('technical_name', 'main')->first();
        if (!$nav || $nav->nodes()->exists()) {
            return; // nur befüllen, wenn die Hauptnavigation noch leer ist
        }

        $this->navNode($nav, 'Start', 0, 'content_page', ['slug' => 'home', 'content_page_id' => $this->pageId('home')]);
        $this->navNode($nav, 'WM-Aktion', 1, 'content_page', ['slug' => 'wm-aktion', 'content_page_id' => $this->pageId('wm-aktion')]);

        if (!empty($this->categoryIds)) {
            $this->navNode($nav, 'Produkte', 2, 'product_category', [
                'slug' => 'produkte',
                'hierarchy_node_id' => $this->categoryIds[0],
                'auto_expand' => true,
            ]);
        }

        $legal = $this->navNode($nav, 'Rechtliches', 3, 'folder', ['slug' => 'rechtliches']);
        if ($legal) {
            $this->navNode($nav, 'Impressum', 0, 'content_page', ['slug' => 'impressum', 'content_page_id' => $this->pageId('impressum')], $legal->id);
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private function page(string $typeTechnicalName, string $slug, string $title, array $extra = []): ?ContentPage
    {
        $type = ContentType::where('technical_name', $typeTechnicalName)->first();
        if (!$type) {
            return null;
        }

        return ContentPage::firstOrCreate(
            ['slug' => $slug],
            array_merge(['content_type_id' => $type->id, 'title' => $title, 'status' => 'active'], $extra),
        );
    }

    private function section(ContentPage $page, string $typeName, array $buckets, int $order, ?string $parentId = null): ?ContentSection
    {
        $type = SectionType::where('technical_name', $typeName)->first();
        if (!$type) {
            return null;
        }

        return ContentSection::create([
            'content_page_id' => $page->id,
            'section_type_id' => $type->id,
            'parent_section_id' => $parentId,
            'sort_order' => $order,
            'values_json' => $buckets,
        ]);
    }

    private function navNode(Navigation $nav, string $label, int $order, string $targetType, array $extra = [], ?string $parentId = null): ?NavigationNode
    {
        $node = NavigationNode::create(array_merge([
            'navigation_id' => $nav->id,
            'parent_node_id' => $parentId,
            'label_de' => $label,
            'target_type' => $targetType,
            'sort_order' => $order,
            'path' => '/', // temporär; refreshTreePath berechnet den echten Pfad
        ], $extra));

        $node->refreshTreePath();

        return $node;
    }

    private function pageId(string $slug): ?string
    {
        return ContentPage::where('slug', $slug)->value('id');
    }
}
