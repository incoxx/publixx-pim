<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductReferenceProfile;
use App\Services\Conformance\ProfileConformanceChecker;
use Illuminate\Console\Command;

/**
 * Batch-Konformitätsprüfung aller Produkte mit Referenz-Profil.
 *
 * Beispiele:
 *   php artisan pim:conformance-recheck
 *   php artisan pim:conformance-recheck --profile=<uuid>
 */
class ConformanceRecheck extends Command
{
    protected $signature = 'pim:conformance-recheck
        {--profile= : Nur Produkte dieses Referenz-Profils prüfen (UUID)}';

    protected $description = 'Produkte gegen ihr virtuelles Referenzprodukt (Profil) prüfen';

    public function handle(ProfileConformanceChecker $checker): int
    {
        $query = Product::whereNotNull('reference_profile_id')->with('referenceProfile');

        if ($profileId = $this->option('profile')) {
            if (!ProductReferenceProfile::whereKey($profileId)->exists()) {
                $this->error("Profil nicht gefunden: {$profileId}");
                return self::FAILURE;
            }
            $query->where('reference_profile_id', $profileId);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Keine Produkte mit Referenz-Profil gefunden.');
            return self::SUCCESS;
        }

        $this->info("Prüfe {$total} Produkt(e)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = ['pass' => 0, 'warning' => 0, 'fail' => 0];

        $query->chunkById(500, function ($products) use ($checker, $bar, &$stats) {
            foreach ($products as $product) {
                try {
                    $result = $checker->check($product, 'batch');
                    if ($result) {
                        $stats[$result->status] = ($stats[$result->status] ?? 0) + 1;
                    }
                } catch (\Throwable $e) {
                    $this->newLine();
                    $this->warn("Produkt {$product->id}: {$e->getMessage()}");
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info(sprintf(
            'Fertig: %d OK, %d Warnung, %d Fehlerhaft.',
            $stats['pass'], $stats['warning'], $stats['fail']
        ));

        return self::SUCCESS;
    }
}
