<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CharacterSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_receives_their_editable_character_sheet_on_the_dashboard(): void
    {
        [, , $player, $character] = $this->campaignWithTwoPlayers();

        $this->actingAs($player)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('characterSheet.character_id', $character->id)
                ->where('characterSheet.character_name', $character->name)
                ->where('characterSheet.can_edit', true));

        $this->actingAs($player)
            ->get(route('character-sheets.show', $character))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_player_can_save_their_sheet_and_previous_version_is_preserved(): void
    {
        Storage::fake('local');
        [, , $player, $character] = $this->campaignWithTwoPlayers();

        $this->actingAs($player)
            ->post(route('character-sheets.update', $character), [
                'sheet' => $this->fakePdf('prima-versione'),
            ])
            ->assertSessionHasNoErrors();

        $currentPath = "character-sheets/{$character->id}/current.pdf";
        $previousPath = "character-sheets/{$character->id}/previous.pdf";
        Storage::disk('local')->assertExists($currentPath);
        Storage::disk('local')->assertMissing($previousPath);

        $this->actingAs($player)
            ->post(route('character-sheets.update', $character), [
                'sheet' => $this->fakePdf('seconda-versione'),
            ])
            ->assertSessionHasNoErrors();

        Storage::disk('local')->assertExists($previousPath);
        $this->assertStringContainsString('prima-versione', Storage::disk('local')->get($previousPath));
        $this->assertStringContainsString('seconda-versione', Storage::disk('local')->get($currentPath));
        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'character_sheet_path' => $currentPath,
        ]);
    }

    public function test_player_cannot_view_or_update_another_players_sheet(): void
    {
        [, , $firstPlayer, , , $secondCharacter] = $this->campaignWithTwoPlayers();

        $this->actingAs($firstPlayer)
            ->get(route('character-sheets.show', $secondCharacter))
            ->assertForbidden();

        $this->actingAs($firstPlayer)
            ->post(route('character-sheets.update', $secondCharacter), [
                'sheet' => $this->fakePdf('intrusione'),
            ])
            ->assertForbidden();
    }

    public function test_dm_can_view_but_cannot_overwrite_a_players_sheet(): void
    {
        [, $dm, , $character] = $this->campaignWithTwoPlayers();

        $this->actingAs($dm)
            ->get(route('dashboard', ['player' => $character->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('characterSheet.character_id', $character->id)
                ->where('characterSheet.can_edit', false));

        $this->actingAs($dm)
            ->get(route('character-sheets.show', $character))
            ->assertOk();

        $this->actingAs($dm)
            ->post(route('character-sheets.update', $character), [
                'sheet' => $this->fakePdf('modifica-dm'),
            ])
            ->assertForbidden();
    }

    private function fakePdf(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'scheda.pdf',
            "%PDF-1.7\n{$content}\n%%EOF",
        );
    }

    /**
     * @return array{Campaign, User, User, Character, User, Character}
     */
    private function campaignWithTwoPlayers(): array
    {
        $dm = User::factory()->create(['role' => UserRole::DungeonMaster]);
        $campaign = Campaign::query()->create([
            'dungeon_master_id' => $dm->id,
            'name' => 'Le Cronache',
            'slug' => 'le-cronache',
        ]);

        $firstPlayer = User::factory()->create(['role' => UserRole::Player]);
        $firstCharacter = $campaign->characters()->create([
            'user_id' => $firstPlayer->id,
            'name' => 'Arannis',
            'level' => 3,
        ]);

        $secondPlayer = User::factory()->create(['role' => UserRole::Player]);
        $secondCharacter = $campaign->characters()->create([
            'user_id' => $secondPlayer->id,
            'name' => 'Mira',
            'level' => 3,
        ]);

        return [$campaign, $dm, $firstPlayer, $firstCharacter, $secondPlayer, $secondCharacter];
    }
}
