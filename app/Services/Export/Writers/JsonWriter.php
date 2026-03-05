<?php

declare(strict_types=1);

namespace App\Services\Export\Writers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class JsonWriter
{
    public function write(array $data, string $fileName): StreamedResponse
    {
        $fullName = $fileName . '.json';

        return new StreamedResponse(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$fullName}\"",
            'Cache-Control' => 'no-store',
        ]);
    }
}
