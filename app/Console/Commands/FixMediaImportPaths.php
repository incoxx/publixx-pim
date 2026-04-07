<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FixMediaImportPaths extends Command
{
    protected $signature = 'pim:fix-media-import-paths {--dry-run : Nur anzeigen, nicht ändern}';

    protected $description = 'Korrigiert file_path von "imports/..." zu "media/..." für importierte Medien';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $disk = Storage::disk('public');

        $affected = Media::where('file_path', 'like', 'imports/%')->get();

        if ($affected->isEmpty()) {
            $this->info('Keine Medien mit imports/-Pfad gefunden.');
            return self::SUCCESS;
        }

        $this->info("Gefunden: {$affected->count()} Medien mit imports/-Pfad");

        $fixed = 0;
        $missing = 0;

        foreach ($affected as $media) {
            $correctedPath = 'media/' . $media->file_name;
            $fileExists = $disk->exists($correctedPath);

            if ($fileExists) {
                if (!$dryRun) {
                    $media->update([
                        'file_path' => $correctedPath,
                        'file_size' => $media->file_size ?: $disk->size($correctedPath),
                    ]);
                }
                $fixed++;
                $this->line("  ✓ {$media->file_name}");
            } else {
                $missing++;
                $this->warn("  ✗ {$media->file_name} — Datei fehlt auf Disk");
            }
        }

        $this->newLine();
        $this->info("Korrigiert: {$fixed} | Datei fehlt: {$missing}");
        if ($dryRun) {
            $this->comment('(Dry-Run — keine Änderungen vorgenommen)');
        }

        return self::SUCCESS;
    }
}
