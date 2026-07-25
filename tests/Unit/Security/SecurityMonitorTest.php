<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Services\Security\SecurityMonitor;
use Illuminate\Http\Request;
use Tests\TestCase;

class SecurityMonitorTest extends TestCase
{
    private SecurityMonitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitor = new SecurityMonitor;

        config([
            'security.enabled' => true,
            'security.block' => true,
            'security.geo.country_header' => 'CF-IPCountry',
            'security.geo.allowed_countries' => [],
            'security.geo.blocked_countries' => [],
            'security.ip_header' => 'CF-Connecting-IP',
            'security.bots.flag_empty_user_agent' => true,
            'security.bots.user_agent_patterns' => ['curl', 'sqlmap', 'python-requests'],
            'security.sensitive_path_patterns' => ['api/v1/auth', 'api/v1/admin'],
        ]);
    }

    public function test_detects_known_bot_user_agents(): void
    {
        $this->assertTrue($this->monitor->isBotUserAgent('curl/8.4.0'));
        $this->assertTrue($this->monitor->isBotUserAgent('sqlmap/1.7'));
        $this->assertTrue($this->monitor->isBotUserAgent('')); // leerer UA gilt als verdaechtig
        $this->assertFalse($this->monitor->isBotUserAgent('Mozilla/5.0 (Macintosh) Safari/605'));
    }

    public function test_blocked_country_is_suspicious(): void
    {
        config(['security.geo.blocked_countries' => ['RU', 'KP']]);

        $this->assertTrue($this->monitor->isCountrySuspicious('RU'));
        $this->assertFalse($this->monitor->isCountrySuspicious('DE'));
        $this->assertFalse($this->monitor->isCountrySuspicious(null));
    }

    public function test_allowlist_makes_other_countries_suspicious(): void
    {
        config(['security.geo.allowed_countries' => ['DE', 'AT', 'CH']]);

        $this->assertFalse($this->monitor->isCountrySuspicious('DE'));
        $this->assertTrue($this->monitor->isCountrySuspicious('US'));
    }

    public function test_resolves_country_from_cloudflare_header(): void
    {
        $request = Request::create('/api/v1/catalog/products', 'GET');
        $request->headers->set('CF-IPCountry', 'de');

        $this->assertSame('DE', $this->monitor->country($request));
    }

    public function test_resolves_client_ip_from_header(): void
    {
        $request = Request::create('/api/v1/auth/login', 'POST');
        $request->headers->set('CF-Connecting-IP', '203.0.113.9, 10.0.0.1');

        $this->assertSame('203.0.113.9', $this->monitor->clientIp($request));
    }

    public function test_identifies_sensitive_paths(): void
    {
        $this->assertTrue($this->monitor->isSensitivePath(Request::create('/api/v1/admin/db/tables', 'GET')));
        $this->assertTrue($this->monitor->isSensitivePath(Request::create('/api/v1/auth/login', 'POST')));
        $this->assertFalse($this->monitor->isSensitivePath(Request::create('/api/v1/catalog/products', 'GET')));
    }
}
