<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Die robots.txt verweist auf die sitemap.xml der Doku. Weil der Doku-Pfad je nach
 * Installationsart abweicht (Root-Modus "/web/help/" vs. Unterverzeichnis
 * "${WEB_PATH}/help/"), werden hier beide Varianten abgesichert.
 */
class RobotsTxtTest extends TestCase
{
    public function test_liefert_text_plain_und_wird_nicht_vom_spa_catchall_verschluckt(): void
    {
        config(['app.url' => 'https://example.com']);

        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        // Gegenprobe: Das SPA-Catch-all wuerde HTML ausliefern.
        $this->assertStringNotContainsString('<html', $response->getContent());
    }

    public function test_root_modus_verweist_auf_web_help(): void
    {
        config(['app.url' => 'https://example.com']);

        $content = $this->get('/robots.txt')->getContent();

        // Exakter Volltext -- schlaegt fehl, sobald das SPA-Catch-all die Route
        // verschluckt oder sich die Pfadableitung aendert.
        $this->assertSame(
            "User-agent: *\n"
            ."Disallow: /\n"
            ."Allow: /web/help/\n"
            ."\n"
            ."Sitemap: https://example.com/web/help/sitemap.xml\n",
            $content
        );
    }

    public function test_unterverzeichnis_modus_verweist_auf_den_web_pfad(): void
    {
        config(['app.url' => 'https://example.com/pim']);

        $content = $this->get('/robots.txt')->getContent();

        $this->assertStringContainsString('Allow: /pim/help/', $content);
        $this->assertStringContainsString(
            'Sitemap: https://example.com/pim/help/sitemap.xml',
            $content
        );
        // Der Root-Modus-Pfad darf im Unterverzeichnis-Modus nicht auftauchen.
        $this->assertStringNotContainsString('/web/help/', $content);
    }

    public function test_ist_restriktiv_und_sperrt_alles_ausser_der_doku(): void
    {
        config(['app.url' => 'https://example.com']);

        $content = $this->get('/robots.txt')->getContent();

        $this->assertStringContainsString('User-agent: *', $content);
        $this->assertStringContainsString('Disallow: /', $content);
    }

    public function test_trailing_slash_in_app_url_erzeugt_keine_doppelten_slashes(): void
    {
        config(['app.url' => 'https://example.com/pim/']);

        $content = $this->get('/robots.txt')->getContent();

        $this->assertStringContainsString(
            'Sitemap: https://example.com/pim/help/sitemap.xml',
            $content
        );
        $this->assertStringNotContainsString('//sitemap', $content);
        $this->assertStringNotContainsString('help//', $content);
    }
}
