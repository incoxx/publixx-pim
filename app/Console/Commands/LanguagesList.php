<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Language;
use Illuminate\Console\Command;

/**
 * Gibt die gepflegten Sprachen maschinenlesbar aus.
 *
 * Massgeblich ist die Tabelle `languages` (GUI: Uebersetzungen -> Sprachen),
 * nicht TMS_TARGET_LANGUAGES in einer .env — die Variable ist nur noch
 * Rueckfallebene fuer den Fall, dass die Tabelle nicht erreichbar ist.
 * Skripte wie tms-activate.sh holen sich die Liste hierueber, statt eine
 * eigene Kopie in einer Konfigurationsdatei zu pflegen.
 */
class LanguagesList extends Command
{
    protected $signature = 'pim:languages
        {--targets : Nur die Zielsprachen ausgeben}
        {--source : Nur die Quellsprache ausgeben}';

    protected $description = 'Gibt Quell- und Zielsprachen kommagetrennt aus';

    public function handle(): int
    {
        if ($this->option('source')) {
            $this->line(Language::sourceCode());

            return self::SUCCESS;
        }

        if ($this->option('targets')) {
            $this->line(implode(',', Language::targetCodes()));

            return self::SUCCESS;
        }

        $this->line('Quellsprache:  ' . Language::sourceCode());
        $this->line('Zielsprachen:  ' . implode(',', Language::targetCodes()));

        return self::SUCCESS;
    }
}
