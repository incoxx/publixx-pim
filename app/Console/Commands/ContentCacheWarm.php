<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Navigation;
use App\Services\Content\ContentCache;
use App\Services\Content\ContentCacheWarmer;
use Illuminate\Console\Command;

class ContentCacheWarm extends Command
{
    protected $signature = 'pim:content-cache-warm
        {--nav= : Navigation (technical_name oder ID); Standard: alle}
        {--lang=de : Sprachen, kommagetrennt (z. B. de,en)}';

    protected $description = 'Content-Cache vorrendern (Sitemap + Seiten + Produktseiten)';

    public function handle(ContentCacheWarmer $warmer, ContentCache $cache): int
    {
        $langs = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('lang')))));
        $langs = $langs ?: ['de'];

        $query = Navigation::query();
        if ($nav = $this->option('nav')) {
            $query->where('technical_name', $nav)->orWhere('id', $nav);
        }
        $navigations = $query->get();

        if ($navigations->isEmpty()) {
            $this->warn('Keine Navigation gefunden.');

            return self::SUCCESS;
        }

        $total = 0;
        foreach ($navigations as $navigation) {
            $warmed = $warmer->warmNavigation($navigation, $langs);
            $total += $warmed;
            $this->info("• {$navigation->technical_name}: {$warmed} Ausgaben gewärmt (" . implode(',', $langs) . ').');
        }

        $m = $cache->metrics();
        $this->info("Fertig: {$total} Ausgaben. Hit-Rate bisher: " . ($m['hit_rate'] * 100) . "% ({$m['hits']}/{$m['total']}).");

        return self::SUCCESS;
    }
}
