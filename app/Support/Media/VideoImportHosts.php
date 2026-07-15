<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * Allowlist der Hosts, von denen per yt-dlp Videos importiert werden dürfen
 * (ImportVideoFromUrl-Job). Bewusst eng gefasst — yt-dlp unterstützt tausende
 * Seiten, aber wir wollen den Server nicht als beliebigen Downloader für
 * fremde URLs freigeben (Missbrauchs-/Ressourcen-Risiko).
 */
final class VideoImportHosts
{
    public const ALLOWED_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'youtu.be',
    ];

    public static function isAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, self::ALLOWED_HOSTS, true);
    }
}
