<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ExportProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportProfileScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_to_returns_own_profiles(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        ExportProfile::factory()->create(['user_id' => $user->id, 'is_shared' => false]);
        ExportProfile::factory()->create(['user_id' => $otherUser->id, 'is_shared' => false]);

        $visible = ExportProfile::visibleTo($user->id)->get();

        $this->assertCount(1, $visible);
        $this->assertEquals($user->id, $visible->first()->user_id);
    }

    public function test_visible_to_includes_shared_profiles(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        ExportProfile::factory()->create(['user_id' => $user->id, 'is_shared' => false]);
        ExportProfile::factory()->shared()->create(['user_id' => $otherUser->id]);

        $visible = ExportProfile::visibleTo($user->id)->get();

        $this->assertCount(2, $visible);
    }

    public function test_visible_to_excludes_others_private_profiles(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        ExportProfile::factory()->create(['user_id' => $otherUser->id, 'is_shared' => false]);

        $visible = ExportProfile::visibleTo($user->id)->get();

        $this->assertCount(0, $visible);
    }
}
