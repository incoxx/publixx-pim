<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tms\TmsClient;
use Illuminate\Console\Command;

/**
 * Rollt eine Zielsprache über den gesamten TM-Bestand aus.
 *
 * Typischer Ablauf beim Hinzufügen einer Sprache (z.B. Finnisch):
 *
 *   1. TMS_TARGET_LANGUAGES in .env UND tms/.env um 'fi' ergänzen
 *   2. php artisan config:cache  (in beiden Anwendungen)
 *   3. php artisan tms:translate-missing --lang=fi
 *   4. php artisan tms:sync
 */
class TmsTranslateMissingCommand extends Command
{
    protected $signature = 'tms:translate-missing
        {--lang= : Zielsprache (z.B. fi). Ohne Angabe: alle aus TMS_TARGET_LANGUAGES}
        {--batch=1000 : Begriffe pro Durchlauf}
        {--max-batches=1000 : Sicherheitsdeckel gegen Endlosschleifen}';

    protected $description = 'Plant alle fehlenden Übersetzungen einer Zielsprache im TMS ein';

    public function handle(TmsClient $client): int
    {
        if (!$client->isEnabled()) {
            $this->error('TMS ist deaktiviert (TMS_ENABLED=false).');
            return self::FAILURE;
        }

        $langs = $this->option('lang')
            ? [trim($this->option('lang'))]
            : array_filter(config('tms.target_languages', []));

        if (empty($langs)) {
            $this->error('Keine Zielsprache angegeben und TMS_TARGET_LANGUAGES ist leer.');
            return self::FAILURE;
        }

        $batch = max(1, (int) $this->option('batch'));
        $maxBatches = max(1, (int) $this->option('max-batches'));
        $failed = false;

        foreach ($langs as $lang) {
            $this->info("Sprache '{$lang}' wird ausgerollt...");

            $totalDispatched = 0;
            $round = 0;

            while ($round < $maxBatches) {
                $round++;
                $result = $client->translateMissing($lang, $batch);

                // Der TmsClient verschluckt Fehler und liefert dann [] — das
                // darf nicht als "fertig" durchgehen, sonst meldet der Befehl
                // faelschlich Erfolg.
                if (!isset($result['dispatched'])) {
                    $this->error("  Keine gueltige Antwort vom TMS — Abbruch fuer '{$lang}'.");
                    $this->line('  Pruefen: TMS erreichbar? API-Key korrekt? Siehe TMS-Log.');
                    $failed = true;
                    break;
                }

                $dispatched = (int) $result['dispatched'];
                $remaining = (int) ($result['remaining'] ?? 0);
                $totalDispatched += $dispatched;

                if ($dispatched === 0) {
                    break;
                }

                $this->line("  {$totalDispatched} eingeplant, noch {$remaining} offen...");

                if ($remaining === 0) {
                    break;
                }
            }

            if ($round >= $maxBatches) {
                $this->warn("  Deckel von {$maxBatches} Durchlaeufen erreicht — erneut ausfuehren.");
            }

            if ($totalDispatched === 0) {
                $this->line("  Nichts offen fuer '{$lang}'.");
            } else {
                $this->info("  {$totalDispatched} Begriffe fuer '{$lang}' eingeplant.");
            }
        }

        if ($failed) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Die Uebersetzung laeuft im TMS-Queue-Worker.');
        $this->line('Fortschritt: php artisan tms:status   —   Danach: php artisan tms:sync');

        return self::SUCCESS;
    }
}
