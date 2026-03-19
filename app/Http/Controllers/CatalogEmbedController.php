<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CatalogTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves customizable catalog-embed HTML templates.
 *
 * Templates can come from:
 *  1. Database (CatalogTemplate model) — managed via the UI
 *  2. File-based fallback in catalog-embed/examples/
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

        // Try database template first
        $dbTemplate = CatalogTemplate::where('slug', $template)->where('is_active', true)->first();

        if ($dbTemplate) {
            $html = $dbTemplate->html_template;
        } else {
            // Fallback to file-based template
            $templatePath = base_path("catalog-embed/examples/{$template}.html");

            if (! File::exists($templatePath)) {
                abort(404, "Catalog template '{$template}' not found.");
            }

            $html = File::get($templatePath);
        }

        $html = $this->injectAssets($html);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    private function injectAssets(string $html): string
    {
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

        // Inject the JS if not already present
        if (! str_contains($html, 'catalog-embed.umd.js') && ! str_contains($html, 'catalog-embed-assets')) {
            $html = str_replace(
                '</body>',
                '  <script src="' . $basePath . '/catalog-embed-assets/catalog-embed.umd.js"></script>' . "\n" . '</body>',
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

        // Ensure essential hidden widget placeholders are present (wishlist drawer,
        // product detail modal, compare modal). DB templates might omit these.
        $requiredWidgets = ['wishlist', 'product-detail', 'compare'];
        $hiddenWidgetHtml = '';
        foreach ($requiredWidgets as $widget) {
            if (! str_contains($html, 'data-catalog="' . $widget . '"')) {
                $hiddenWidgetHtml .= '  <div data-catalog="' . $widget . '"></div>' . "\n";
            }
        }
        if ($hiddenWidgetHtml && str_contains($html, '</body>')) {
            $html = str_replace('</body>', $hiddenWidgetHtml . '</body>', $html);
        }

        return $html;
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
        $basePath = rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/');
        $links = [];

        // Database templates
        $dbTemplates = CatalogTemplate::where('is_active', true)->orderBy('name')->get();
        foreach ($dbTemplates as $t) {
            $links[] = "<li><a href=\"{$basePath}/catalog-embed/{$t->slug}\">{$t->name}</a>"
                . ($t->description ? " <span style=\"color:#888;font-size:0.85rem\">— {$t->description}</span>" : '')
                . '</li>';
        }

        // File-based templates
        $dir = base_path('catalog-embed/examples');
        $fileTemplates = [];

        if (File::isDirectory($dir)) {
            foreach (File::files($dir) as $file) {
                if ($file->getExtension() === 'html') {
                    $name = $file->getFilenameWithoutExtension();
                    // Skip if a DB template with the same slug exists
                    if ($dbTemplates->contains('slug', $name)) {
                        continue;
                    }
                    $fileTemplates[] = $name;
                }
            }
        }

        sort($fileTemplates);
        foreach ($fileTemplates as $t) {
            $links[] = "<li><a href=\"{$basePath}/catalog-embed/{$t}\">{$t}</a> <span style=\"color:#888;font-size:0.85rem\">— Datei</span></li>";
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<title>Catalog Embed Templates</title>'
            . '<style>body{font-family:system-ui,sans-serif;max-width:600px;margin:40px auto;padding:0 20px}'
            . 'h1{font-size:1.5rem}li{margin:8px 0}a{color:#004588;font-size:1.1rem}</style>'
            . '</head><body>'
            . '<h1>Verfügbare Katalog-Templates</h1>'
            . '<ul>' . implode("\n", $links) . '</ul>'
            . '</body></html>';

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
