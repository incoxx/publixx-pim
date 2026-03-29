<?php

declare(strict_types=1);

namespace App\Services\Connectors\AnyPim;

use App\Models\ConnectorConnection;
use App\Models\Media;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
     * Media von Remote-Instanz herunterladen und lokal speichern.
     */
    public function pullMedia(string $remoteUrl, string $accessToken, string $filePath, string $fileName): ?Media
    {
        $url = rtrim($remoteUrl, '/') . '/storage/' . ltrim($filePath, '/');

        try {
            $response = Http::withToken($accessToken)
                ->timeout(60)
                ->get($url);

            if (! $response->successful()) {
                Log::channel('connectors')->warning("anyPIM Media-Download fehlgeschlagen: {$url}");
                return null;
            }

            $localPath = 'media/' . $fileName;
            Storage::disk('public')->put($localPath, $response->body());

            return Media::updateOrCreate(
                ['file_name' => $fileName],
                [
                    'original_file_name' => $fileName,
                    'file_path'          => $localPath,
                    'mime_type'          => $response->header('Content-Type', 'application/octet-stream'),
                    'file_size'          => strlen($response->body()),
                    'media_type'         => 'image',
                ],
            );
        } catch (\Throwable $e) {
            Log::channel('connectors')->error("anyPIM Media-Download Fehler: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Entscheidet anhand der Connection-Einstellungen ob Media direkt übertragen wird.
     */
    public function shouldTransferDirectly(ConnectorConnection $connection): bool
    {
        return ($connection->settings['media_transfer_mode'] ?? 'reference') === 'direct';
    }
}
