<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtisanCockpitControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::findOrCreate('Admin', 'sanctum'));

        $this->regularUser = User::factory()->create();
    }

    public function test_non_admin_cannot_list_commands(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/admin/artisan-cockpit/commands')
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_run_commands(): void
    {
        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/admin/artisan-cockpit/run', ['command' => 'cache:clear'])
            ->assertStatus(403);
    }

    public function test_admin_can_list_commands_grouped_by_category(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/artisan-cockpit/commands')
            ->assertOk();

        $categories = $response->json('data');
        $this->assertNotEmpty($categories);
        $this->assertArrayHasKey('key', $categories[0]);
        $this->assertArrayHasKey('commands', $categories[0]);
    }

    public function test_admin_can_run_a_safe_command(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/artisan-cockpit/run', ['command' => 'cache:clear'])
            ->assertOk();

        $response->assertJsonPath('data.command', 'cache:clear');
        $response->assertJsonPath('data.success', true);
        $this->assertSame('php artisan cache:clear', $response->json('data.cli'));
    }

    public function test_non_runnable_command_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/artisan-cockpit/run', ['command' => 'migrate:fresh'])
            ->assertStatus(422);
    }

    public function test_unknown_command_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/artisan-cockpit/run', ['command' => 'not:a-real-command'])
            ->assertStatus(422);
    }

    public function test_dangerous_command_requires_confirmation(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/artisan-cockpit/run', ['command' => 'queue:restart'])
            ->assertStatus(422);
    }

    public function test_dangerous_command_runs_once_confirmed(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/artisan-cockpit/run', [
                'command' => 'queue:restart',
                'confirmed' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.success', true);
    }

    public function test_required_option_is_enforced(): void
    {
        // queue:clear verlangt die Option "queue"
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/artisan-cockpit/run', [
                'command' => 'queue:clear',
                'confirmed' => true,
                'options' => [],
            ])
            ->assertStatus(422);
    }

    public function test_only_registry_defined_options_are_forwarded_to_the_command(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/artisan-cockpit/run', [
                'command' => 'pim:content-cache-clear',
                'options' => [
                    'stats' => true,
                    // Unbekannte Option darf nicht in den gebauten Befehl gelangen
                    'malicious' => '; rm -rf /',
                ],
            ])
            ->assertOk();

        $cli = $response->json('data.cli');
        $this->assertStringContainsString('--stats', $cli);
        $this->assertStringNotContainsString('malicious', $cli);
        $this->assertStringNotContainsString('rm -rf', $cli);
    }

    public function test_string_option_value_is_shell_escaped(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/artisan-cockpit/run', [
                'command' => 'pim:content-cache-clear',
                'options' => [
                    'nav' => "foo'; touch /tmp/pwned; #",
                ],
            ])
            ->assertOk();

        // Der Payload muss als einzelnes, escapetes Shell-Argument landen (escapeshellarg
        // umschließt in einfache Anführungszeichen und escaped enthaltene Quotes) statt
        // die Shell-Befehlsgrenze aufzubrechen — sonst könnte "; touch ..." als eigener
        // Befehl ausgeführt werden.
        $cli = $response->json('data.cli');
        $this->assertMatchesRegularExpression("/--nav='foo'\\\\''; touch \\/tmp\\/pwned; #'/", $cli);
        $this->assertFileDoesNotExist('/tmp/pwned');
    }
}
