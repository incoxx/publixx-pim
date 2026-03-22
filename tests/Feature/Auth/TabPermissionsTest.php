<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\RoleTabPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TabPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $editor;
    private Role $editorRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Admin role & user
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'sanctum']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($adminRole);

        // Create Editor role with basic permissions
        $this->editorRole = Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'sanctum']);
        foreach (['products.view', 'products.edit', 'products.create', 'roles.view', 'roles.edit'] as $perm) {
            Permission::findOrCreate($perm, 'sanctum');
        }
        $this->editorRole->givePermissionTo(['products.view', 'products.edit', 'roles.view']);

        $this->editor = User::factory()->create();
        $this->editor->assignRole($this->editorRole);
    }

    // ─── API: GET /roles/{role}/tab-permissions ──────────

    public function test_admin_can_view_tab_permissions(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/roles/{$this->editorRole->id}/tab-permissions");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_returns_empty_when_no_tab_permissions_set(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/roles/{$this->editorRole->id}/tab-permissions");

        $response->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_returns_existing_tab_permissions(): void
    {
        RoleTabPermission::create([
            'role_id' => $this->editorRole->id,
            'tab_key' => 'prices',
            'access_level' => 'hidden',
        ]);
        RoleTabPermission::create([
            'role_id' => $this->editorRole->id,
            'tab_key' => 'media',
            'access_level' => 'read',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/roles/{$this->editorRole->id}/tab-permissions");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);

        $pricesEntry = collect($data)->firstWhere('tab_key', 'prices');
        $this->assertEquals('hidden', $pricesEntry['access_level']);

        $mediaEntry = collect($data)->firstWhere('tab_key', 'media');
        $this->assertEquals('read', $mediaEntry['access_level']);
    }

    // ─── API: PUT /roles/{role}/tab-permissions ──────────

    public function test_admin_can_sync_tab_permissions(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->editorRole->id}/tab-permissions", [
                'tabs' => [
                    ['tab_key' => 'prices', 'access_level' => 'hidden'],
                    ['tab_key' => 'media', 'access_level' => 'read'],
                ],
            ]);

        $response->assertOk();

        // Verify DB
        $this->assertDatabaseHas('role_tab_permissions', [
            'role_id' => $this->editorRole->id,
            'tab_key' => 'prices',
            'access_level' => 'hidden',
        ]);
        $this->assertDatabaseHas('role_tab_permissions', [
            'role_id' => $this->editorRole->id,
            'tab_key' => 'media',
            'access_level' => 'read',
        ]);
    }

    public function test_sync_replaces_existing_permissions(): void
    {
        RoleTabPermission::create([
            'role_id' => $this->editorRole->id,
            'tab_key' => 'prices',
            'access_level' => 'hidden',
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->editorRole->id}/tab-permissions", [
                'tabs' => [
                    ['tab_key' => 'media', 'access_level' => 'read'],
                ],
            ]);

        // Old entry should be gone
        $this->assertDatabaseMissing('role_tab_permissions', [
            'role_id' => $this->editorRole->id,
            'tab_key' => 'prices',
        ]);
        // New entry should exist
        $this->assertDatabaseHas('role_tab_permissions', [
            'role_id' => $this->editorRole->id,
            'tab_key' => 'media',
            'access_level' => 'read',
        ]);
    }

    public function test_sync_skips_write_entries_as_they_are_default(): void
    {
        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->editorRole->id}/tab-permissions", [
                'tabs' => [
                    ['tab_key' => 'prices', 'access_level' => 'write'],
                    ['tab_key' => 'media', 'access_level' => 'read'],
                ],
            ]);

        // 'write' entries should NOT be stored (they're the default)
        $this->assertDatabaseMissing('role_tab_permissions', [
            'role_id' => $this->editorRole->id,
            'tab_key' => 'prices',
        ]);
        $this->assertDatabaseHas('role_tab_permissions', [
            'role_id' => $this->editorRole->id,
            'tab_key' => 'media',
            'access_level' => 'read',
        ]);
    }

    // ─── Validation ──────────────────────────────────────

    public function test_rejects_invalid_tab_key(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->editorRole->id}/tab-permissions", [
                'tabs' => [
                    ['tab_key' => 'nonexistent-tab', 'access_level' => 'read'],
                ],
            ]);

        $response->assertUnprocessable();
    }

    public function test_rejects_invalid_access_level(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->editorRole->id}/tab-permissions", [
                'tabs' => [
                    ['tab_key' => 'prices', 'access_level' => 'superadmin'],
                ],
            ]);

        $response->assertUnprocessable();
    }

    public function test_rejects_duplicate_tab_keys(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->editorRole->id}/tab-permissions", [
                'tabs' => [
                    ['tab_key' => 'prices', 'access_level' => 'read'],
                    ['tab_key' => 'prices', 'access_level' => 'hidden'],
                ],
            ]);

        $response->assertUnprocessable();
    }

    public function test_rejects_missing_tabs_field(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->editorRole->id}/tab-permissions", []);

        $response->assertUnprocessable();
    }

    // ─── Auth: tab_permissions in /me response ───────────

    public function test_me_response_includes_tab_permissions(): void
    {
        RoleTabPermission::create([
            'role_id' => $this->editorRole->id,
            'tab_key' => 'prices',
            'access_level' => 'hidden',
        ]);

        $response = $this->actingAs($this->editor)
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $tabPerms = $response->json('data.tab_permissions');
        $this->assertIsArray($tabPerms);
        $this->assertEquals('hidden', $tabPerms['prices'] ?? null);
    }

    public function test_me_response_empty_tab_permissions_by_default(): void
    {
        $response = $this->actingAs($this->editor)
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $tabPerms = $response->json('data.tab_permissions');
        $this->assertEmpty((array) $tabPerms);
    }

    // ─── Multi-role merging ──────────────────────────────

    public function test_multi_role_highest_access_wins(): void
    {
        // Create a second role with different tab permissions
        $designerRole = Role::create(['name' => 'Designer', 'guard_name' => 'sanctum']);
        $designerRole->givePermissionTo(['products.view']);

        // Editor: prices=hidden, media=read
        RoleTabPermission::create([
            'role_id' => $this->editorRole->id,
            'tab_key' => 'prices',
            'access_level' => 'hidden',
        ]);
        RoleTabPermission::create([
            'role_id' => $this->editorRole->id,
            'tab_key' => 'media',
            'access_level' => 'read',
        ]);

        // Designer: prices=read, media=write (implicit, no entry)
        RoleTabPermission::create([
            'role_id' => $designerRole->id,
            'tab_key' => 'prices',
            'access_level' => 'read',
        ]);

        // Assign both roles to user
        $this->editor->assignRole($designerRole);

        $response = $this->actingAs($this->editor)
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $tabPerms = $response->json('data.tab_permissions');

        // prices: Editor=hidden, Designer=read → highest is 'read'
        $this->assertEquals('read', $tabPerms['prices'] ?? 'write');

        // media: Editor=read, Designer=no entry(=write) → highest is 'write'
        // 'write' is default, so it should NOT appear in the response
        $this->assertArrayNotHasKey('media', (array) $tabPerms);
    }

    public function test_role_without_tab_permissions_grants_full_access(): void
    {
        // Editor role has restrictions, but a second role without any grants full access
        RoleTabPermission::create([
            'role_id' => $this->editorRole->id,
            'tab_key' => 'prices',
            'access_level' => 'hidden',
        ]);

        $viewerRole = Role::create(['name' => 'Viewer', 'guard_name' => 'sanctum']);
        $viewerRole->givePermissionTo(['products.view']);
        // No tab permissions set for Viewer role → implicit write

        $this->editor->assignRole($viewerRole);

        $response = $this->actingAs($this->editor)
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $tabPerms = $response->json('data.tab_permissions');

        // Editor=hidden for prices, Viewer=write (no entry) → highest is 'write'
        $this->assertArrayNotHasKey('prices', (array) $tabPerms);
    }

    // ─── Admin bypass ────────────────────────────────────

    public function test_admin_always_has_full_access(): void
    {
        // Even if admin role has tab restrictions (shouldn't happen, but defense-in-depth)
        $adminRole = $this->admin->roles->first();
        RoleTabPermission::create([
            'role_id' => $adminRole->id,
            'tab_key' => 'prices',
            'access_level' => 'hidden',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        // Admin should still see everything - tab permissions don't affect Admin
        // (Frontend checks userRole === 'Admin' before checking tabPermissions)
    }

    // ─── Cascade delete ──────────────────────────────────

    public function test_tab_permissions_deleted_when_role_deleted(): void
    {
        RoleTabPermission::create([
            'role_id' => $this->editorRole->id,
            'tab_key' => 'prices',
            'access_level' => 'hidden',
        ]);

        $this->assertDatabaseHas('role_tab_permissions', [
            'role_id' => $this->editorRole->id,
        ]);

        $this->editorRole->delete();

        $this->assertDatabaseMissing('role_tab_permissions', [
            'role_id' => $this->editorRole->id,
        ]);
    }

    // ─── Index includes tab permissions ──────────────────

    public function test_restrictions_index_includes_tab_permissions(): void
    {
        RoleTabPermission::create([
            'role_id' => $this->editorRole->id,
            'tab_key' => 'prices',
            'access_level' => 'hidden',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/roles/{$this->editorRole->id}/restrictions");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('tab-permissions', $data);
        $this->assertCount(1, $data['tab-permissions']);
        $this->assertEquals('prices', $data['tab-permissions'][0]['tab_key']);
    }

    // ─── Authorization checks ────────────────────────────

    public function test_non_admin_without_role_permission_cannot_sync(): void
    {
        // Editor doesn't have roles.edit permission
        $response = $this->actingAs($this->editor)
            ->putJson("/api/v1/roles/{$this->editorRole->id}/tab-permissions", [
                'tabs' => [
                    ['tab_key' => 'prices', 'access_level' => 'hidden'],
                ],
            ]);

        $response->assertForbidden();
    }
}
