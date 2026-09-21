<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\GalleryImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedCampaignToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_campaign_member_can_upload_and_see_gallery_images(): void
    {
        Storage::fake('public');
        [$campaign, , $firstPlayer, $secondPlayer] = $this->campaignWithTwoPlayers();

        $this->actingAs($firstPlayer)
            ->post(route('gallery.store'), [
                'image' => UploadedFile::fake()->image('rovine.png', 800, 600),
            ])
            ->assertSessionHasNoErrors();

        $galleryImage = GalleryImage::query()->firstOrFail();
        $this->assertSame($campaign->id, $galleryImage->campaign_id);
        Storage::disk('public')->assertExists($galleryImage->image_path);

        $this->actingAs($secondPlayer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('galleryImages', 1)
                ->where('galleryImages.0.id', $galleryImage->id)
                ->where('rationCount', 0));
    }

    public function test_campaign_member_can_remove_a_shared_gallery_image(): void
    {
        Storage::fake('public');
        [$campaign, , $firstPlayer, $secondPlayer] = $this->campaignWithTwoPlayers();
        $path = UploadedFile::fake()->image('tempio.jpg')->store("gallery/{$campaign->id}", 'public');
        $galleryImage = $campaign->galleryImages()->create([
            'uploaded_by' => $firstPlayer->id,
            'image_path' => $path,
        ]);

        $this->actingAs($secondPlayer)
            ->delete(route('gallery.destroy', $galleryImage))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('gallery_images', ['id' => $galleryImage->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_member_cannot_remove_another_campaign_gallery_image(): void
    {
        Storage::fake('public');
        [, , $player] = $this->campaignWithTwoPlayers();
        $otherDm = User::factory()->create(['role' => UserRole::DungeonMaster]);
        $otherCampaign = Campaign::query()->create([
            'dungeon_master_id' => $otherDm->id,
            'name' => 'Altra campagna',
            'slug' => 'altra-campagna',
        ]);
        $path = UploadedFile::fake()->image('segreto.jpg')->store("gallery/{$otherCampaign->id}", 'public');
        $foreignImage = $otherCampaign->galleryImages()->create([
            'uploaded_by' => $otherDm->id,
            'image_path' => $path,
        ]);

        $this->actingAs($player)
            ->delete(route('gallery.destroy', $foreignImage))
            ->assertNotFound();

        $this->assertDatabaseHas('gallery_images', ['id' => $foreignImage->id]);
        Storage::disk('public')->assertExists($path);
    }

    public function test_every_campaign_member_can_manage_rations_and_rest_consumes_four(): void
    {
        [$campaign, , $firstPlayer, $secondPlayer] = $this->campaignWithTwoPlayers();

        $this->actingAs($firstPlayer)
            ->patch(route('rations.update'), ['quantity' => 12])
            ->assertSessionHasNoErrors();

        $this->actingAs($secondPlayer)
            ->post(route('rations.rest'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'ration_count' => 8,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'campaign_id' => $campaign->id,
            'description' => 'ha consumato 4 razioni per il riposo della compagnia',
        ]);
    }

    public function test_company_cannot_rest_without_four_rations(): void
    {
        [$campaign, , $player] = $this->campaignWithTwoPlayers();
        $campaign->update(['ration_count' => 3]);

        $this->actingAs($player)
            ->post(route('rations.rest'))
            ->assertSessionHasErrors('rations');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'ration_count' => 3,
        ]);
    }

    /**
     * @return array{Campaign, User, User, User}
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

        return [$campaign, $dm, $firstPlayer, $secondPlayer];
    }
}
