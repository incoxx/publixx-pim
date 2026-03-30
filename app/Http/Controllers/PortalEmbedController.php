<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PortalConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Rendert Portal-Vorschaltseiten mit injiziertem portal-embed.umd.js.
 *
 * Analog zu CatalogEmbedController — laedt HTML-Templates aus DB oder Datei,
 * injiziert Assets und Portal-Config.
 */
class PortalEmbedController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);

        $portal = PortalConfig::where('slug', $slug)->where('is_active', true)->first();

        if ($portal) {
            $html = $portal->html_template;
        } else {
            $templatePath = base_path("portal-embed/examples/{$slug}.html");
            if (!File::exists($templatePath)) {
                abort(404, "Portal '{$slug}' nicht gefunden.");
            }
            $html = File::get($templatePath);
        }

        $html = $this->injectAssets($html, $slug);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function asset(Request $request, string $file): BinaryFileResponse|Response
    {
        $allowed = ['portal-embed.umd.js', 'portal-embed.css'];
        if (!in_array($file, $allowed, true)) {
            abort(404);
        }

        $path = base_path('portal-embed/dist/' . $file);
        if (!File::exists($path)) {
            abort(404, "Asset '{$file}' not found.");
        }

        $mime = str_ends_with($file, '.js') ? 'application/javascript' : 'text/css';

        return response()->file($path, [
            'Content-Type' => $mime . '; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function injectAssets(string $html, string $slug): string
    {
        $basePath = rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/');
        $assetsBase = $basePath . '/portal-embed-assets';

        // JS-Pfad ersetzen
        $html = preg_replace(
            '/src=["\'][^"\']*portal-embed\.umd\.js["\']/',
            'src="' . $assetsBase . '/portal-embed.umd.js"',
            $html,
        );

        // CSS-Pfad ersetzen
        $html = preg_replace(
            '/href=["\'][^"\']*portal-embed\.css["\']/',
            'href="' . $assetsBase . '/portal-embed.css"',
            $html,
        );

        // CSS injizieren falls nicht vorhanden
        if (!str_contains($html, 'portal-embed.css')) {
            $html = str_replace(
                '</head>',
                '  <link rel="stylesheet" href="' . $assetsBase . '/portal-embed.css">' . "\n" . '</head>',
                $html,
            );
        }

        // API-URL ersetzen in PortalEmbed.init() Aufrufen
        $apiUrl = rtrim(config('app.url'), '/') . '/api/v1';
        $html = preg_replace(
            "/api:\s*['\"][^'\"]+['\"]/",
            "api: '" . $apiUrl . "'",
            $html,
        );

        // Slug injizieren falls Platzhalter
        $html = str_replace("slug: '__SLUG__'", "slug: '" . $slug . "'", $html);

        return $html;
    }
}
