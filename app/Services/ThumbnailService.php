<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

class ThumbnailService
{
    /**
     * Generate (or return cached) thumbnail for a media item.
     *
     * @return string|null Absolute path to the thumbnail file, or null if not an image.
     */
    public function generate(Media $media, int $width = 300, int $height = 300, string $fit = 'contain'): ?string
    {
        if (!extension_loaded('gd')) {
            \Log::warning('ThumbnailService: GD extension not loaded, cannot generate thumbnails.');
            return null;
        }

        if (!str_starts_with($media->mime_type, 'image/')) {
            return null;
        }

        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'jpg';
        $cacheDir = "thumbs/{$width}x{$height}";
        $cachePath = "{$cacheDir}/{$media->id}.{$extension}";

        $disk = Storage::disk('public');

        // Cache hit — nur vertrauen, wenn die Datei auch Inhalt hat. Ein 0-Byte-File
        // deutet auf einen abgebrochenen oder parallel überschriebenen Schreibvorgang
        // hin (siehe saveImage()) und würde sonst dauerhaft als "broken image" ausgeliefert.
        if ($disk->exists($cachePath)) {
            if ($disk->size($cachePath) > 0) {
                return $disk->path($cachePath);
            }
            $disk->delete($cachePath);
        }

        // Source file
        $sourcePath = $disk->path($media->file_path);
        if (!file_exists($sourcePath)) {
            \Log::warning('ThumbnailService: Source file missing', [
                'media_id' => $media->id,
                'file_path' => $media->file_path,
                'expected_path' => $sourcePath,
            ]);
            return null;
        }

        // Ensure cache directory exists
        $disk->makeDirectory($cacheDir);

        // Generate thumbnail using GD
        $thumbPath = $disk->path($cachePath);
        $this->createThumbnail($sourcePath, $thumbPath, $width, $height, $fit, $media->mime_type);

        return (file_exists($thumbPath) && filesize($thumbPath) > 0) ? $thumbPath : null;
    }

    /**
     * Delete all cached thumbnails for a media item.
     */
    public function clearCache(Media $media): void
    {
        $disk = Storage::disk('public');
        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'jpg';

        // Find and delete all size variants
        $thumbDirs = $disk->directories('thumbs');
        foreach ($thumbDirs as $dir) {
            $file = "{$dir}/{$media->id}.{$extension}";
            if ($disk->exists($file)) {
                $disk->delete($file);
            }
        }
    }

    /**
     * Löscht den kompletten Thumbnail-Cache (alle Größen, alle Medien). Fehlerhafte
     * oder veraltete Cache-Einträge werden dadurch beim nächsten Aufruf lazy neu erzeugt.
     *
     * @return int Anzahl der gelöschten Dateien.
     */
    public function clearAllCache(): int
    {
        $disk = Storage::disk('public');
        $count = 0;

        foreach ($disk->directories('thumbs') as $dir) {
            $count += count($disk->files($dir));
        }

        $disk->deleteDirectory('thumbs');

        return $count;
    }

    private function createThumbnail(string $source, string $dest, int $maxW, int $maxH, string $fit, string $mimeType): void
    {
        $srcImage = $this->loadImage($source, $mimeType);
        if (!$srcImage) {
            return;
        }

        $destImage = null;
        $tmpImage = null;

        try {
            $srcW = imagesx($srcImage);
            $srcH = imagesy($srcImage);

            if ($fit === 'cover') {
                // Crop to fill
                $ratio = max($maxW / $srcW, $maxH / $srcH);
                $newW = (int) round($srcW * $ratio);
                $newH = (int) round($srcH * $ratio);
                $offsetX = (int) round(($newW - $maxW) / 2);
                $offsetY = (int) round(($newH - $maxH) / 2);

                $tmpImage = imagecreatetruecolor($newW, $newH);
                if (!$tmpImage) {
                    return;
                }
                $this->preserveTransparency($tmpImage, $mimeType);
                imagecopyresampled($tmpImage, $srcImage, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

                $destImage = imagecreatetruecolor($maxW, $maxH);
                if (!$destImage) {
                    return;
                }
                $this->preserveTransparency($destImage, $mimeType);
                imagecopy($destImage, $tmpImage, 0, 0, $offsetX, $offsetY, $maxW, $maxH);
            } else {
                // Contain: fit within bounds, maintaining aspect ratio
                $ratio = min($maxW / $srcW, $maxH / $srcH);
                $newW = max(1, (int) round($srcW * $ratio));
                $newH = max(1, (int) round($srcH * $ratio));

                $destImage = imagecreatetruecolor($newW, $newH);
                if (!$destImage) {
                    return;
                }
                $this->preserveTransparency($destImage, $mimeType);
                imagecopyresampled($destImage, $srcImage, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
            }

            $this->saveImage($destImage, $dest, $mimeType);
        } finally {
            if ($tmpImage) {
                imagedestroy($tmpImage);
            }
            if ($destImage) {
                imagedestroy($destImage);
            }
            imagedestroy($srcImage);
        }
    }

    private function loadImage(string $path, string $mimeType): ?\GdImage
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path) ?: null,
            'image/png' => @imagecreatefrompng($path) ?: null,
            'image/gif' => @imagecreatefromgif($path) ?: null,
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            default => null,
        };
    }

    private function saveImage(\GdImage $image, string $path, string $mimeType): void
    {
        // Zielverzeichnis sicherstellen (makeDirectory über Storage reicht nicht immer)
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // In eine temporäre Datei im selben Verzeichnis schreiben und erst danach atomar
        // an den Zielpfad verschieben (rename). Ohne das würden zwei parallele Requests für
        // dasselbe neue Thumbnail (z.B. mehrere UI-Stellen, die gleichzeitig das gerade als
        // Hauptbild zugeordnete Bild anfragen) sich gegenseitig eine unvollständige Datei
        // überschreiben — Ursache für dauerhaft "broken" Thumbnails.
        $tmpPath = $path.'.'.uniqid('tmp', true);

        $success = match ($mimeType) {
            'image/png' => imagepng($image, $tmpPath, 8),
            'image/gif' => imagegif($image, $tmpPath),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, $tmpPath, 85) : imagejpeg($image, $tmpPath, 85),
            default => imagejpeg($image, $tmpPath, 85),
        };

        if (!$success || !file_exists($tmpPath) || filesize($tmpPath) === 0) {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
            \Log::error('ThumbnailService: Failed to write thumbnail image', ['path' => $path]);
            return;
        }

        rename($tmpPath, $path);
    }

    private function preserveTransparency(\GdImage $image, string $mimeType): void
    {
        if (in_array($mimeType, ['image/png', 'image/gif', 'image/webp'])) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
            imagealphablending($image, true);
        }
    }
}
