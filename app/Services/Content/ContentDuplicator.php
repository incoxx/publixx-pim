<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\ContentPage;
use App\Models\ContentSection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Dupliziert Content: ganze Seiten (inkl. Sektionsbaum) oder einzelne
 * Sektionen (Copy & Paste, auch seitenübergreifend). Tief-Klon erhält die
 * Verschachtelung (parent_section_id) und Reihenfolge.
 */
class ContentDuplicator
{
    /**
     * Ganze Seite duplizieren: neue Seite (Entwurf, eindeutiger Slug) plus
     * vollständiger Sektionsbaum als Kopie.
     */
    public function duplicatePage(ContentPage $source): ContentPage
    {
        return DB::transaction(function () use ($source) {
            $page = $source->replicate();
            $page->slug = $this->uniqueSlug($source->slug);
            $page->title = $source->title . ' (Kopie)';
            $page->status = 'draft';
            $page->created_by = Auth::id();
            $page->updated_by = Auth::id();
            $page->save();

            $roots = $source->sections()
                ->whereNull('parent_section_id')->orderBy('sort_order')->get();
            foreach ($roots as $root) {
                $this->cloneSectionTree($root, $page->id, null);
            }

            return $page;
        });
    }

    /**
     * Eine Sektion (inkl. Kind-Sektionen) ans Ende einer Zielseite kopieren.
     * Quelle und Ziel dürfen identisch sein (= In-Place-Duplikat).
     */
    public function pasteSection(ContentSection $source, ContentPage $target): ContentSection
    {
        return DB::transaction(function () use ($source, $target) {
            $sort = (int) $target->sections()
                ->whereNull('parent_section_id')->max('sort_order') + 1;

            return $this->cloneSectionTree($source, $target->id, null, $sort);
        });
    }

    /** Sektion rekursiv klonen (Werte, Einstellungen, Kinder). */
    private function cloneSectionTree(ContentSection $src, string $pageId, ?string $parentId, ?int $sort = null): ContentSection
    {
        $copy = $src->replicate();
        $copy->content_page_id = $pageId;
        $copy->parent_section_id = $parentId;
        if ($sort !== null) {
            $copy->sort_order = $sort;
        }
        $copy->save();

        $children = $src->childSections()->orderBy('sort_order')->get();
        foreach ($children as $child) {
            $this->cloneSectionTree($child, $pageId, $copy->id);
        }

        return $copy;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base . '-kopie';
        $i = 2;
        while (ContentPage::where('slug', $slug)->exists()) {
            $slug = $base . '-kopie-' . $i++;
        }

        return $slug;
    }
}
