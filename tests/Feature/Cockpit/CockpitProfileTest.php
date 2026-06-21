<?php

declare(strict_types=1);

namespace Tests\Feature\Cockpit;

use App\Models\CockpitProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autorisierung der rollenbasierten Cockpit-Layouts.
 *
 * Lesen (Rollenliste, Layout einer Rolle): cockpit-layouts.view
 * Verwalten (anlegen/aktualisieren/löschen): cockpit-layouts.edit
 * Eigenes Layout (mine): jede:r authentifizierte Nutzer:in.
 */
class CockpitProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;   // view + edit
    private User $viewer;    // nur view
    private User $outsider;  // keine Cockpit-Layout-Rechte
    private Role $targetRole;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['cockpit-layouts.view', 'cockpit-layouts.edit', 'products.view'] as $perm) {
            Permission::findOrCreate($perm, 'sanctum');
        }

        // Rolle, deren Layout im Test verwaltet wird
        $this->targetRole = Role::firstOrCreate(['name' => 'Marketing', 'guard_name' => 'sanctum']);

        $managerRole = Role::firstOrCreate(['name' => 'Layout Manager', 'guard_name' => 'sanctum']);
        $managerRole->givePermissionTo(['cockpit-layouts.view', 'cockpit-layouts.edit']);
        $this->manager = User::factory()->create();
        $this->manager->assignRole($managerRole);

        $viewerRole = Role::firstOrCreate(['name' => 'Layout Viewer', 'guard_name' => 'sanctum']);
        $viewerRole->givePermissionTo(['cockpit-layouts.view']);
        $this->viewer = User::factory()->create();
        $this->viewer->assignRole($viewerRole);

        $outsiderRole = Role::firstOrCreate(['name' => 'Outsider', 'guard_name' => 'sanctum']);
        $outsiderRole->givePermissionTo(['products.view']);
        $this->outsider = User::factory()->create();
        $this->outsider->assignRole($outsiderRole);
    }

    // ─── GET /cockpit-profiles/roles ──────────────────────

    public function test_view_permission_can_list_roles(): void
    {
        $this->actingAs($this->viewer)
            ->getJson('/api/v1/cockpit-profiles/roles')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'has_custom_layout']]]);
    }

    public function test_role_list_reports_custom_layout_flag(): void
    {
        CockpitProfile::create([
            'role_id' => $this->targetRole->id,
            'layout' => ['tiles' => ['products'], 'workplace' => [], 'content' => [], 'kpis' => []],
        ]);

        $data = $this->actingAs($this->manager)
            ->getJson('/api/v1/cockpit-profiles/roles')
            ->assertOk()
            ->json('data');

        $entry = collect($data)->firstWhere('id', $this->targetRole->id);
        $this->assertNotNull($entry);
        $this->assertTrue($entry['has_custom_layout']);
    }

    public function test_outsider_cannot_list_roles(): void
    {
        $this->actingAs($this->outsider)
            ->getJson('/api/v1/cockpit-profiles/roles')
            ->assertForbidden();
    }

    // ─── GET /cockpit-profiles/{role} ─────────────────────

    public function test_view_permission_can_read_role_layout(): void
    {
        $this->actingAs($this->viewer)
            ->getJson("/api/v1/cockpit-profiles/{$this->targetRole->id}")
            ->assertOk()
            ->assertJson(['data' => null]); // noch kein eigenes Layout
    }

    public function test_outsider_cannot_read_role_layout(): void
    {
        $this->actingAs($this->outsider)
            ->getJson("/api/v1/cockpit-profiles/{$this->targetRole->id}")
            ->assertForbidden();
    }

    // ─── PUT /cockpit-profiles/{role} ─────────────────────

    public function test_edit_permission_can_create_layout(): void
    {
        $payload = [
            'tiles' => ['products', 'preview'],
            'workplace' => ['watchlist'],
            'content' => [],
            'kpis' => ['completeness'],
        ];

        $this->actingAs($this->manager)
            ->putJson("/api/v1/cockpit-profiles/{$this->targetRole->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.tiles', ['products', 'preview']);

        $this->assertDatabaseHas('cockpit_profiles', ['role_id' => $this->targetRole->id]);
    }

    public function test_view_only_user_cannot_create_layout(): void
    {
        $this->actingAs($this->viewer)
            ->putJson("/api/v1/cockpit-profiles/{$this->targetRole->id}", ['tiles' => ['products']])
            ->assertForbidden();

        $this->assertDatabaseMissing('cockpit_profiles', ['role_id' => $this->targetRole->id]);
    }

    // ─── DELETE /cockpit-profiles/{role} ──────────────────

    public function test_edit_permission_can_delete_layout(): void
    {
        CockpitProfile::create([
            'role_id' => $this->targetRole->id,
            'layout' => ['tiles' => ['products'], 'workplace' => [], 'content' => [], 'kpis' => []],
        ]);

        $this->actingAs($this->manager)
            ->deleteJson("/api/v1/cockpit-profiles/{$this->targetRole->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('cockpit_profiles', ['role_id' => $this->targetRole->id]);
    }

    public function test_view_only_user_cannot_delete_layout(): void
    {
        CockpitProfile::create([
            'role_id' => $this->targetRole->id,
            'layout' => ['tiles' => ['products'], 'workplace' => [], 'content' => [], 'kpis' => []],
        ]);

        $this->actingAs($this->viewer)
            ->deleteJson("/api/v1/cockpit-profiles/{$this->targetRole->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('cockpit_profiles', ['role_id' => $this->targetRole->id]);
    }

    // ─── GET /cockpit-profiles/mine ───────────────────────

    public function test_any_authenticated_user_can_read_own_layout(): void
    {
        CockpitProfile::create([
            'role_id' => $this->outsider->roles->first()->id,
            'layout' => ['tiles' => ['products'], 'workplace' => [], 'content' => [], 'kpis' => []],
        ]);

        // Outsider hat keine cockpit-layouts-Rechte, darf sein eigenes Layout aber lesen
        $this->actingAs($this->outsider)
            ->getJson('/api/v1/cockpit-profiles/mine')
            ->assertOk()
            ->assertJsonPath('data.tiles', ['products']);
    }
}
