<?php

namespace App\Http\Controllers;

use App\Models\Character;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $campaign = $this->campaign($request);
        $characters = $campaign->characters()
            ->with('user:id,name,username')
            ->orderBy('name')
            ->get();

        $selectedCharacter = $user->isDungeonMaster()
            ? $this->selectedCharacter($request, $characters)
            : $characters->firstWhere('user_id', $user->id);

        $inventory = $campaign->inventoryItems()
            ->with('updatedBy:id,name')
            ->orderBy('name')
            ->get();

        $relics = $selectedCharacter
            ? $selectedCharacter->relics()
                ->with(['character:id,name', 'revelations'])
                ->latest()
                ->get()
            : collect();

        if (! $user->isDungeonMaster()) {
            $relics->each(function ($relic): void {
                $relic->revelations->each(function ($revelation): void {
                    if (! $revelation->is_unlocked) {
                        $revelation->content = null;
                    }
                });
            });
        }

        $journalTopics = $campaign->journalTopics()
            ->with([
                'creator:id,name',
                'pages' => fn ($query) => $query->with('author:id,name'),
            ])
            ->withCount('pages')
            ->orderBy('title')
            ->get();

        $galleryImages = $campaign->galleryImages()
            ->latest()
            ->get();

        $activity = $campaign->activityLogs()
            ->with('user:id,name')
            ->latest()
            ->limit(8)
            ->get();

        return Inertia::render('Dashboard', [
            'campaign' => $campaign->only('id', 'name', 'slug', 'synopsis'),
            'viewer' => ['role' => $user->role->value],
            'players' => $user->isDungeonMaster() ? $characters : [],
            'selectedPlayer' => $selectedCharacter,
            'inventory' => $inventory,
            'relics' => $relics,
            'journalTopics' => $journalTopics,
            'galleryImages' => $galleryImages,
            'rationCount' => $campaign->ration_count,
            'characterSheet' => $selectedCharacter ? [
                'character_id' => $selectedCharacter->id,
                'character_name' => $selectedCharacter->name,
                'url' => route('character-sheets.show', $selectedCharacter).'?v='.($selectedCharacter->character_sheet_updated_at?->timestamp ?? 0),
                'download_url' => route('character-sheets.download', $selectedCharacter),
                'save_url' => route('character-sheets.update', $selectedCharacter),
                'can_edit' => $selectedCharacter->user_id === $user->id,
                'updated_at' => $selectedCharacter->character_sheet_updated_at,
            ] : null,
            'activity' => $activity,
        ]);
    }

    private function selectedCharacter(Request $request, $characters): ?Character
    {
        $selectedId = $request->integer('player');

        if ($selectedId) {
            return $characters->firstWhere('id', $selectedId) ?? abort(404);
        }

        return $characters->first();
    }
}
