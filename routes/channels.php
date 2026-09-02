<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('campaign.{campaignId}', function (User $user, int $campaignId) {
    if ($user->isDungeonMaster()) {
        return $user->managedCampaigns()->whereKey($campaignId)->exists();
    }

    return $user->character()->where('campaign_id', $campaignId)->exists();
});
