<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autorisierungs-Regressionstests fuer administrative Werkzeuge (Audit M-3/M-4):
 * Lizenz-Generator, Offline-Katalog-Verwaltung und Datenbank-Viewer/-Consistency
 * duerfen ausschliesslich Admins erreichen.
 */
class AdminToolingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsNonAdmin(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Viewer', 'sanctum'));
        $this->actingAs($user);
    }

    public function test_non_admin_cannot_use_license_generator(): void
    {
        $this->actingAsNonAdmin();

        $this->getJson('/api/v1/license-generator/modules')->assertForbidden();
        $this->postJson('/api/v1/license-generator/generate-keypair')->assertForbidden();
    }

    public function test_non_admin_cannot_manage_offline_catalog(): void
    {
        $this->actingAsNonAdmin();

        $this->postJson('/api/v1/admin/offline-catalog/generate')->assertForbidden();
        $this->postJson('/api/v1/admin/offline-catalog/build-bundle')->assertForbidden();
        $this->deleteJson('/api/v1/admin/offline-catalog/cleanup')->assertForbidden();
    }

    public function test_non_admin_cannot_use_database_viewer(): void
    {
        $this->actingAsNonAdmin();

        $this->getJson('/api/v1/admin/db/tables')->assertForbidden();
        $this->getJson('/api/v1/admin/db/tables/users/rows')->assertForbidden();
    }

    public function test_non_admin_cannot_run_consistency_fix(): void
    {
        $this->actingAsNonAdmin();

        $this->postJson('/api/v1/admin/db-consistency/fix/orphaned_prices')->assertForbidden();
    }

    public function test_database_viewer_blocks_sensitive_tables_for_admin(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('Admin', 'sanctum'));
        $this->actingAs($admin);

        // Auth-/Token-Tabellen sind auch fuer Admins gesperrt (Audit M-4).
        $this->getJson('/api/v1/admin/db/tables/personal_access_tokens/rows')
            ->assertNotFound();
    }
}
