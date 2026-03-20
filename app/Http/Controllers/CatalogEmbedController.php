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
        $templateName = $template;

        if ($dbTemplate) {
            $html = $dbTemplate->html_template;
            $templateName = $dbTemplate->name;
        } else {
            // Fallback to file-based template
            $templatePath = base_path("catalog-embed/examples/{$template}.html");

            if (! File::exists($templatePath)) {
                abort(404, "Catalog template '{$template}' not found.");
            }

            $html = File::get($templatePath);
        }

        $html = $this->injectAssets($html, $templateName);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    private function injectAssets(string $html, string $templateName = 'basic'): string
    {
        $basePath = rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/');

        $assetsBase = $basePath . '/catalog-embed-assets';

        // Replace any script src pointing to catalog-embed JS (dev path or previous deploy path)
        $html = preg_replace(
            '/src=["\'][^"\']*catalog-embed\.umd\.js["\']/',
            'src="' . $assetsBase . '/catalog-embed.umd.js"',
            $html,
        );

        // Replace any CSS href pointing to catalog-embed CSS
        $html = preg_replace(
            '/href=["\'][^"\']*catalog-embed\.css["\']/',
            'href="' . $assetsBase . '/catalog-embed.css"',
            $html,
        );

        // Inject CSS link if not already present
        if (! str_contains($html, 'catalog-embed.css')) {
            $html = str_replace(
                '</head>',
                '  <link rel="stylesheet" href="' . $assetsBase . '/catalog-embed.css">' . "\n" . '</head>',
                $html,
            );
        }

        // Inject JS if not already present
        if (! str_contains($html, 'catalog-embed.umd.js') && ! str_contains($html, 'catalog-embed-assets')) {
            $html = str_replace(
                '</body>',
                '  <script src="' . $assetsBase . '/catalog-embed.umd.js"></script>' . "\n" . '</body>',
                $html,
            );
        }

        // Replace API URL with the correct app URL
        // Handles localhost dev URLs and previously saved production URLs
        $apiBase = rtrim(config('app.url'), '/') . '/api/v1';
        $html = str_replace(
            'http://localhost:8000/api/v1',
            $apiBase,
            $html,
        );
        // Also replace any other API base URLs in PublixxCatalog.init() calls
        $html = preg_replace(
            '/api:\s*[\'"]https?:\/\/[^"\']+\/api\/v1[\'"]/',
            "api: '{$apiBase}'",
            $html,
        );

        // Ensure sidebar-toggle is present (needed for mobile hamburger menu).
        if (! str_contains($html, 'data-catalog="sidebar-toggle"')) {
            $html = preg_replace(
                '/<body[^>]*>/i',
                '$0' . "\n" . '  <div data-catalog="sidebar-toggle"></div>',
                $html,
                1
            );
        }
        // Strip old inline fixed positioning from sidebar-toggle (caused overlap with search)
        $html = preg_replace(
            '/(<div\s+data-catalog="sidebar-toggle")\s+style="[^"]*"/',
            '$1',
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

        // Build release info (HTML comment + info icon)
        $jsPath = public_path('catalog-embed-assets/catalog-embed.umd.js');
        $cssPath = public_path('catalog-embed-assets/catalog-embed.css');
        $date = now()->format('Y-m-d H:i:s');
        $jsHash = file_exists($jsPath) ? substr(md5_file($jsPath), 0, 8) : '-';
        $cssHash = file_exists($cssPath) ? substr(md5_file($cssPath), 0, 8) : '-';
        $tplName = e($templateName);

        $releaseInfo = <<<HTML

  <!-- ════════════════════════════════════════════════
       PublixxCatalog Online
       Rendered: {$date}
       JS:       {$jsHash}
       CSS:      {$cssHash}
       Template: {$tplName}
       ════════════════════════════════════════════════ -->
  <div id="pxc-release-info" style="position:fixed;bottom:12px;right:12px;z-index:9000">
    <button onclick="document.getElementById('pxc-release-popup').style.display=document.getElementById('pxc-release-popup').style.display==='none'?'block':'none'"
      style="width:28px;height:28px;border-radius:50%;border:1px solid #ccc;background:#fff;color:#888;font-size:14px;cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,0.15);display:flex;align-items:center;justify-content:center"
      title="Build-Info">&nbsp;&#x2139;&nbsp;</button>
    <div id="pxc-release-popup" style="display:none;position:absolute;bottom:36px;right:0;background:#fff;border:1px solid #ddd;border-radius:6px;padding:12px 16px;font-family:monospace;font-size:12px;line-height:1.6;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,0.15);color:#333">
      <div style="font-weight:700;margin-bottom:4px;font-size:13px;color:#004588">PublixxCatalog Online</div>
      <div>Rendered: {$date}</div>
      <div>JS:  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{$jsHash}</div>
      <div>CSS: &nbsp;&nbsp;&nbsp;&nbsp;{$cssHash}</div>
      <div>Template: {$tplName}</div>
    </div>
  </div>
HTML;

        if (str_contains($html, '</body>')) {
            $html = str_replace('</body>', $releaseInfo . "\n</body>", $html);
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
