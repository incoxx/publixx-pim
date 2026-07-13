<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * Zentrale Quelle für erlaubte Datei-Endungen der Medienverwaltung.
 *
 * Wird sowohl von der Direct-Upload-Validierung (StoreMediaRequest) als auch
 * von URL-Import/Bulk-Import (MediaController::validateExtension()) genutzt,
 * damit beide Wege dieselbe Allowlist verwenden.
 */
final class MediaFileTypes
{
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'tif'];

    public const DOCUMENT_EXTENSIONS = ['pdf', 'eps', 'ai', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv'];

    public const AUDIO_EXTENSIONS = ['mp3'];

    public const VIDEO_EXTENSIONS = ['mp4', 'mpeg', 'mpg'];

    public const ALLOWED_EXTENSIONS = [
        ...self::IMAGE_EXTENSIONS,
        ...self::DOCUMENT_EXTENSIONS,
        ...self::AUDIO_EXTENSIONS,
        ...self::VIDEO_EXTENSIONS,
    ];

    /**
     * Ordnet einen MIME-Type dem passenden media_type zu (image/document/video/audio/other).
     * Zentrale Klassifizierungslogik, genutzt von Import-Pfaden (BmecatFormatImporter,
     * ImportExecutor), die media_type nicht direkt vom Nutzer übernehmen.
     */
    public static function classifyMimeType(string $mimeType): string
    {
        $mimeType = strtolower($mimeType);

        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            str_contains($mimeType, 'pdf') || str_starts_with($mimeType, 'application/') => 'document',
            default => 'other',
        };
    }
}
