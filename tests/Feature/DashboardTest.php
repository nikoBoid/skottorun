<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create(['role' => UserRole::Player]);
        $campaign = Campaign::query()->create([
            'dungeon_master_id' => User::factory()->create(['role' => UserRole::DungeonMaster])->id,
            'name' => 'Test campaign',
            'slug' => 'test-campaign',
        ]);
        $campaign->characters()->create([
            'user_id' => $user->id,
            'name' => 'Arannis',
        ]);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }
}
