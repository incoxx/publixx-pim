<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HierarchyTreeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->user = User::factory()->create();
        $role = Role::findOrCreate('Admin', 'sanctum');
        $this->user->assignRole($role);
        $this->actingAs($this->user);
    }

    public function test_tree_returns_root_nodes_created_like_test_generator(): void
    {
        $hierarchy = Hierarchy::create([
            'technical_name' => '__testdata__',
            'name_de' => 'Testdaten-Hierarchie',
            'name_en' => 'Test Data Hierarchy',
            'name_json' => ['de' => 'Testdaten-Hierarchie', 'en' => 'Test Data Hierarchy'],
            'hierarchy_type' => 'master',
            'description' => 'Test hierarchy',
        ]);

        // Create root node exactly like TestDataGeneratorService does
        $rootNode = HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'parent_node_id' => null,
            'name_de' => 'Elektronik',
            'name_en' => 'Elektronik',
            'name_json' => ['de' => 'Elektronik', 'en' => 'Elektronik'],
            'path' => '/',
            'depth' => 0,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $rootNode->update(['path' => '/' . $rootNode->id . '/']);

        // Verify the root node has parent_node_id = NULL
        $this->assertNull($rootNode->fresh()->parent_node_id, 'Root node parent_node_id should be NULL');

        // Verify whereNull query finds the root node
        $found = HierarchyNode::where('hierarchy_id', $hierarchy->id)
            ->whereNull('parent_node_id')
            ->get();
        $this->assertCount(1, $found, 'whereNull(parent_node_id) should find the root node');

        // Create a child node
        $childNode = HierarchyNode::create([
            'hierarchy_id' => $hierarchy->id,
            'parent_node_id' => $rootNode->id,
            'name_de' => 'Serie A',
            'name_en' => 'Serie A',
            'name_json' => ['de' => 'Serie A', 'en' => 'Serie A'],
            'path' => '/',
            'depth' => 1,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $childNode->update(['path' => $rootNode->path . $childNode->id . '/']);

        // Verify child node has non-null parent_node_id
        $this->assertNotNull($childNode->fresh()->parent_node_id, 'Child node should have parent_node_id');

        // Test the tree endpoint
        $response = $this->getJson("/api/v1/hierarchies/{$hierarchy->id}/tree");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Tree endpoint should return root nodes');
        $this->assertCount(1, $data, 'Should have exactly 1 root node');
        $this->assertEquals('Elektronik', $data[0]['name_de']);
        $this->assertCount(1, $data[0]['children'], 'Root node should have 1 child');
        $this->assertEquals('Serie A', $data[0]['children'][0]['name_de']);
    }

    public function test_tree_with_factory_created_nodes(): void
    {
        $hierarchy = Hierarchy::factory()->create();

        // Create root node using factory (doesn't set parent_node_id, so it defaults to null)
        $rootNode = HierarchyNode::factory()->create([
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Root Node',
        ]);
        $rootNode->update(['path' => '/' . $rootNode->id . '/']);

        $response = $this->getJson("/api/v1/hierarchies/{$hierarchy->id}/tree");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Tree should have root nodes from factory');
    }
}
