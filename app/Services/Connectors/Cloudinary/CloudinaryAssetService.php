<?php

declare(strict_types=1);

namespace App\Services\Connectors\Cloudinary;

use App\Models\Media;
use Illuminate\Support\Facades\Http;

class CloudinaryAssetService
{
    /**
     * Lädt ein Media-Asset zu Cloudinary hoch.
     *
     * @param  string       $folder  Zielordner in Cloudinary
     * @param  string[]     $tags    Tags für das Asset
     * @return array  Cloudinary Upload-Response
     */
    public function upload(
        string $cloudName,
        string $apiKey,
        string $apiSecret,
        Media $media,
        string $folder = 'pim',
        array $tags = [],
    ): array {
        $publicUrl = url("/api/v1/media/file/{$media->file_name}");
        $timestamp = time();

        $params = [
            'folder'     => $folder,
            'public_id'  => pathinfo($media->file_name, PATHINFO_FILENAME),
            'tags'       => implode(',', $tags),
            'timestamp'  => $timestamp,
            'overwrite'  => 'true',
        ];

        // Signatur generieren
        $params['signature'] = $this->generateSignature($params, $apiSecret);
        $params['api_key'] = $apiKey;
        $params['file'] = $publicUrl;

        $resourceType = $this->getResourceType($media->mime_type);

        $response = Http::timeout(60)
            ->asMultipart()
            ->post(
                "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload",
                $params,
            );

        $response->throw();

        return $response->json();
    }

    /**
     * Generiert eine Transformation-URL.
     */
    public function transformUrl(
        string $cloudName,
        string $publicId,
        array $transformations = [],
    ): string {
        $transforms = [];
        foreach ($transformations as $key => $value) {
            $transforms[] = "{$key}_{$value}";
        }

        $transformString = implode(',', $transforms);
        $base = "https://res.cloudinary.com/{$cloudName}/image/upload";

        return $transformString
            ? "{$base}/{$transformString}/{$publicId}"
            : "{$base}/{$publicId}";
    }

    /**
     * Löscht ein Asset aus Cloudinary.
     */
    public function delete(string $cloudName, string $apiKey, string $apiSecret, string $publicId): array
    {
        $timestamp = time();

        $params = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];

        $params['signature'] = $this->generateSignature($params, $apiSecret);
        $params['api_key'] = $apiKey;

        $response = Http::timeout(15)
            ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy", $params);

        $response->throw();

        return $response->json();
    }

    /**
     * Generiert die Cloudinary API-Signatur.
     */
    private function generateSignature(array $params, string $apiSecret): string
    {
        // Nur relevante Parameter für die Signatur (kein api_key, signature, file)
        $signParams = array_filter($params, fn ($key) => ! in_array($key, ['api_key', 'signature', 'file']), ARRAY_FILTER_USE_KEY);
        ksort($signParams);

        $signString = http_build_query($signParams) . $apiSecret;

        return sha1($signString);
    }

    private function getResourceType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mimeType, 'application/') || str_starts_with($mimeType, 'text/')) {
            return 'raw';
        }

        return 'image';
    }
}
