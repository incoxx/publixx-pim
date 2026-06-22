<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RenderSocialVideoJob;
use App\Services\Export\SocialVideoBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SocialVideoGenerate extends Command
{
    protected $signature = 'pim:social-video
        {--products= : Produkt-IDs (kommagetrennt)}
        {--mapping= : Mapping-ID (PublixxExportMapping UUID)}
        {--lang=de : Sprache(n), kommagetrennt}
        {--format=9x16 : Seitenverhältnis (9x16|1x1|16x9)}
        {--template=default : Szenen-Template}
        {--ai : KI-Hook via Claude generieren}
        {--reel-only : Nur Reel-Definition als JSON ausgeben, kein Rendering}';

    protected $description = 'Erzeugt ein Social-Media-Produktvideo (Reel/Short) aus PIM-Daten';

    public function handle(SocialVideoBuilder $builder): int
    {
        $productIds = array_filter(explode(',', (string) $this->option('products')));
        if (empty($productIds)) {
            $this->error('Bitte --products=<id[,id...]> angeben.');

            return self::FAILURE;
        }

        $builder->setProductIds($productIds);
        $builder->setLanguages(array_filter(explode(',', (string) $this->option('lang'))));
        $builder->setFormat((string) $this->option('format'));
        $builder->setTemplate((string) $this->option('template'));
        $builder->setUseAi((bool) $this->option('ai'));

        if ($this->option('mapping')) {
            $builder->setMappingId((string) $this->option('mapping'));
        }

        $this->info('Reel-Definition wird gebaut …');
        $reel = $builder->build();
        $this->info(sprintf('Produkte: %d, Szenen: %d', $reel['meta']['product_count'], count($reel['scenes'])));

        if ($this->option('reel-only')) {
            $this->line(json_encode($reel, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $jobId = (string) Str::uuid();
        $this->info("Rendering startet (Job {$jobId}) …");

        // Synchron ausführen, damit die CLI auf das Ergebnis wartet.
        RenderSocialVideoJob::dispatchSync($jobId, $reel);

        $statusPath = RenderSocialVideoJob::statusPath($jobId);
        $status = is_file($statusPath) ? json_decode((string) file_get_contents($statusPath), true) : [];

        if (($status['status'] ?? '') === 'done') {
            $this->info('✓ ' . ($status['message'] ?? 'fertig'));
            $this->line('  ' . ($status['download_url'] ?? ''));

            return self::SUCCESS;
        }

        $this->error('✗ ' . ($status['message'] ?? 'Rendering fehlgeschlagen'));

        return self::FAILURE;
    }
}
