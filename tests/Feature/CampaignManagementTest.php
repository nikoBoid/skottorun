<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CampaignManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_sees_the_shared_inventory_and_only_their_character_sheet(): void
    {
        [$campaign, , $player, $character] = $this->campaignWithPlayer();
        $campaign->inventoryItems()->create([
            'name' => 'Pozione di cura',
            'quantity' => 2,
            'unit' => 'pz',
        ]);

        $this->actingAs($player)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('viewer.role', 'player')
                ->where('selectedPlayer.id', $character->id)
                ->has('inventory', 1)
                ->has('players', 0));
    }

    public function test_dungeon_master_can_create_a_player_and_character(): void
    {
        [$campaign, $dm] = $this->campaignWithPlayer();

        $this->actingAs($dm)
            ->post(route('dm.players.store'), [
                'name' => 'Luca',
                'username' => 'luca',
                'password' => 'very-secret',
                'character_name' => 'Dorian',
                'ancestry' => 'Umano',
                'class_name' => 'Bardo',
                'level' => 4,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'luca', 'role' => 'player']);
        $this->assertDatabaseHas('characters', ['campaign_id' => $campaign->id, 'name' => 'Dorian']);
    }

    public function test_dm_can_update_credentials_and_deactivate_a_player(): void
    {
        [, $dm, $player, $character] = $this->campaignWithPlayer();

        $this->actingAs($dm)
            ->patch(route('dm.players.update', $character), [
                'name' => 'Nuovo nome',
                'username' => 'nuovo-login',
                'password' => 'new-secret',
                'character_name' => 'Arannis II',
                'ancestry' => 'Elfo',
                'class_name' => 'Esploratore',
                'level' => 5,
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $player->id,
            'username' => 'nuovo-login',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('characters', ['id' => $character->id, 'name' => 'Arannis II']);
    }

    public function test_player_cannot_open_or_update_dm_player_management(): void
    {
        [, , $player, $character] = $this->campaignWithPlayer();

        $this->actingAs($player)->get(route('dm.players.index'))->assertForbidden();
        $this->actingAs($player)->patch(route('dm.players.update', $character), [])->assertForbidden();
    }

    public function test_player_can_update_shared_inventory(): void
    {
        [$campaign, , $player] = $this->campaignWithPlayer();
        $item = $campaign->inventoryItems()->create([
            'name' => 'Torcia',
            'quantity' => 2,
            'unit' => 'pz',
        ]);

        $this->actingAs($player)
            ->patch(route('inventory.update', $item), ['quantity' => 3])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'quantity' => 3]);
        $this->assertDatabaseHas('activity_logs', ['subject_id' => $item->id, 'action' => 'updated']);
    }

    public function test_all_players_in_the_campaign_share_the_same_inventory(): void
    {
        [$campaign, , $firstPlayer] = $this->campaignWithPlayer();
        $secondPlayer = User::factory()->create(['role' => UserRole::Player]);
        $campaign->characters()->create([
            'user_id' => $secondPlayer->id,
            'name' => 'Mira',
            'class_name' => 'Chierica',
            'level' => 3,
        ]);
        $item = $campaign->inventoryItems()->create([
            'name' => 'Razioni da viaggio',
            'quantity' => 5,
            'unit' => 'pz',
        ]);

        $this->actingAs($firstPlayer)
            ->patch(route('inventory.update', $item), ['quantity' => 4])
            ->assertSessionHasNoErrors();

        $this->actingAs($secondPlayer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inventory.0.id', $item->id)
                ->where('inventory.0.quantity', 4));
    }

    public function test_user_cannot_change_another_campaign_inventory(): void
    {
        [, , $player] = $this->campaignWithPlayer();
        $otherDm = User::factory()->create(['role' => UserRole::DungeonMaster]);
        $otherCampaign = Campaign::query()->create([
            'dungeon_master_id' => $otherDm->id,
            'name' => 'Other campaign',
            'slug' => 'other-campaign',
        ]);
        $foreignItem = InventoryItem::query()->create([
            'campaign_id' => $otherCampaign->id,
            'name' => 'Forbidden item',
            'quantity' => 1,
            'unit' => 'pz',
        ]);

        $this->actingAs($player)
            ->patch(route('inventory.update', $foreignItem), ['quantity' => 99])
            ->assertNotFound();

        $this->assertDatabaseHas('inventory_items', ['id' => $foreignItem->id, 'quantity' => 1]);
    }

    /**
     * @return array{Campaign, User, User, Character}
     */
    private function campaignWithPlayer(): array
    {
        $dm = User::factory()->create(['role' => UserRole::DungeonMaster]);
        $campaign = Campaign::query()->create([
            'dungeon_master_id' => $dm->id,
            'name' => 'Le Cronache',
            'slug' => 'le-cronache',
        ]);
        $player = User::factory()->create(['role' => UserRole::Player]);
        $character = $campaign->characters()->create([
            'user_id' => $player->id,
            'name' => 'Arannis',
            'class_name' => 'Ranger',
            'level' => 3,
        ]);

        return [$campaign, $dm, $player, $character];
    }
}
