<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Services\Media\Exceptions\UnsupportedRenditionException;

/**
 * Imagick-basierter Bildprozessor. Zusätzlich zu RGB unterstützt er CMYK-Konvertierung
 * (für Print) und TIFF-Ausgabe. Wird nur verwendet, wenn ext-imagick installiert ist —
 * siehe ImageProcessorFactory.
 */
class ImagickImageProcessor implements ImageProcessorInterface
{
    private const SUPPORTED_FORMATS = ['jpeg', 'jpg', 'png', 'webp', 'gif', 'tiff', 'tif'];

    private const SUPPORTED_COLORSPACES = ['rgb', 'cmyk', 'gray'];

    public function __construct()
    {
        if (! extension_loaded('imagick')) {
            throw new \RuntimeException('ImagickImageProcessor benötigt die PHP-Erweiterung "imagick".');
        }
    }

    public function supports(string $format, string $colorspace): bool
    {
        return in_array($format, self::SUPPORTED_FORMATS, true)
            && in_array($colorspace, self::SUPPORTED_COLORSPACES, true);
    }

    public function convert(string $sourcePath, string $sourceMimeType, string $destPath, array $options): array
    {
        $format = $options['format'];
        $colorspace = $options['colorspace'] ?? 'rgb';

        if (! $this->supports($format, $colorspace)) {
            throw new UnsupportedRenditionException(
                "Imagick kann Format \"{$format}\" mit Colorspace \"{$colorspace}\" nicht erzeugen."
            );
        }

        $image = new \Imagick($sourcePath);
        // Bei mehrseitigen Quellen (z.B. TIFF) nur die erste Seite verwenden
        if ($image->getNumberImages() > 1) {
            $image->setIteratorIndex(0);
        }

        $srcW = $image->getImageWidth();
        $srcH = $image->getImageHeight();

        $maxW = $options['max_width'] ?? $srcW;
        $maxH = $options['max_height'] ?? $srcH;
        $fit = $options['fit'] ?? 'contain';

        if ($fit === 'cover') {
            $this->resizeCover($image, $srcW, $srcH, $maxW, $maxH, $options['focal_point'] ?? null);
            $outW = $maxW;
            $outH = $maxH;
        } else {
            $ratio = min($maxW / $srcW, $maxH / $srcH, 1.0);
            $outW = max(1, (int) round($srcW * $ratio));
            $outH = max(1, (int) round($srcH * $ratio));
            $image->resizeImage($outW, $outH, \Imagick::FILTER_LANCZOS, 1, false);
        }

        $this->applyColorspace($image, $colorspace, $format, $options['background_color'] ?? null);

        if (! empty($options['dpi'])) {
            $image->setImageResolution($options['dpi'], $options['dpi']);
            $image->setResolution($options['dpi'], $options['dpi']);
        }

        if (! empty($options['quality']) && in_array($format, ['jpeg', 'jpg', 'webp'], true)) {
            $image->setImageCompressionQuality($options['quality']);
        }

        $image->setImageFormat($format);

        $dir = dirname($destPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $image->writeImage($destPath);
        $image->clear();
        $image->destroy();

        return ['width' => $outW, 'height' => $outH];
    }

    private function resizeCover(\Imagick $image, int $srcW, int $srcH, int $maxW, int $maxH, ?array $focalPoint): void
    {
        $ratio = max($maxW / $srcW, $maxH / $srcH);
        $scaledW = (int) round($srcW * $ratio);
        $scaledH = (int) round($srcH * $ratio);
        $image->resizeImage($scaledW, $scaledH, \Imagick::FILTER_LANCZOS, 1, false);

        $focalX = $focalPoint['x'] ?? 0.5;
        $focalY = $focalPoint['y'] ?? 0.5;

        $offsetX = (int) round(($scaledW - $maxW) * $focalX);
        $offsetY = (int) round(($scaledH - $maxH) * $focalY);
        $offsetX = max(0, min($offsetX, $scaledW - $maxW));
        $offsetY = max(0, min($offsetY, $scaledH - $maxH));

        $image->cropImage($maxW, $maxH, $offsetX, $offsetY);
        $image->setImagePage($maxW, $maxH, 0, 0);
    }

    private function applyColorspace(\Imagick &$image, string $colorspace, string $format, ?string $backgroundColor): void
    {
        // Transparenz auf Hintergrundfarbe flatten, wenn Zielformat/Colorspace keinen Alphakanal kennt
        if (in_array($format, ['jpeg', 'jpg', 'tiff', 'tif'], true) || $colorspace === 'cmyk') {
            $image->setImageBackgroundColor(new \ImagickPixel($backgroundColor ?? '#FFFFFF'));
            $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        }

        match ($colorspace) {
            'cmyk' => $image->transformImageColorspace(\Imagick::COLORSPACE_CMYK),
            'gray' => $image->transformImageColorspace(\Imagick::COLORSPACE_GRAY),
            default => $image->transformImageColorspace(\Imagick::COLORSPACE_SRGB),
        };
    }
}
