<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessengerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_colleagues_endpoint_is_available_without_users_view_permission(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $inactive = User::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/messenger/colleagues');

        $response->assertOk()->assertJsonStructure(['data' => [['id', 'name']]]);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($other->id));
        $this->assertFalse($ids->contains($inactive->id));
    }

    public function test_teams_endpoint_lists_teams(): void
    {
        Team::create(['name' => 'Marketing']);

        $response = $this->getJson('/api/v1/messenger/teams');

        $response->assertOk()->assertJsonPath('data.0.name', 'Marketing');
    }
}
