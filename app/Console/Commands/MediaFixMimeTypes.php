<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MimeTypeDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MediaFixMimeTypes extends Command
{
    protected $signature = 'media:fix-mime-types
        {--quiet-mode : Keine Ausgabe außer Fehlern}';

    protected $description = 'Korrigiert fehlerhafte mime_type/media_type-Werte anhand des tatsächlichen Dateiinhalts (behebt "kein Bild"-Thumbnail-Fehler durch fehlerhafte Import-Metadaten)';

    public function handle(): int
    {
        $disk = Storage::disk(config('media-library.disk_name', 'public'));

        $checked = 0;
        $fixed = 0;

        Media::query()
            ->where(function ($q) {
                $q->whereNull('mime_type')
                    ->orWhere('mime_type', '=', '')
                    ->orWhere('mime_type', 'not like', 'image/%');
            })
            ->select('id', 'file_name', 'file_path', 'mime_type', 'media_type')
            ->chunkById(500, function ($records) use ($disk, &$checked, &$fixed) {
                foreach ($records as $media) {
                    $checked++;

                    if (!$disk->exists($media->file_path)) {
                        continue;
                    }

                    $detected = MimeTypeDetector::detectFromFile($disk->path($media->file_path));
                    if (!$detected || $detected === $media->mime_type) {
                        continue;
                    }

                    $updates = ['mime_type' => $detected];
                    if (str_starts_with($detected, 'image/') && $media->media_type !== 'image') {
                        $updates['media_type'] = 'image';
                    }

                    $media->update($updates);
                    $fixed++;
                }
            });

        if (!$this->option('quiet-mode')) {
            $this->info("Geprüft: {$checked} Medien mit verdächtigem MIME-Typ");
            $this->info("Korrigiert: {$fixed}");
        }

        return self::SUCCESS;
    }
}
