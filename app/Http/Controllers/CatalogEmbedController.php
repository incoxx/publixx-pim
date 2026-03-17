<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves customizable catalog-embed HTML templates.
 *
 * Templates live in catalog-embed/examples/ and are served
 * under /catalog-embed/{template}. The controller injects
 * the correct API base URL and asset paths so templates
 * work out-of-the-box without manual configuration.
 *
 * Supports subdirectory installs (e.g. https://example.com/pim/)
 * by resolving the base path from APP_URL.
 */
class CatalogEmbedController extends Controller
{
    public function show(Request $request, string $template = 'basic'): Response
    {
        // Sanitize: only allow alphanumeric, hyphens, underscores
        $template = preg_replace('/[^a-zA-Z0-9_-]/', '', $template);

        $templatePath = base_path("catalog-embed/examples/{$template}.html");

        if (! File::exists($templatePath)) {
            abort(404, "Catalog template '{$template}' not found.");
        }

        $html = File::get($templatePath);

        // Resolve base path for subdirectory support
        // e.g. APP_URL=https://example.com/pim → basePath="/pim"
        $basePath = rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/');

        // Replace the script src to point to the public assets (subdirectory-aware)
        $html = str_replace(
            '../dist/catalog-embed.umd.js',
            $basePath . '/catalog-embed-assets/catalog-embed.umd.js',
            $html,
        );

        // Inject the CSS link if not already present
        if (! str_contains($html, 'catalog-embed.css')) {
            $html = str_replace(
                '</head>',
                '  <link rel="stylesheet" href="' . $basePath . '/catalog-embed-assets/catalog-embed.css">' . "\n" . '</head>',
                $html,
            );
        }

        // Replace localhost API URL with the actual app URL
        $apiBase = rtrim(config('app.url'), '/') . '/api/v1';
        $html = str_replace(
            'http://localhost:8000/api/v1',
            $apiBase,
            $html,
        );

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Serve catalog-embed static assets (JS/CSS) with correct MIME types.
     *
     * Needed because Apache subdirectory installs route all requests through
     * Laravel, so static files in public/ are not served directly.
     */
    public function asset(string $file): BinaryFileResponse
    {
        $allowed = ['catalog-embed.umd.js', 'catalog-embed.css'];

        if (! in_array($file, $allowed, true)) {
            abort(404);
        }

        $path = public_path("catalog-embed-assets/{$file}");

        if (! File::exists($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    /**
     * List available templates.
     */
    public function index(Request $request): Response
    {
        $dir = base_path('catalog-embed/examples');
        $templates = [];

        if (File::isDirectory($dir)) {
            foreach (File::files($dir) as $file) {
                if ($file->getExtension() === 'html') {
                    $templates[] = $file->getFilenameWithoutExtension();
                }
            }
        }

        sort($templates);

        // Resolve base path for subdirectory support
        $basePath = rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/');

        $links = array_map(
            fn ($t) => "<li><a href=\"{$basePath}/catalog-embed/{$t}\">{$t}</a></li>",
            $templates,
        );

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<title>Catalog Embed Templates</title>'
            . '<style>body{font-family:system-ui,sans-serif;max-width:600px;margin:40px auto;padding:0 20px}'
            . 'h1{font-size:1.5rem}li{margin:8px 0}a{color:#004588;font-size:1.1rem}</style>'
            . '</head><body>'
            . '<h1>Verfügbare Katalog-Templates</h1>'
            . '<ul>' . implode("\n", $links) . '</ul>'
            . '<p style="margin-top:32px;color:#888;font-size:0.875rem">'
            . 'Templates liegen in <code>catalog-embed/examples/</code></p>'
            . '</body></html>';

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
