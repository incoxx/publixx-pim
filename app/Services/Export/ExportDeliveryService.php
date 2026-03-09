<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\ExportJob;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Zustellung von Export-Dateien an konfigurierte Ziele.
 *
 * Unterstützte Delivery-Typen:
 *   - filesystem: Kopie auf einen Laravel-Storage-Disk
 *   - sftp:       Upload per SFTP (league/flysystem-sftp-v3)
 *   - http:       HTTP POST/PUT an eine REST-API
 */
class ExportDeliveryService
{
    /**
     * @return array{status: string, error: string|null}
     */
    public function deliver(ExportJob $job, string $filePath): array
    {
        if (!$job->delivery_type || !$job->delivery_config) {
            return ['status' => 'skipped', 'error' => null];
        }

        Log::channel('export')->info("Delivery gestartet: {$job->delivery_type}", [
            'job_id' => $job->id,
            'file' => basename($filePath),
        ]);

        try {
            $result = match ($job->delivery_type) {
                'filesystem' => $this->deliverToFilesystem($job->delivery_config, $filePath),
                'sftp' => $this->deliverViaSftp($job->delivery_config, $filePath),
                'http' => $this->deliverViaHttp($job->delivery_config, $filePath),
                default => throw new \InvalidArgumentException("Unbekannter Delivery-Typ: {$job->delivery_type}"),
            };

            Log::channel('export')->info("Delivery abgeschlossen: {$job->delivery_type}", [
                'job_id' => $job->id,
            ]);

            return ['status' => 'delivered', 'error' => null];
        } catch (\Throwable $e) {
            Log::channel('export')->error("Delivery fehlgeschlagen: {$job->delivery_type}", [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Kopiert die Datei auf einen Laravel-Storage-Disk.
     */
    private function deliverToFilesystem(array $config, string $filePath): void
    {
        $disk = $config['disk'] ?? 'exports';
        $path = rtrim($config['path'] ?? '', '/');
        $destination = $path ? "{$path}/" . basename($filePath) : basename($filePath);

        Storage::disk($disk)->put($destination, file_get_contents($filePath));
    }

    /**
     * Upload per SFTP mit on-the-fly Disk via Storage::build().
     */
    private function deliverViaSftp(array $config, string $filePath): void
    {
        $password = isset($config['password']) ? Crypt::decryptString($config['password']) : null;
        $privateKey = isset($config['private_key']) ? Crypt::decryptString($config['private_key']) : null;

        $diskConfig = [
            'driver' => 'sftp',
            'host' => $config['host'],
            'port' => (int) ($config['port'] ?? 22),
            'username' => $config['username'],
            'timeout' => 30,
        ];

        if ($password) {
            $diskConfig['password'] = $password;
        }
        if ($privateKey) {
            $diskConfig['privateKey'] = $privateKey;
        }

        $remotePath = rtrim($config['remote_path'] ?? '/', '/');
        $destination = "{$remotePath}/" . basename($filePath);

        $disk = Storage::build($diskConfig);
        $disk->put($destination, file_get_contents($filePath));
    }

    /**
     * HTTP POST/PUT mit Datei als Multipart-Upload.
     */
    private function deliverViaHttp(array $config, string $filePath): void
    {
        $url = $config['url'];
        $method = strtolower($config['method'] ?? 'POST');
        $headers = $config['headers'] ?? [];

        $request = Http::timeout(120)->withHeaders($headers);
        $fileName = basename($filePath);

        $response = $request->attach('file', file_get_contents($filePath), $fileName)
            ->{$method}($url);

        if ($response->failed()) {
            throw new \RuntimeException(
                "HTTP Delivery fehlgeschlagen: {$response->status()} {$response->body()}"
            );
        }
    }

    /**
     * Verschlüsselt sensible Felder in der delivery_config vor dem Speichern.
     */
    public static function encryptCredentials(array $config, string $deliveryType): array
    {
        if ($deliveryType === 'sftp') {
            if (!empty($config['password'])) {
                $config['password'] = Crypt::encryptString($config['password']);
            }
            if (!empty($config['private_key'])) {
                $config['private_key'] = Crypt::encryptString($config['private_key']);
            }
        }

        return $config;
    }
}
