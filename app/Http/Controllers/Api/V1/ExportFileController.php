<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Filesystem Viewer für Export-Dateien.
 *
 *   GET    /api/v1/export-files          — Dateien auflisten
 *   DELETE /api/v1/export-files/{name}   — Datei löschen
 */
class ExportFileController extends Controller
{
    public function index(): JsonResponse
    {
        $disk = Storage::disk('exports');
        $files = collect($disk->files())
            ->map(fn (string $path) => [
                'name' => $path,
                'size' => $disk->size($path),
                'last_modified' => $disk->lastModified($path),
            ])
            ->sortByDesc('last_modified')
            ->values();

        return response()->json(['data' => $files]);
    }

    public function destroy(string $name): JsonResponse
    {
        $disk = Storage::disk('exports');

        // Sicherheit: Kein Pfad-Traversal erlauben
        if (str_contains($name, '..') || str_contains($name, '/')) {
            return response()->json(['error' => 'Ungültiger Dateiname.'], 400);
        }

        if (!$disk->exists($name)) {
            return response()->json(['error' => 'Datei nicht gefunden.'], 404);
        }

        $disk->delete($name);

        return response()->json(null, 204);
    }
}
