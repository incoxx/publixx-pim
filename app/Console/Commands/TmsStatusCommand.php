<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tms\TmsClient;
use Illuminate\Console\Command;

/**
 * Zeigt Erreichbarkeit und Abdeckung des Translation Memory.
 *
 * Gedacht als erster Griff bei "warum ist da nichts uebersetzt?" — der
 * TmsClient verschluckt Fehler bewusst, damit das PIM weiterlaeuft; dieser
 * Befehl macht sie sichtbar.
 */
class TmsStatusCommand extends Command
{
    protected $signature = 'tms:status';

    protected $description = 'Zeigt Status und Uebersetzungs-Abdeckung des TMS';

    public function handle(TmsClient $client): int
    {
        $this->line('Konfiguration');
        $this->line('  TMS_ENABLED:          ' . (config('tms.enabled') ? 'true' : 'false'));
        $this->line('  TMS_BASE_URL:         ' . config('tms.base_url'));
        $this->line('  TMS_TARGET_LANGUAGES: ' . implode(',', config('tms.target_languages', [])));
        $this->newLine();

        if (!$client->isEnabled()) {
            $this->warn('TMS ist deaktiviert — es wird weder ingestet noch synchronisiert.');
            return self::FAILURE;
        }

        $stats = $client->getStats();

        if (empty($stats)) {
            $this->error('Keine Antwort vom TMS.');
            $this->line('Pruefen:');
            $this->line('  - Laeuft der Dienst?   curl -s ' . rtrim((string) config('tms.base_url'), '/') . '/../up');
            $this->line('  - Stimmt der API-Key?  TMS_API_KEY in .env und tms/.env muessen identisch sein');
            $this->line('  - Log:                 tms/storage/logs/');
            return self::FAILURE;
        }

        $this->info('Begriffe im TM: ' . ($stats['total_units'] ?? 0));
        $this->newLine();

        $rows = [];
        foreach ($stats['languages'] ?? [] as $lang => $s) {
            $rows[] = [
                $lang,
                $s['total'] ?? 0,
                $s['translated'] ?? 0,
                $s['reviewed'] ?? 0,
                $s['missing'] ?? 0,
                ($s['coverage'] ?? 0) . ' %',
            ];
        }

        $this->table(
            ['Sprache', 'Relevant', 'Uebersetzt', 'Geprueft', 'Offen', 'Abdeckung'],
            $rows,
        );

        // Konfigurations-Drift zwischen PIM und TMS sichtbar machen: die
        // Sprachliste steht in beiden .env und laeuft sonst still auseinander.
        $pimLangs = array_filter(config('tms.target_languages', []));
        $tmsLangs = array_keys($stats['languages'] ?? []);
        $onlyPim = array_diff($pimLangs, $tmsLangs);
        $onlyTms = array_diff($tmsLangs, $pimLangs);

        if (!empty($onlyPim) || !empty($onlyTms)) {
            $this->newLine();
            $this->warn('TMS_TARGET_LANGUAGES laeuft zwischen PIM und TMS auseinander:');
            if (!empty($onlyPim)) {
                $this->line('  nur im PIM: ' . implode(',', $onlyPim) . ' — das TMS uebersetzt diese Sprachen nicht.');
            }
            if (!empty($onlyTms)) {
                $this->line('  nur im TMS: ' . implode(',', $onlyTms) . ' — der Sync holt diese Sprachen nicht ab.');
            }
            $this->line('  Beide .env angleichen, dann in beiden Anwendungen: php artisan config:cache');
        }

        $offen = array_sum(array_column($stats['languages'] ?? [], 'missing'));
        if ($offen > 0) {
            $this->newLine();
            $this->line("Offen: {$offen}. Ausrollen mit: php artisan tms:translate-missing");
        }

        return self::SUCCESS;
    }
}
