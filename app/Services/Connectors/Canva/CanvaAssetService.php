<?php

declare(strict_types=1);

namespace App\Services\Connectors\Canva;

use App\Models\ConnectorConnection;
use App\Models\Media;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class CanvaAssetService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('connectors.canva.base_url');
    }

    /**
     * Lädt ein Media-Asset per URL-basiertem Upload zu Canva hoch.
     *
     * @return string Canva Asset-ID
     */
    public function uploadFromUrl(PendingRequest $http, Media $media, string $publicUrl): string
    {
        $response = $http->post("{$this->baseUrl}/asset-uploads", [
            'job' => [
                'type' => 'url',
                'url'  => $publicUrl,
                'name' => $media->title_de ?: $media->file_name,
            ],
        ]);

        $response->throw();
        $data = $response->json();

        return $data['job']['id'];
    }

    /**
     * Prüft den Status eines Asset-Upload-Jobs.
     *
     * @return array{status: string, asset: array|null}
     */
    public function getUploadJobStatus(PendingRequest $http, string $jobId): array
    {
        $response = $http->get("{$this->baseUrl}/asset-uploads/{$jobId}");
        $response->throw();

        $data = $response->json();

        return [
            'status' => $data['job']['status'] ?? 'unknown',
            'asset'  => $data['job']['asset'] ?? null,
        ];
    }

    /**
     * Listet Assets des verbundenen Canva-Benutzers.
     */
    public function listAssets(PendingRequest $http, int $limit = 50, ?string $continuation = null): array
    {
        $params = ['limit' => $limit];
        if ($continuation) {
            $params['continuation'] = $continuation;
        }

        $response = $http->get("{$this->baseUrl}/assets", $params);
        $response->throw();

        return $response->json();
    }
}
