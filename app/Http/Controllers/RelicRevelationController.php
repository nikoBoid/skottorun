<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\RelicRevelation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RelicRevelationController extends Controller
{
    public function update(Request $request, RelicRevelation $revelation): RedirectResponse
    {
        abort_unless($request->user()->isDungeonMaster(), 403);

        $campaign = $this->campaign($request);
        $revelation->load('relic');
        abort_unless($revelation->relic->campaign_id === $campaign->id, 404);

        $data = $request->validate([
            'is_unlocked' => ['required', 'boolean'],
        ]);

        $revelation->update([
            'is_unlocked' => $data['is_unlocked'],
            'unlocked_at' => $data['is_unlocked'] ? now() : null,
            'unlocked_by' => $data['is_unlocked'] ? $request->user()->id : null,
        ]);

        $action = $data['is_unlocked'] ? 'ha sbloccato' : 'ha nuovamente celato';
        ActivityLog::record(
            $campaign,
            $request->user(),
            $data['is_unlocked'] ? 'unlocked' : 'locked',
            $revelation,
            "{$action} {$revelation->title} di {$revelation->relic->name}",
        );

        return back()->with('success', $data['is_unlocked'] ? 'Segreto sbloccato.' : 'Segreto nuovamente celato.');
    }
}
