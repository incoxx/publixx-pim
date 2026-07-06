<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Services\Media\Exceptions\UnsupportedRenditionException;

interface ImageProcessorInterface
{
    /**
     * Kann dieser Prozessor das angeforderte Format/Colorspace erzeugen?
     */
    public function supports(string $format, string $colorspace): bool;

    /**
     * Quellbild in eine Rendition konvertieren (Resize/Crop/Format/Colorspace/DPI).
     *
     * $options:
     *   format: jpeg|png|webp|gif|tiff
     *   colorspace: rgb|cmyk|gray
     *   fit: contain|cover
     *   max_width, max_height: int|null (null = Originalgröße behalten)
     *   dpi: int|null
     *   quality: int|null (1-100, nur verlustbehaftete Formate)
     *   background_color: string|null ('#RRGGBB', für Formate ohne Alphakanal)
     *   focal_point: array{x: float, y: float}|null (0..1, für 'cover'-Fit)
     *
     * @return array{width: int, height: int} Tatsächliche Ausgabe-Dimensionen
     *
     * @throws UnsupportedRenditionException
     */
    public function convert(string $sourcePath, string $sourceMimeType, string $destPath, array $options): array;
}
