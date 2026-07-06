<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Services\Media\Exceptions\UnsupportedRenditionException;

/**
 * GD-basierter Bildprozessor. Unterstützt RGB(+Alpha) in JPEG/PNG/WebP/GIF.
 * Kein CMYK, kein TIFF — dafür wird ImagickImageProcessor benötigt.
 */
class GdImageProcessor implements ImageProcessorInterface
{
    private const SUPPORTED_FORMATS = ['jpeg', 'jpg', 'png', 'webp', 'gif'];

    public function supports(string $format, string $colorspace): bool
    {
        if ($colorspace !== 'rgb') {
            return false;
        }

        if (! in_array($format, self::SUPPORTED_FORMATS, true)) {
            return false;
        }

        if ($format === 'webp' && ! function_exists('imagewebp')) {
            return false;
        }

        return true;
    }

    public function convert(string $sourcePath, string $sourceMimeType, string $destPath, array $options): array
    {
        if (! $this->supports($options['format'], $options['colorspace'] ?? 'rgb')) {
            throw new UnsupportedRenditionException(
                "GD kann Format \"{$options['format']}\" mit Colorspace \"{$options['colorspace']}\" nicht erzeugen. Erfordert ext-imagick."
            );
        }

        $src = $this->loadImage($sourcePath, $sourceMimeType);
        if (! $src) {
            throw new UnsupportedRenditionException("Quelldatei konnte nicht gelesen werden: {$sourcePath}");
        }

        try {
            $srcW = imagesx($src);
            $srcH = imagesy($src);

            $maxW = $options['max_width'] ?? $srcW;
            $maxH = $options['max_height'] ?? $srcH;
            $fit = $options['fit'] ?? 'contain';

            [$out, $outW, $outH] = $fit === 'cover'
                ? $this->resizeCover($src, $srcW, $srcH, $maxW, $maxH, $options['focal_point'] ?? null)
                : $this->resizeContain($src, $srcW, $srcH, $maxW, $maxH);

            try {
                $this->flattenIfNeeded($out, $options['format'], $options['background_color'] ?? null);
                $this->saveImage($out, $destPath, $options['format'], $options['quality'] ?? null);
            } finally {
                if ($out !== $src) {
                    imagedestroy($out);
                }
            }

            return ['width' => $outW, 'height' => $outH];
        } finally {
            imagedestroy($src);
        }
    }

    private function resizeContain(\GdImage $src, int $srcW, int $srcH, int $maxW, int $maxH): array
    {
        $ratio = min($maxW / $srcW, $maxH / $srcH, 1.0);
        $newW = max(1, (int) round($srcW * $ratio));
        $newH = max(1, (int) round($srcH * $ratio));

        $dest = imagecreatetruecolor($newW, $newH);
        $this->preserveTransparency($dest);
        imagecopyresampled($dest, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

        return [$dest, $newW, $newH];
    }

    private function resizeCover(\GdImage $src, int $srcW, int $srcH, int $maxW, int $maxH, ?array $focalPoint): array
    {
        $ratio = max($maxW / $srcW, $maxH / $srcH);
        $scaledW = (int) round($srcW * $ratio);
        $scaledH = (int) round($srcH * $ratio);

        $scaled = imagecreatetruecolor($scaledW, $scaledH);
        $this->preserveTransparency($scaled);
        imagecopyresampled($scaled, $src, 0, 0, 0, 0, $scaledW, $scaledH, $srcW, $srcH);

        // Fokuspunkt bestimmt, welcher Ausschnitt erhalten bleibt (Default: Bildmitte)
        $focalX = $focalPoint['x'] ?? 0.5;
        $focalY = $focalPoint['y'] ?? 0.5;

        $offsetX = (int) round(($scaledW - $maxW) * $focalX);
        $offsetY = (int) round(($scaledH - $maxH) * $focalY);
        $offsetX = max(0, min($offsetX, $scaledW - $maxW));
        $offsetY = max(0, min($offsetY, $scaledH - $maxH));

        $dest = imagecreatetruecolor($maxW, $maxH);
        $this->preserveTransparency($dest);
        imagecopy($dest, $scaled, 0, 0, $offsetX, $offsetY, $maxW, $maxH);
        imagedestroy($scaled);

        return [$dest, $maxW, $maxH];
    }

    private function flattenIfNeeded(\GdImage $image, string $format, ?string $backgroundColor): void
    {
        // JPEG kennt keine Transparenz — auf Hintergrundfarbe flatten
        if (! in_array($format, ['jpeg', 'jpg'], true)) {
            return;
        }

        [$r, $g, $b] = $this->hexToRgb($backgroundColor ?? '#FFFFFF');
        $w = imagesx($image);
        $h = imagesy($image);

        $flat = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($flat, $r, $g, $b);
        imagefilledrectangle($flat, 0, 0, $w, $h, $bg);
        imagealphablending($flat, true);
        imagecopy($flat, $image, 0, 0, 0, 0, $w, $h);

        // Ergebnis zurück in $image kopieren (Referenz bleibt gültig für Aufrufer)
        imagealphablending($image, false);
        imagesavealpha($image, false);
        imagecopy($image, $flat, 0, 0, 0, 0, $w, $h);
        imagedestroy($flat);
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return [255, 255, 255];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
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

    private function saveImage(\GdImage $image, string $path, string $format, ?int $quality): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        match ($format) {
            'png' => imagepng($image, $path, 8),
            'gif' => imagegif($image, $path),
            'webp' => imagewebp($image, $path, $quality ?? 85),
            default => imagejpeg($image, $path, $quality ?? 85),
        };
    }

    private function preserveTransparency(\GdImage $image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
        imagealphablending($image, true);
    }
}
