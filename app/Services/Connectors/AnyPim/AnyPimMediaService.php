<?php

declare(strict_types=1);

namespace App\Services\Connectors\AnyPim;

use App\Models\ConnectorConnection;
use App\Models\Media;
use App\Support\Media\MediaFileTypes;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Exceptions\ImportCancelledException;

class AnyPimMediaService
{
    /**
     * Media-Asset zur Remote-Instanz übertragen.
     *
     * @return string Remote-Dateiname als Referenz
     */
    public function pushMedia(PendingRequest $http, string $remoteUrl, Media $media): string
    {
        $filePath = Storage::disk('public')->path($media->file_path);

        if (! file_exists($filePath)) {
            throw new \RuntimeException("Mediendatei nicht gefunden: {$media->file_path}");
        }

        $response = $http->timeout(120)
            ->attach('file', fopen($filePath, 'r'), $media->file_name)
            ->post("{$remoteUrl}/api/v1/pim-sync/media", [
                'file_name'  => $media->file_name,
                'mime_type'  => $media->mime_type,
                'title_de'   => $media->title_de,
                'title_en'   => $media->title_en,
                'alt_text_de' => $media->alt_text_de,
                'alt_text_en' => $media->alt_text_en,
            ]);

        $response->throw();

        return $media->file_name;
    }

    /**
     * Lädt die Datei eines einzelnen Media-Eintrags von der Remote-Instanz
     * (öffentlicher /storage/-Pfad) herunter und speichert sie lokal unter
     * genau demselben file_path. Aktualisiert file_size/mime_type am Media-Datensatz.
     *
     * Wirft bei Fehlschlag — der Aufrufer (pullMissingMedia) fängt pro Datei ab.
     */
    public function pullMedia(PendingRequest $http, string $remoteUrl, Media $media): void
    {
        $url = rtrim($remoteUrl, '/') . '/storage/' . ltrim($media->file_path, '/');

        $response = $http->timeout(60)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Download fehlgeschlagen (HTTP {$response->status()})");
        }

        $body = $response->body();
        $mimeType = $response->header('Content-Type', $media->mime_type) ?: $media->mime_type;

        Storage::disk('public')->put($media->file_path, $body);

        $media->update([
            'file_size' => strlen($body),
            'mime_type' => $mimeType,
            'media_type' => $media->media_type ?: MediaFileTypes::classifyMimeType((string) $mimeType),
        ]);
    }

    /**
     * Anzahl der lokalen Media-Einträge, deren Datei physisch fehlt
     * (z.B. weil sie per JSON-Import als Metadaten angelegt, aber nie
     * hochgeladen wurden).
     */
    public function countMissingMedia(): int
    {
        return $this->findMissingMedia()->count();
    }

    /**
     * Alle Media-Einträge, deren Datei auf dem public-Disk nicht existiert.
     *
     * @return \Illuminate\Support\Collection<int, Media>
     */
    private function findMissingMedia(): \Illuminate\Support\Collection
    {
        $disk = Storage::disk('public');

        return Media::query()->get()->filter(
            fn (Media $media) => $media->file_path && $disk->missing($media->file_path)
        )->values();
    }

    /**
     * Holt alle lokal fehlenden Mediendateien von der Remote-Instanz nach.
     * Nutzt denselben Cache-basierten Abbruch-Mechanismus wie der JSON-Import
     * (Cache-Key "bmecat_import_cancel_{$importId}"), damit der bestehende
     * /json-import/cancel-Endpoint auch hierfür funktioniert.
     *
     * @param  callable(int $current, int $total, string $fileName): void|null  $progressCallback
     * @param  callable(): void|null  $heartbeatCallback
     * @return array{checked: int, missing: int, downloaded: int, failed: int, errors: string[]}
     */
    public function pullMissingMedia(
        PendingRequest $http,
        string $remoteUrl,
        ?string $importId = null,
        ?callable $progressCallback = null,
        ?callable $heartbeatCallback = null,
    ): array {
        $missing = $this->findMissingMedia();
        $total = $missing->count();

        $stats = [
            'checked' => Media::count(),
            'missing' => $total,
            'downloaded' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($missing as $index => $media) {
            if ($heartbeatCallback) {
                ($heartbeatCallback)();
            }

            if ($importId && Cache::get("bmecat_import_cancel_{$importId}")) {
                Cache::forget("bmecat_import_cancel_{$importId}");
                throw new ImportCancelledException($importId);
            }

            try {
                $this->pullMedia($http, $remoteUrl, $media);
                $stats['downloaded']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['errors'][] = "{$media->file_name}: {$e->getMessage()}";
                Log::channel('connectors')->warning("anyPIM Missing-Media Pull fehlgeschlagen: {$media->file_name} — {$e->getMessage()}");
            }

            if ($progressCallback) {
                ($progressCallback)($index + 1, $total, $media->file_name);
            }
        }

        return $stats;
    }

    /**
     * Entscheidet anhand der Connection-Einstellungen ob Media direkt übertragen wird.
     */
    public function shouldTransferDirectly(ConnectorConnection $connection): bool
    {
        return ($connection->settings['media_transfer_mode'] ?? 'reference') === 'direct';
    }
}
