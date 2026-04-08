<?php

declare(strict_types=1);

namespace App\Services\ApiDesigner;

use App\Models\ApiTemplate;
use App\Models\SearchProfile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiDesignerService
{
    public function __construct(
        private readonly ApiDataCollector $dataCollector,
        private readonly JsonWriter $jsonWriter,
    ) {}

    /**
     * Generate a JSON preview with limited products.
     */
    public function preview(ApiTemplate $template, ?SearchProfile $searchProfile = null, int $limit = 5): array
    {
        $searchProfile = $searchProfile ?? $template->searchProfile;

        $data = $this->dataCollector->collect($template, $searchProfile, $limit);

        return $this->jsonWriter->build(
            $data['grouped'],
            $template->template_json,
            $template->language ?? 'de',
            $data,
        );
    }

    /**
     * Stream product data as JSON response.
     */
    public function stream(
        ApiTemplate $template,
        ?SearchProfile $searchProfile = null,
        ?int $limit = null,
        int $offset = 0,
    ): StreamedResponse {
        $searchProfile = $searchProfile ?? $template->searchProfile;

        Log::channel('export')->info("API-Stream gestartet: {$template->name}", [
            'template_id' => $template->id,
            'slug'        => $template->slug,
            'limit'       => $limit,
            'offset'      => $offset,
        ]);

        $data = $this->dataCollector->collect($template, $searchProfile, $limit, $offset);

        $jsonString = $this->jsonWriter->buildString(
            $data['grouped'],
            $template->template_json,
            $template->language ?? 'de',
            $data,
        );

        Log::channel('export')->info("API-Stream abgeschlossen: {$template->name}", [
            'template_id' => $template->id,
            'products'    => $data['count'],
            'total'       => $data['total'],
        ]);

        return new StreamedResponse(function () use ($jsonString) {
            echo $jsonString;
        }, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
