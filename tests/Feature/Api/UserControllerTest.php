<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $this->admin->assignRole($role);
        Sanctum::actingAs($this->admin);
    }

    public function test_index_returns_paginated_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email']],
            ]);
    }

    public function test_store_creates_user(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'securepassword123',
            'language' => 'de',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New User')
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_store_validates_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Another',
            'email' => 'taken@example.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_show_returns_user(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/v1/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_update_modifies_user(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/v1/users/{$user->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_destroy_deletes_user(): void
    {
        $user = User::factory()->create();

        $response = $this->deleteJson("/api/v1/users/{$user->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/users', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }
}
