<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RationController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $campaign = $this->campaign($request);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:99999'],
        ]);

        $campaign->update(['ration_count' => $data['quantity']]);
        ActivityLog::record($campaign, $request->user(), 'updated', $campaign, "ha impostato le razioni a {$data['quantity']}");

        return back()->with('success', 'Razioni aggiornate.');
    }

    public function rest(Request $request): RedirectResponse
    {
        $campaign = $this->campaign($request);

        DB::transaction(function () use ($campaign, $request): void {
            $lockedCampaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if ($lockedCampaign->ration_count < 4) {
                throw ValidationException::withMessages([
                    'rations' => 'Servono almeno 4 razioni per far riposare tutta la compagnia.',
                ]);
            }

            $lockedCampaign->decrement('ration_count', 4);
            ActivityLog::record($lockedCampaign, $request->user(), 'updated', $lockedCampaign, 'ha consumato 4 razioni per il riposo della compagnia');
        });

        return back()->with('success', 'Riposo registrato: consumate 4 razioni.');
    }
}
