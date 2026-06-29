<?php

declare(strict_types=1);

namespace App\Observers\Content;

use App\Models\ContentPage;
use App\Models\ContentSection;
use App\Models\Navigation;
use App\Models\NavigationNode;
use App\Models\ProductWidget;
use App\Services\Content\ContentCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidiert den Content-Cache bei Änderungen an Content-Strukturen.
 * Registriert auf ContentPage, ContentSection, Navigation, NavigationNode,
 * ProductWidget (siehe EventServiceProvider).
 *
 * Hinweis: Content-Seiten sind mit `content:nav:{id}` UND `content:page:{id}`
 * getaggt → ein Nav-Flush verwirft auch die Seiten dieser Navigation.
 */
class ContentCacheObserver
{
    public function __construct(private readonly ContentCache $cache) {}

    public function saved(Model $model): void
    {
        $this->flush($model);
    }

    public function deleted(Model $model): void
    {
        $this->flush($model);
    }

    private function flush(Model $model): void
    {
        match (true) {
            $model instanceof ContentPage => $this->cache->flushTags(["content:page:{$model->id}"]),
            $model instanceof ContentSection => $this->cache->flushTags(["content:page:{$model->content_page_id}"]),
            $model instanceof Navigation => $this->cache->flushTags(["content:nav:{$model->id}"]),
            $model instanceof NavigationNode => $this->cache->flushTags(["content:nav:{$model->navigation_id}"]),
            $model instanceof ProductWidget => $this->cache->flushTags(['content']),
            default => null,
        };
    }
}
