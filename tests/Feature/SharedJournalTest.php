<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\JournalTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedJournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_players_share_topics_and_all_their_pages(): void
    {
        [$campaign, $firstPlayer, $secondPlayer] = $this->campaignWithTwoPlayers();

        $this->actingAs($firstPlayer)
            ->post(route('journal-topics.store'), ['title' => 'Celestiali'])
            ->assertSessionHasNoErrors();

        $topic = $campaign->journalTopics()->where('title', 'Celestiali')->firstOrFail();

        $this->actingAs($firstPlayer)
            ->post(route('journal-pages.store', $topic), [
                'title' => 'Gerarchie del cielo',
                'content' => 'Gli arconti custodiscono le soglie superiori.',
                'occurred_on' => '2026-08-20',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($secondPlayer)
            ->post(route('journal-pages.store', $topic), [
                'title' => 'Segni e simboli',
                'content' => 'Le sette stelle ricorrono in ogni tempio.',
                'occurred_on' => '2026-08-21',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($secondPlayer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('journalTopics.0.title', 'Celestiali')
                ->has('journalTopics.0.pages', 2)
                ->where('journalTopics.0.pages.0.author.id', $firstPlayer->id)
                ->where('journalTopics.0.pages.1.author.id', $secondPlayer->id));
    }

    public function test_a_topic_with_one_page_is_returned_with_that_page_ready_to_display(): void
    {
        [$campaign, $firstPlayer, $secondPlayer] = $this->campaignWithTwoPlayers();
        $topic = $campaign->journalTopics()->create([
            'created_by' => $firstPlayer->id,
            'title' => 'Draghi metallici',
        ]);
        $topic->pages()->create([
            'author_id' => $firstPlayer->id,
            'title' => 'Il drago d’argento',
            'content' => 'Assume spesso sembianze mortali.',
        ]);

        $this->actingAs($secondPlayer)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('journalTopics.0.pages_count', 1)
                ->has('journalTopics.0.pages', 1)
                ->where('journalTopics.0.pages.0.title', 'Il drago d’argento'));
    }

    public function test_every_campaign_member_can_rename_a_topic_created_by_someone_else(): void
    {
        [$campaign, $firstPlayer, $secondPlayer] = $this->campaignWithTwoPlayers();
        $topic = $campaign->journalTopics()->create([
            'created_by' => $firstPlayer->id,
            'title' => 'Celestiali',
        ]);

        $this->actingAs($secondPlayer)
            ->patch(route('journal-topics.update', $topic), [
                'title' => 'Gerarchie celestiali',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('journal_topics', [
            'id' => $topic->id,
            'title' => 'Gerarchie celestiali',
        ]);

        $this->actingAs($campaign->dungeonMaster)
            ->patch(route('journal-topics.update', $topic), [
                'title' => 'Celestiali e arconti',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('journal_topics', [
            'id' => $topic->id,
            'title' => 'Celestiali e arconti',
        ]);
    }

    public function test_member_cannot_rename_another_campaign_topic(): void
    {
        [, $player] = $this->campaignWithTwoPlayers();
        $otherDm = User::factory()->create(['role' => UserRole::DungeonMaster]);
        $otherCampaign = Campaign::query()->create([
            'dungeon_master_id' => $otherDm->id,
            'name' => 'Altra campagna',
            'slug' => 'altra-campagna',
        ]);
        $foreignTopic = $otherCampaign->journalTopics()->create([
            'created_by' => $otherDm->id,
            'title' => 'Segreti proibiti',
        ]);

        $this->actingAs($player)
            ->patch(route('journal-topics.update', $foreignTopic), [
                'title' => 'Titolo sottratto',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('journal_topics', ['title' => 'Titolo sottratto']);
    }

    public function test_player_cannot_add_pages_to_another_campaign_topic(): void
    {
        [, $player] = $this->campaignWithTwoPlayers();
        $otherDm = User::factory()->create(['role' => UserRole::DungeonMaster]);
        $otherCampaign = Campaign::query()->create([
            'dungeon_master_id' => $otherDm->id,
            'name' => 'Altra campagna',
            'slug' => 'altra-campagna',
        ]);
        $foreignTopic = JournalTopic::query()->create([
            'campaign_id' => $otherCampaign->id,
            'created_by' => $otherDm->id,
            'title' => 'Segreti proibiti',
        ]);

        $this->actingAs($player)
            ->post(route('journal-pages.store', $foreignTopic), [
                'title' => 'Intrusione',
                'content' => 'Questo testo non deve essere salvato.',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('journal_pages', ['title' => 'Intrusione']);
    }

    /**
     * @return array{Campaign, User, User}
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
        $campaign->characters()->create([
            'user_id' => $firstPlayer->id,
            'name' => 'Arannis',
            'level' => 3,
        ]);

        $secondPlayer = User::factory()->create(['role' => UserRole::Player]);
        $campaign->characters()->create([
            'user_id' => $secondPlayer->id,
            'name' => 'Mira',
            'level' => 3,
        ]);

        return [$campaign, $firstPlayer, $secondPlayer];
    }
}
