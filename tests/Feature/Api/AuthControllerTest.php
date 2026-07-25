<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'expires_at', 'user'],
            ])
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_with_invalid_password_returns_401(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('type', 'https://anypim.local/problems/auth/invalid-credentials');
    }

    public function test_login_with_nonexistent_email_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $response->assertUnauthorized();
    }

    public function test_login_with_inactive_user_returns_403(): void
    {
        $user = User::factory()->inactive()->create([
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('type', 'https://anypim.local/problems/auth/account-deactivated');
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
            'last_login_at' => null,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * Regressionstest fuer den Account-Lockout (Audit M-5): Nach 5 Fehlversuchen
     * ist das Konto gesperrt — auch ein danach korrektes Passwort wird mit 429
     * abgewiesen, unabhaengig von der Angreifer-IP.
     */
    public function test_login_locks_out_after_repeated_failures(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertStatus(429);
    }

    public function test_logout_deletes_current_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        // Login to get a real token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
        $token = $loginResponse->json('data.token');

        $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Successfully logged out.');
    }

    public function test_refresh_returns_new_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
        $token = $loginResponse->json('data.token');

        $response = $this->withToken($token)->postJson('/api/v1/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'expires_at'],
            ]);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
        $token = $loginResponse->json('data.token');

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_without_auth_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }
}
