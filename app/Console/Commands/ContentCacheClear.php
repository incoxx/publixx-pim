<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ContentPage;
use App\Models\Navigation;
use App\Services\Content\ContentCache;
use Illuminate\Console\Command;

class ContentCacheClear extends Command
{
    protected $signature = 'pim:content-cache-clear
        {--nav= : nur diese Navigation (technical_name oder ID)}
        {--page= : nur diese Seite (Slug oder ID)}
        {--product= : nur diese Produkt-ID}
        {--stats : aktuelle Hit-Rate ausgeben und Metriken zurücksetzen}';

    protected $description = 'Content-Cache verwerfen (gesamt oder gezielt)';

    public function handle(ContentCache $cache): int
    {
        if ($this->option('stats')) {
            $m = $cache->metrics();
            $this->info("Hit-Rate: " . ($m['hit_rate'] * 100) . "% — {$m['hits']} Hits / {$m['misses']} Misses ({$m['total']} gesamt).");
            $cache->resetMetrics();
            $this->line('Metriken zurückgesetzt.');

            return self::SUCCESS;
        }

        $targeted = false;

        if ($nav = $this->option('nav')) {
            $navigation = Navigation::where('technical_name', $nav)->orWhere('id', $nav)->first();
            if ($navigation) {
                $cache->flushTags(["content:nav:{$navigation->id}"]);
                $this->info("Navigation {$navigation->technical_name} invalidiert.");
                $targeted = true;
            }
        }

        if ($page = $this->option('page')) {
            $model = ContentPage::where('slug', $page)->orWhere('id', $page)->first();
            if ($model) {
                $cache->flushTags(["content:page:{$model->id}"]);
                $this->info("Seite {$model->slug} invalidiert.");
                $targeted = true;
            }
        }

        if ($product = $this->option('product')) {
            $cache->flushProduct($product);
            $this->info("Produkt {$product} (PDP + einbettende Seiten) invalidiert.");
            $targeted = true;
        }

        if (! $targeted) {
            $cache->flushAll();
            $this->info('Kompletter Content-Cache verworfen.');
        }

        return self::SUCCESS;
    }
}
