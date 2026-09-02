<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function campaign(Request $request): Campaign
    {
        $user = $request->user();

        if ($user->isDungeonMaster()) {
            return $user->managedCampaigns()->firstOrFail();
        }

        return $user->character()->with('campaign')->firstOrFail()->campaign;
    }
}
