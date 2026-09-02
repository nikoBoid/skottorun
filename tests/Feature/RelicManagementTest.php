<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Relic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RelicManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_dm_can_prepare_a_relic_in_the_archive(): void
    {
        Storage::fake('public');
        [, $dm, $player] = $this->campaignWithPlayer();

        $this->actingAs($player)
            ->post(route('dm.relics.store'), [
                'name' => 'Occhio di onice',
                'description' => 'Una sfera fredda e perfettamente liscia.',
            ])
            ->assertForbidden();

        $this->actingAs($dm)
            ->post(route('dm.relics.store'), [
                'character_id' => null,
                'name' => 'Occhio di onice',
                'description' => 'Una sfera fredda e perfettamente liscia.',
                'image' => UploadedFile::fake()->image('occhio.png', 600, 400),
                'revelations' => [
                    [
                        'kind' => 'skill',
                        'title' => 'Vista oltre il velo',
                        'content' => 'Permette di vedere le creature invisibili.',
                    ],
                    [
                        'kind' => 'lore',
                        'title' => 'Il primo custode',
                        'content' => 'Apparteneva al guardiano della soglia.',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('relics', [
            'name' => 'Occhio di onice',
            'character_id' => null,
        ]);
        $this->assertDatabaseCount('relic_revelations', 2);
        $this->assertDatabaseHas('relic_revelations', [
            'title' => 'Vista oltre il velo',
            'is_unlocked' => false,
        ]);
        Storage::disk('public')->assertExists('relics/'.$this->relicImageFilename());
    }

    public function test_archived_relic_can_be_delivered_and_is_then_visible_to_the_player(): void
    {
        [$campaign, $dm, $player, $character] = $this->campaignWithPlayer();
        $relic = $campaign->relics()->create([
            'name' => 'Chiave delle maree',
            'description' => 'Una chiave ricoperta di sale.',
            'created_by' => $dm->id,
        ]);

        $this->actingAs($player)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('relics', 0));

        $this->actingAs($dm)
            ->patch(route('dm.relics.update', $relic), ['character_id' => $character->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($player)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('relics', 1)
                ->where('relics.0.id', $relic->id)
                ->where('relics.0.character_id', $character->id));
    }

    public function test_locked_content_is_not_sent_to_the_player_until_the_dm_unlocks_it(): void
    {
        [$campaign, $dm, $player, $character] = $this->campaignWithPlayer();
        $relic = $campaign->relics()->create([
            'character_id' => $character->id,
            'name' => 'Lanterna muta',
            'created_by' => $dm->id,
        ]);
        $revelation = $relic->revelations()->create([
            'kind' => 'skill',
            'title' => 'Luce dei trapassati',
            'content' => 'Rivela le impronte lasciate dagli spiriti.',
            'sort_order' => 0,
        ]);

        $this->actingAs($player)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('relics.0.revelations.0.is_unlocked', false)
                ->where('relics.0.revelations.0.content', null));

        $this->actingAs($player)
            ->patch(route('dm.relic-revelations.update', $revelation), ['is_unlocked' => true])
            ->assertForbidden();

        $this->actingAs($dm)
            ->patch(route('dm.relic-revelations.update', $revelation), ['is_unlocked' => true])
            ->assertSessionHasNoErrors();

        $this->actingAs($player)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('relics.0.revelations.0.is_unlocked', true)
                ->where('relics.0.revelations.0.content', 'Rivela le impronte lasciate dagli spiriti.'));
    }

    public function test_dm_can_edit_relic_and_sync_revelations_without_resetting_unlocks(): void
    {
        [$campaign, $dm, , $character] = $this->campaignWithPlayer();
        $relic = $campaign->relics()->create([
            'character_id' => $character->id,
            'name' => 'Vecchio nome',
            'created_by' => $dm->id,
        ]);
        $kept = $relic->revelations()->create([
            'kind' => 'skill',
            'title' => 'Segreto noto',
            'content' => 'Contenuto',
            'is_unlocked' => true,
            'sort_order' => 0,
        ]);
        $removed = $relic->revelations()->create([
            'kind' => 'lore',
            'title' => 'Da rimuovere',
            'content' => 'Contenuto',
            'sort_order' => 1,
        ]);

        $this->actingAs($dm)
            ->patch(route('dm.relics.update', $relic), [
                'name' => 'Nuovo nome',
                'description' => 'Nuova descrizione',
                'revelations' => [
                    ['id' => $kept->id, 'kind' => 'skill', 'title' => 'Segreto aggiornato', 'content' => 'Nuovo contenuto'],
                    ['kind' => 'lore', 'title' => 'Nuovo segreto', 'content' => 'Ancora sigillato'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('relic_revelations', ['id' => $kept->id, 'is_unlocked' => true, 'title' => 'Segreto aggiornato']);
        $this->assertDatabaseMissing('relic_revelations', ['id' => $removed->id]);
        $this->assertDatabaseHas('relic_revelations', ['relic_id' => $relic->id, 'title' => 'Nuovo segreto', 'is_unlocked' => false]);
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

    private function relicImageFilename(): string
    {
        return basename((string) Relic::query()->value('image_path'));
    }
}
