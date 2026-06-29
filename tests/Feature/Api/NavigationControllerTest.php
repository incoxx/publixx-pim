<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Navigation;
use App\Models\NavigationNode;
use App\Models\Role;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->mock(LicenseService::class, function ($mock) {
            $mock->shouldReceive('isModuleActive')->andReturn(true);
        });

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::findOrCreate('Admin', 'sanctum'));
        $this->actingAs($this->user);
    }

    private function navigation(): Navigation
    {
        return Navigation::create([
            'technical_name' => 'main',
            'name_de' => 'Hauptnavigation',
            'is_primary' => true,
        ]);
    }

    public function test_store_legt_navigation_an(): void
    {
        $this->postJson('/api/v1/navigations', [
            'technical_name' => 'footer',
            'name_de' => 'Footer',
        ])->assertCreated()->assertJsonPath('data.technical_name', 'footer');
    }

    public function test_knoten_anlegen_setzt_pfad_und_tiefe(): void
    {
        $nav = $this->navigation();

        $root = $this->postJson("/api/v1/navigations/{$nav->id}/nodes", [
            'label_de' => 'Produkte',
            'target_type' => 'folder',
        ])->assertCreated()->json('data');

        $this->assertSame(0, $root['depth']);

        $child = $this->postJson("/api/v1/navigations/{$nav->id}/nodes", [
            'label_de' => 'Werkzeuge',
            'target_type' => 'folder',
            'parent_node_id' => $root['id'],
        ])->assertCreated()->json('data');

        $this->assertSame(1, $child['depth']);
        $this->assertStringContainsString($root['id'], $child['path']);
    }

    public function test_externer_link_erfordert_url(): void
    {
        $nav = $this->navigation();

        $this->postJson("/api/v1/navigations/{$nav->id}/nodes", [
            'label_de' => 'Shop',
            'target_type' => 'external_url',
        ])->assertStatus(422)->assertJsonValidationErrors(['external_url']);
    }

    public function test_move_haengt_knoten_um(): void
    {
        $nav = $this->navigation();
        $a = NavigationNode::create(['navigation_id' => $nav->id, 'label_de' => 'A', 'target_type' => 'folder', 'path' => '/', 'sort_order' => 0]);
        $a->refreshTreePath();
        $b = NavigationNode::create(['navigation_id' => $nav->id, 'label_de' => 'B', 'target_type' => 'folder', 'path' => '/', 'sort_order' => 1]);
        $b->refreshTreePath();

        $this->putJson("/api/v1/navigation-nodes/{$a->id}/move", [
            'parent_node_id' => $b->id,
            'sort_order' => 0,
        ])->assertOk();

        $a->refresh();
        $this->assertSame($b->id, $a->parent_node_id);
        $this->assertSame(1, $a->depth);
        $this->assertStringContainsString($b->id, $a->path);
    }

    public function test_tree_endpoint_liefert_verschachtelten_baum(): void
    {
        $nav = $this->navigation();
        $root = NavigationNode::create(['navigation_id' => $nav->id, 'label_de' => 'Root', 'target_type' => 'folder', 'path' => '/', 'sort_order' => 0]);
        $root->refreshTreePath();
        $child = NavigationNode::create(['navigation_id' => $nav->id, 'parent_node_id' => $root->id, 'label_de' => 'Kind', 'target_type' => 'folder', 'path' => '/', 'sort_order' => 0]);
        $child->refreshTreePath();

        $this->getJson("/api/v1/navigations/{$nav->id}/tree")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.children')
            ->assertJsonPath('data.0.children.0.label_de', 'Kind');
    }

    public function test_delete_navigation_kaskadiert_knoten(): void
    {
        $nav = $this->navigation();
        $node = NavigationNode::create(['navigation_id' => $nav->id, 'label_de' => 'X', 'target_type' => 'folder', 'path' => '/', 'sort_order' => 0]);
        $node->refreshTreePath();

        $this->deleteJson("/api/v1/navigations/{$nav->id}")->assertNoContent();

        $this->assertDatabaseMissing('navigation_nodes', ['id' => $node->id]);
    }
}
