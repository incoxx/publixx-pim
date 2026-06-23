<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MediaScanMissing extends Command
{
    protected $signature = 'media:scan-missing
        {--reset : Alle file_status auf ok setzen und neu scannen}
        {--quiet-mode : Keine Ausgabe außer Fehlern}';

    protected $description = 'Prüft alle Medien-Dateien auf Existenz und aktualisiert file_status (ok/missing)';

    public function handle(): int
    {
        $disk = Storage::disk(config('media-library.disk_name', 'public'));

        if ($this->option('reset')) {
            Media::query()->update(['file_status' => 'ok']);
            if (!$this->option('quiet-mode')) {
                $this->info('Alle file_status auf "ok" zurückgesetzt.');
            }
        }

        $checked = 0;
        $missing = 0;
        $recovered = 0;

        Media::select('id', 'file_name', 'file_path', 'file_status')
            ->chunk(500, function ($records) use ($disk, &$checked, &$missing, &$recovered) {
                foreach ($records as $record) {
                    $checked++;
                    $exists = $disk->exists($record->file_path);
                    $newStatus = $exists ? 'ok' : 'missing';

                    if ($newStatus !== $record->file_status) {
                        $record->update(['file_status' => $newStatus]);
                        if ($newStatus === 'missing') {
                            $missing++;
                        } else {
                            $recovered++;
                        }
                    } elseif ($newStatus === 'missing') {
                        $missing++;
                    }
                }
            });

        if (!$this->option('quiet-mode')) {
            $this->info("Geprüft: {$checked} Medien");
            $this->line("Fehlend: {$missing}");
            if ($recovered > 0) {
                $this->info("Status aktualisiert (jetzt ok): {$recovered}");
            }
        }

        return self::SUCCESS;
    }
}
