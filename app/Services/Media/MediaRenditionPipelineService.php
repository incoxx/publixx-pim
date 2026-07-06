<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Media;
use App\Models\MediaMotif;
use App\Models\MediaRenditionPreset;
use App\Services\Media\Exceptions\UnsupportedRenditionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Kern-Service der Motiv/Rendition-Pipeline: aus einer Master-Datei werden
 * kanal-/formatspezifische Ableitungen (Print-TIFF, Web-JPEG, Mobile-WebP, ...) erzeugt.
 */
class MediaRenditionPipelineService
{
    public function __construct(private readonly ImageProcessorFactory $processorFactory) {}

    /**
     * Bestehendes, eigenständiges Media-Asset zum Master einer neuen Motiv-Gruppe machen.
     * Die Zeile selbst bleibt unverändert nutzbar (Produkt-/Knoten-Zuordnungen bleiben bestehen) —
     * es wird lediglich motif_id + is_master_rendition gesetzt. Nichts Bestehendes bricht dadurch.
     */
    public function promoteToMotif(Media $media, array $motifAttributes = []): MediaMotif
    {
        if ($media->motif_id !== null) {
            throw new \InvalidArgumentException('Medium gehört bereits zu einem Motiv.');
        }

        return DB::transaction(function () use ($media, $motifAttributes) {
            $motif = MediaMotif::create(array_merge([
                'title_de' => $media->title_de,
                'title_en' => $media->title_en,
                'asset_folder_id' => $media->asset_folder_id,
            ], $motifAttributes));

            $media->update([
                'motif_id' => $motif->id,
                'is_master_rendition' => true,
                'rendition_channel' => null,
                'rendition_preset_id' => null,
                'generated_at' => null,
            ]);

            return $motif;
        });
    }

    /**
     * Rendition für ein Preset erzeugen oder aktualisieren (Upsert über motif_id+preset_id).
     */
    public function generateRendition(MediaMotif $motif, MediaRenditionPreset $preset): Media
    {
        $master = $motif->masterRendition()->first();
        if (! $master) {
            throw new \RuntimeException("Motiv {$motif->id} hat keine Master-Rendition.");
        }

        $disk = Storage::disk('public');
        $sourcePath = $disk->path($master->file_path);
        if (! file_exists($sourcePath)) {
            throw new \RuntimeException("Master-Datei fehlt auf dem Server: {$master->file_path}");
        }

        $processor = $this->processorFactory->make($preset->format, $preset->colorspace);

        $extension = $preset->format === 'jpeg' ? 'jpg' : $preset->format;
        $fileName = "{$motif->id}-{$preset->technical_name}.{$extension}";
        $relativePath = "media/renditions/{$motif->id}/{$fileName}";
        $destPath = $disk->path($relativePath);

        $focalPoint = ($motif->focal_point_x !== null && $motif->focal_point_y !== null)
            ? ['x' => (float) $motif->focal_point_x, 'y' => (float) $motif->focal_point_y]
            : null;

        $dimensions = $processor->convert($sourcePath, $master->mime_type, $destPath, [
            'format' => $preset->format,
            'colorspace' => $preset->colorspace,
            'fit' => $preset->fit,
            'max_width' => $preset->max_width,
            'max_height' => $preset->max_height,
            'dpi' => $preset->dpi,
            'quality' => $preset->quality,
            'background_color' => $preset->background_color,
            'focal_point' => $focalPoint,
        ]);

        $mimeType = match ($preset->format) {
            'jpeg', 'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'tiff', 'tif' => 'image/tiff',
            default => 'application/octet-stream',
        };

        $rendition = Media::where('motif_id', $motif->id)
            ->where('rendition_preset_id', $preset->id)
            ->first();

        $attributes = [
            'file_name' => $fileName,
            'original_file_name' => $fileName,
            'file_path' => $relativePath,
            'file_status' => 'ok',
            'mime_type' => $mimeType,
            'file_size' => $disk->size($relativePath),
            'media_type' => 'image',
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'asset_folder_id' => $master->asset_folder_id,
            'usage_purpose' => $preset->channel === 'print' ? 'print' : 'web',
            'motif_id' => $motif->id,
            'is_master_rendition' => false,
            'rendition_channel' => $preset->channel,
            'rendition_preset_id' => $preset->id,
            'colorspace' => $preset->colorspace,
            'dpi' => $preset->dpi,
            'generated_at' => now(),
            'last_uploaded_at' => now(),
        ];

        if ($rendition) {
            // Alte Datei löschen, falls sich Dateiname/Pfad geändert hat (z.B. Preset nachträglich editiert)
            if ($rendition->file_path !== $relativePath && $disk->exists($rendition->file_path)) {
                $disk->delete($rendition->file_path);
            }
            $rendition->update($attributes);

            return $rendition->fresh();
        }

        return Media::create($attributes);
    }

    /**
     * Renditions für alle aktiven Presets (oder eine Teilmenge) generieren.
     * Presets, die der verfügbare Prozessor nicht bedienen kann (z.B. CMYK ohne Imagick),
     * werden übersprungen und als Fehler zurückgegeben statt den gesamten Lauf abzubrechen.
     *
     * @param  string[]|null  $presetIds
     * @return array{generated: Media[], errors: array<string, string>}
     */
    public function regenerateAll(MediaMotif $motif, ?array $presetIds = null): array
    {
        $query = MediaRenditionPreset::where('is_active', true);
        if ($presetIds !== null) {
            $query->whereIn('id', $presetIds);
        }

        $generated = [];
        $errors = [];

        foreach ($query->orderBy('sort_order')->get() as $preset) {
            try {
                $generated[] = $this->generateRendition($motif, $preset);
            } catch (UnsupportedRenditionException|\RuntimeException $e) {
                $errors[$preset->technical_name] = $e->getMessage();
            }
        }

        return ['generated' => $generated, 'errors' => $errors];
    }
}
