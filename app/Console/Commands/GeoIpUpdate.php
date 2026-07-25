<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Lädt bzw. aktualisiert die MaxMind GeoLite2-Länderdatenbank für die
 * IP→Land-Auflösung des SecurityMonitor (Geo-Blocking ohne CDN).
 *
 * Voraussetzung: kostenloser MaxMind-Account → License Key in MAXMIND_LICENSE_KEY.
 */
class GeoIpUpdate extends Command
{
    protected $signature = 'pim:geoip-update';

    protected $description = 'MaxMind GeoLite2-Länderdatenbank herunterladen/aktualisieren';

    public function handle(): int
    {
        $key = (string) config('security.geo.maxmind.license_key', '');
        $edition = (string) config('security.geo.maxmind.edition', 'GeoLite2-Country');
        $target = (string) config('security.geo.maxmind.database', '');

        if ($key === '') {
            $this->error('MAXMIND_LICENSE_KEY ist nicht gesetzt. Kostenlosen Key unter maxmind.com anlegen und in der .env hinterlegen.');

            return self::FAILURE;
        }
        if ($target === '') {
            $this->error('SECURITY_GEOIP_DB (Zielpfad) ist nicht konfiguriert.');

            return self::FAILURE;
        }

        $dir = dirname($target);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error("Zielverzeichnis konnte nicht erstellt werden: {$dir}");

            return self::FAILURE;
        }

        $url = 'https://download.maxmind.com/app/geoip_download';
        $tmpArchive = $dir.'/_geolite_download.tar.gz';

        $this->info("Lade {$edition} von MaxMind …");

        try {
            $response = Http::timeout(120)->withOptions(['sink' => $tmpArchive])->get($url, [
                'edition_id' => $edition,
                'license_key' => $key,
                'suffix' => 'tar.gz',
            ]);

            if (! $response->successful()) {
                @unlink($tmpArchive);
                $this->error("Download fehlgeschlagen (HTTP {$response->status()}). License Key / Edition prüfen.");

                return self::FAILURE;
            }

            $mmdb = $this->extractMmdb($tmpArchive);
            if ($mmdb === null) {
                @unlink($tmpArchive);
                $this->error('Keine .mmdb-Datei im Archiv gefunden.');

                return self::FAILURE;
            }

            if (! copy($mmdb, $target)) {
                @unlink($tmpArchive);
                $this->error("Konnte die Datenbank nicht nach {$target} schreiben.");

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            @unlink($tmpArchive);
            $this->error('Fehler: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            $this->cleanup($tmpArchive);
        }

        $this->info('GeoLite2-Datenbank aktualisiert: '.$target);
        $this->info('Größe: '.number_format((int) filesize($target)).' Bytes');

        return self::SUCCESS;
    }

    /**
     * Entpackt das tar.gz und liefert den Pfad zur enthaltenen .mmdb-Datei.
     */
    private function extractMmdb(string $archive): ?string
    {
        $phar = new \PharData($archive);
        foreach (new \RecursiveIteratorIterator($phar) as $file) {
            if (str_ends_with(strtolower($file->getFilename()), '.mmdb')) {
                return $file->getPathname();
            }
        }

        return null;
    }

    private function cleanup(string $archive): void
    {
        @unlink($archive);
        // Von PharData entpackte Verzeichnisse (falls vorhanden) mit aufräumen.
        foreach (glob(dirname($archive).'/'.pathinfo($archive, PATHINFO_FILENAME).'*') ?: [] as $leftover) {
            if (is_file($leftover)) {
                @unlink($leftover);
            }
        }
    }
}
