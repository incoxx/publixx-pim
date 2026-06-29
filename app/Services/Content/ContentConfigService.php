<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\ContentPage;
use App\Models\ContentType;
use App\Models\Navigation;
use App\Models\NavigationNode;
use App\Models\ProductWidget;
use App\Models\SectionType;
use Illuminate\Support\Facades\DB;

/**
 * Export/Import der gesamten Content-Konfiguration als portables JSON-Bundle.
 *
 * Zweck: Layouts (Seitentypen, Sektionstypen, Produkt-Widgets, Navigation +
 * Theme) lassen sich exportieren, per KI/Claude gestalten und idempotent
 * zurückspielen. Natürlicher Schlüssel ist überall `technical_name`
 * (Upsert: vorhandene Einträge werden aktualisiert, neue angelegt — kein
 * Duplizieren). Instanz-spezifische IDs (UUID, Timestamps, workflow_id)
 * werden bewusst NICHT exportiert.
 */
class ContentConfigService
{
    public const FORMAT_VERSION = '1.0';

    /** Exportierbare Felder je Entität (id/timestamps/FK bewusst ausgelassen). */
    private const CONTENT_TYPE_FIELDS = [
        'technical_name', 'name_de', 'name_en', 'name_json', 'description', 'icon',
        'color', 'allowed_section_types', 'default_sections', 'layout_hint',
        'is_active', 'sort_order',
    ];

    private const SECTION_TYPE_FIELDS = [
        'technical_name', 'name_de', 'name_en', 'name_json', 'icon', 'category',
        'schema', 'is_repeatable', 'is_nestable', 'preview_component',
        'is_system', 'is_active', 'sort_order',
    ];

    private const PRODUCT_WIDGET_FIELDS = [
        'technical_name', 'name_de', 'name_en', 'name_json', 'description',
        'config', 'is_system', 'is_active', 'sort_order',
    ];

    private const NAVIGATION_FIELDS = [
        'technical_name', 'name_de', 'name_en', 'name_json', 'locale_set',
        'theme_json', 'is_primary',
    ];

    private const NAVIGATION_NODE_FIELDS = [
        'label_de', 'label_en', 'label_json', 'slug', 'icon', 'is_visible',
        'target_type', 'external_url', 'auto_expand', 'expand_depth', 'sort_order',
    ];

    /**
     * Konfiguration einlesen und als strukturiertes Array zurückgeben.
     *
     * @param  array<string>  $sections  Teilmengen-Auswahl (leer = alles).
     *                                    Erlaubt: content_types, section_types,
     *                                    product_widgets, navigations
     */
    public function export(array $sections = []): array
    {
        $want = fn (string $key) => $sections === [] || in_array($key, $sections, true);

        $bundle = [
            'anypim_content_config' => self::FORMAT_VERSION,
            'generator' => 'anyPIM',
        ];

        if ($want('content_types')) {
            $bundle['content_types'] = ContentType::query()
                ->orderBy('sort_order')->orderBy('technical_name')->get()
                ->map(fn (ContentType $t) => $this->only($t, self::CONTENT_TYPE_FIELDS))
                ->all();
        }

        if ($want('section_types')) {
            $bundle['section_types'] = SectionType::query()
                ->orderBy('sort_order')->orderBy('technical_name')->get()
                ->map(fn (SectionType $t) => $this->only($t, self::SECTION_TYPE_FIELDS))
                ->all();
        }

        if ($want('product_widgets')) {
            $bundle['product_widgets'] = ProductWidget::query()
                ->orderBy('sort_order')->orderBy('technical_name')->get()
                ->map(fn (ProductWidget $w) => $this->only($w, self::PRODUCT_WIDGET_FIELDS))
                ->all();
        }

        if ($want('navigations')) {
            $bundle['navigations'] = Navigation::query()
                ->orderBy('technical_name')->get()
                ->map(fn (Navigation $n) => $this->exportNavigation($n))
                ->all();
        }

        return $bundle;
    }

    /**
     * Bundle importieren (idempotenter Upsert per technical_name).
     *
     * @param  array  $bundle   Dekodiertes JSON-Bundle
     * @param  array<string>  $sections  Teilmengen-Auswahl (leer = alles)
     * @return array  Zusammenfassung je Entität: created/updated
     */
    public function import(array $bundle, array $sections = []): array
    {
        if (($bundle['anypim_content_config'] ?? null) === null) {
            throw new \InvalidArgumentException('Kein gültiges anyPIM-Content-Konfigurationsbundle (Feld "anypim_content_config" fehlt).');
        }

        $want = fn (string $key) => $sections === [] || in_array($key, $sections, true);
        $summary = [];

        DB::transaction(function () use ($bundle, $want, &$summary) {
            if ($want('content_types') && isset($bundle['content_types'])) {
                $summary['content_types'] = $this->importRecords(
                    ContentType::class, $bundle['content_types'], self::CONTENT_TYPE_FIELDS
                );
            }
            if ($want('section_types') && isset($bundle['section_types'])) {
                $summary['section_types'] = $this->importRecords(
                    SectionType::class, $bundle['section_types'], self::SECTION_TYPE_FIELDS
                );
            }
            if ($want('product_widgets') && isset($bundle['product_widgets'])) {
                $summary['product_widgets'] = $this->importRecords(
                    ProductWidget::class, $bundle['product_widgets'], self::PRODUCT_WIDGET_FIELDS
                );
            }
            if ($want('navigations') && isset($bundle['navigations'])) {
                $summary['navigations'] = $this->importNavigations($bundle['navigations']);
            }
        });

        return $summary;
    }

    // ─────────────────────────────────────────────────────────────────────

    /** Nur die Whitelist-Felder aus einem Model ziehen (Reihenfolge stabil). */
    private function only(\Illuminate\Database\Eloquent\Model $model, array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            $out[$f] = $model->getAttribute($f);
        }

        return $out;
    }

    private function exportNavigation(Navigation $nav): array
    {
        $data = $this->only($nav, self::NAVIGATION_FIELDS);

        // Alle Knoten laden und in-memory zum Baum verschachteln (kein N+1).
        $nodes = $nav->nodes()->orderBy('sort_order')->get();
        $data['nodes'] = $this->buildNodeTree($nodes, null);

        return $data;
    }

    /**
     * Knoten-Liste rekursiv zum Baum zusammensetzen. Ziel-Referenzen werden
     * portabel exportiert: Content-Seiten per Slug, externe Links direkt.
     * Produkt-/Kategorie-Mountpoints sind PIM-Daten-abhängig und werden als
     * Hinweis ohne harte ID exportiert (beim Import ggf. zu 'folder').
     */
    private function buildNodeTree($nodes, ?string $parentId): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if ($node->parent_node_id !== $parentId) {
                continue;
            }
            $entry = $this->only($node, self::NAVIGATION_NODE_FIELDS);

            // Content-Seite portabel per Slug referenzieren
            if ($node->target_type === 'content_page' && $node->content_page_id) {
                $entry['content_page_slug'] = ContentPage::query()
                    ->whereKey($node->content_page_id)->value('slug');
            }

            $entry['children'] = $this->buildNodeTree($nodes, $node->id);
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * Datensätze upserten. is_system wird beim Anlegen NIE gesetzt (importierte
     * Einträge bleiben löschbar); bei vorhandenen Einträgen bleibt der Wert
     * unangetastet.
     */
    private function importRecords(string $modelClass, array $records, array $fields): array
    {
        $created = 0;
        $updated = 0;

        foreach ($records as $record) {
            $key = $record['technical_name'] ?? null;
            if (! is_string($key) || $key === '') {
                continue; // ohne natürlichen Schlüssel nicht importierbar
            }

            $attrs = [];
            foreach ($fields as $f) {
                if ($f === 'technical_name' || $f === 'is_system') {
                    continue;
                }
                if (array_key_exists($f, $record)) {
                    $attrs[$f] = $record[$f];
                }
            }

            $existing = $modelClass::query()->where('technical_name', $key)->first();
            if ($existing) {
                $existing->update($attrs);
                $updated++;
            } else {
                $attrs['technical_name'] = $key;
                if (in_array('is_system', $fields, true)) {
                    $attrs['is_system'] = false;
                }
                $modelClass::query()->create($attrs);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Navigationen upserten. Wenn das Bundle für eine Navigation `nodes`
     * mitliefert, wird deren Baum vollständig ersetzt (struktureller Import).
     */
    private function importNavigations(array $navigations): array
    {
        $created = 0;
        $updated = 0;
        $nodeCount = 0;

        foreach ($navigations as $navData) {
            $key = $navData['technical_name'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            $attrs = [];
            foreach (self::NAVIGATION_FIELDS as $f) {
                if ($f === 'technical_name') {
                    continue;
                }
                if (array_key_exists($f, $navData)) {
                    $attrs[$f] = $navData[$f];
                }
            }

            $nav = Navigation::query()->where('technical_name', $key)->first();
            if ($nav) {
                $nav->update($attrs);
                $updated++;
            } else {
                $attrs['technical_name'] = $key;
                $nav = Navigation::query()->create($attrs);
                $created++;
            }

            if (array_key_exists('nodes', $navData) && is_array($navData['nodes'])) {
                // Bestehenden Baum ersetzen (CASCADE räumt Kinder ab).
                $nav->nodes()->delete();
                $nodeCount += $this->importNodeTree($nav, $navData['nodes'], null);
            }
        }

        return ['created' => $created, 'updated' => $updated, 'nodes' => $nodeCount];
    }

    /** Knoten-Baum rekursiv anlegen, Pfad/Tiefe über den Tree-Trait setzen. */
    private function importNodeTree(Navigation $nav, array $nodes, ?string $parentId): int
    {
        $count = 0;
        foreach ($nodes as $i => $nodeData) {
            $attrs = ['navigation_id' => $nav->id, 'parent_node_id' => $parentId, 'path' => '/'];
            foreach (self::NAVIGATION_NODE_FIELDS as $f) {
                if (array_key_exists($f, $nodeData)) {
                    $attrs[$f] = $nodeData[$f];
                }
            }
            $attrs['sort_order'] = $nodeData['sort_order'] ?? $i;

            // Content-Seite per Slug auflösen; nicht auflösbare Ziele → 'folder'
            if (($nodeData['target_type'] ?? null) === 'content_page') {
                $slug = $nodeData['content_page_slug'] ?? null;
                $pageId = $slug ? ContentPage::query()->where('slug', $slug)->value('id') : null;
                if ($pageId) {
                    $attrs['content_page_id'] = $pageId;
                } else {
                    $attrs['target_type'] = 'folder';
                }
            } elseif (in_array($nodeData['target_type'] ?? null, ['product', 'product_category'], true)) {
                // PIM-Daten-abhängige Mountpoints lassen sich nicht portabel
                // übertragen → als Ordner anlegen.
                $attrs['target_type'] = 'folder';
            }

            $node = NavigationNode::create($attrs);
            $node->refreshTreePath();
            $count++;

            if (! empty($nodeData['children']) && is_array($nodeData['children'])) {
                $count += $this->importNodeTree($nav, $nodeData['children'], $node->id);
            }
        }

        return $count;
    }
}
