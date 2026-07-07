<?php

declare(strict_types=1);

namespace App\Services;

class MimeTypeDetector
{
    /**
     * Ermittelt den echten MIME-Typ einer Datei anhand ihres Inhalts (Magic Bytes),
     * unabhängig von Dateiendung, HTTP-Header oder ggf. fehlerhaften Import-Metadaten.
     */
    public static function detectFromFile(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        return $finfo->file($path) ?: null;
    }
}
