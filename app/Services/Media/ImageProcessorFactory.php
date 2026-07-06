<?php

declare(strict_types=1);

namespace App\Services\Media;

class ImageProcessorFactory
{
    /**
     * Liefert den fähigsten verfügbaren Prozessor für das angeforderte Format/Colorspace.
     * Imagick (CMYK/TIFF) wird bevorzugt, wenn installiert; sonst GD (nur RGB).
     */
    public function make(string $format, string $colorspace): ImageProcessorInterface
    {
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            $imagick = new ImagickImageProcessor;
            if ($imagick->supports($format, $colorspace)) {
                return $imagick;
            }
        }

        return new GdImageProcessor;
    }
}
