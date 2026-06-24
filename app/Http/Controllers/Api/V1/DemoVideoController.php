<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

/**
 * Öffentlicher Endpunkt für Demo-/Tutorial-Videos auf der Login-Seite.
 *
 * Listet alle .mp4-Dateien aus public/videos auf und reichert sie — sofern
 * vorhanden — mit Titel und Beschreibung aus den Story-Definitionen unter
 * video-stories/{slug}/story.yaml an. Kein Auth, da die Login-Seite vor der
 * Anmeldung darauf zugreift.
 */
final class DemoVideoController extends Controller
{
    public function index(): JsonResponse
    {
        $dir = public_path('videos');
        $videos = [];

        if (is_dir($dir)) {
            $files = glob($dir . '/*.mp4') ?: [];
            sort($files, SORT_NATURAL);

            foreach ($files as $file) {
                $filename = basename($file);
                $slug = pathinfo($filename, PATHINFO_FILENAME);
                $meta = $this->storyMeta($slug);

                $videos[] = [
                    'slug' => $slug,
                    'title' => $meta['title'] ?? $this->prettify($slug),
                    'description' => $meta['description'],
                    'url' => '/videos/' . rawurlencode($filename),
                ];
            }
        }

        return response()->json(['data' => $videos]);
    }

    /**
     * Liest Titel und Beschreibung aus dem meta-Block einer story.yaml.
     * Bewusst ein minimaler Parser, um keine zusätzliche YAML-Abhängigkeit
     * einzuführen — es werden nur die zwei benötigten Felder gelesen.
     *
     * @return array{title: ?string, description: ?string}
     */
    private function storyMeta(string $slug): array
    {
        $path = base_path('video-stories/' . $slug . '/story.yaml');
        $title = null;
        $description = null;

        if (is_file($path)) {
            $yaml = (string) file_get_contents($path);

            // Nur den meta-Block (bis zur ersten Top-Level-Sektion) auswerten.
            $metaBlock = $yaml;
            if (preg_match('/\nsteps:/', $yaml, $m, PREG_OFFSET_CAPTURE)) {
                $metaBlock = substr($yaml, 0, $m[0][1]);
            }

            if (preg_match('/^\s+title:\s*"?(.+?)"?\s*$/m', $metaBlock, $m)) {
                $title = trim($m[1]);
            }
            if (preg_match('/^\s+description:\s*"?(.+?)"?\s*$/m', $metaBlock, $m)) {
                $description = trim($m[1]);
            }
        }

        return ['title' => $title, 'description' => $description];
    }

    /**
     * Erzeugt aus einem Dateinamen-Slug einen lesbaren Titel als Fallback.
     * Beispiel: "01-produktanlage" → "Produktanlage".
     */
    private function prettify(string $slug): string
    {
        $s = preg_replace('/^\d+[-_]/', '', $slug) ?? $slug;
        $s = str_replace(['-', '_'], ' ', $s);

        return ucwords(trim($s));
    }
}
