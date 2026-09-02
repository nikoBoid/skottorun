<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $campaign = $this->campaign($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['required', 'integer', 'min:0', 'max:9999'],
            'unit' => ['nullable', 'string', 'max:20'],
        ]);

        $item = $campaign->inventoryItems()->create([
            ...$data,
            'unit' => $data['unit'] ?: 'pz',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        ActivityLog::record($campaign, $request->user(), 'created', $item, "ha aggiunto {$item->name} allo zaino");

        return back()->with('success', 'Oggetto aggiunto allo zaino.');
    }

    public function update(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $campaign = $this->campaign($request);
        abort_unless($inventoryItem->campaign_id === $campaign->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:0', 'max:9999'],
            'unit' => ['sometimes', 'required', 'string', 'max:20'],
        ]);

        $inventoryItem->update([...$data, 'updated_by' => $request->user()->id]);
        ActivityLog::record($campaign, $request->user(), 'updated', $inventoryItem, "ha aggiornato {$inventoryItem->name}");

        return back()->with('success', 'Zaino aggiornato.');
    }

    public function destroy(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $campaign = $this->campaign($request);
        abort_unless($inventoryItem->campaign_id === $campaign->id, 404);

        ActivityLog::record($campaign, $request->user(), 'deleted', $inventoryItem, "ha rimosso {$inventoryItem->name} dallo zaino");
        $inventoryItem->delete();

        return back()->with('success', 'Oggetto rimosso.');
    }
}
