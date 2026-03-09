<?php

declare(strict_types=1);

namespace App\Services\Export\Writers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class JsonWriter
{
    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function write(array $data, string $fileName): StreamedResponse
    {
        $fullName = $fileName . '.json';

        return new StreamedResponse(function () use ($data) {
            echo json_encode($data, self::JSON_FLAGS);
        }, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$fullName}\"",
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Returns JSON as an inline API response (no Content-Disposition / no download).
     */
    public static function asInlineResponse(array $data): \Illuminate\Http\JsonResponse
    {
        return response()->json($data, 200, [], self::JSON_FLAGS);
    }
}
