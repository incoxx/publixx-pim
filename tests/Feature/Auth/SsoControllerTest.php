<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SsoControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sso.enabled' => true,
            'sso.auto_provision' => true,
            'sso.default_role' => 'Viewer',
            'services.azure.client_id' => 'test-client-id',
            'services.azure.client_secret' => 'test-secret',
            'services.azure.redirect' => 'http://localhost/api/v1/auth/sso/callback',
            'services.azure.tenant' => 'test-tenant',
            'app.frontend_url' => 'http://localhost:3000',
        ]);
    }

    public function test_sso_config_returns_enabled_status(): void
    {
        $response = $this->getJson('/api/v1/auth/sso/config');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'enabled' => true,
                    'provider' => 'azure',
                ],
            ]);
    }

    public function test_sso_config_returns_disabled_when_not_configured(): void
    {
        config(['services.azure.client_id' => null]);

        $response = $this->getJson('/api/v1/auth/sso/config');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'enabled' => false,
                ],
            ]);
    }

    public function test_sso_config_returns_disabled_when_sso_off(): void
    {
        config(['sso.enabled' => false]);

        $response = $this->getJson('/api/v1/auth/sso/config');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'enabled' => false,
                ],
            ]);
    }

    public function test_sso_redirect_returns_404_when_disabled(): void
    {
        config(['sso.enabled' => false]);

        $response = $this->getJson('/api/v1/auth/sso/redirect');

        $response->assertStatus(404);
    }

    public function test_sso_config_includes_label(): void
    {
        $response = $this->getJson('/api/v1/auth/sso/config');

        $response->assertOk()
            ->assertJsonPath('data.label', 'Mit Microsoft anmelden');
    }
}
