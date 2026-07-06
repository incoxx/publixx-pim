<?php

declare(strict_types=1);

namespace App\Services\Media\Exceptions;

/**
 * Wird geworfen, wenn ein Preset ein Format/Farbraum verlangt (z.B. CMYK/TIFF),
 * das der verfügbare Bildprozessor (GD) nicht unterstützt.
 */
class UnsupportedRenditionException extends \RuntimeException {}
