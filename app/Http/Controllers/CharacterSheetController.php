<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Character;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CharacterSheetController extends Controller
{
    public function show(Request $request, Character $character): BinaryFileResponse
    {
        $this->authorizeView($request, $character);

        return response()->file($this->sheetPath($character), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(Request $request, Character $character): BinaryFileResponse
    {
        $this->authorizeView($request, $character);

        return response()->download(
            $this->sheetPath($character),
            'scheda-'.str($character->name)->slug().'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function update(Request $request, Character $character): RedirectResponse
    {
        $campaign = $this->campaign($request);
        abort_unless($character->campaign_id === $campaign->id, 404);
        abort_unless($character->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'sheet' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $contents = $data['sheet']->get();
        abort_unless(str_starts_with($contents, '%PDF-'), 422, 'Il file salvato non è un PDF valido.');

        $directory = "character-sheets/{$character->id}";
        $currentPath = "{$directory}/current.pdf";
        $previousPath = "{$directory}/previous.pdf";

        if (Storage::disk('local')->exists($currentPath)) {
            Storage::disk('local')->copy($currentPath, $previousPath);
        }

        Storage::disk('local')->put($currentPath, $contents);
        $character->update([
            'character_sheet_path' => $currentPath,
            'character_sheet_updated_at' => now(),
        ]);

        ActivityLog::record($campaign, $request->user(), 'updated', $character, "ha aggiornato la scheda di {$character->name}");

        return back()->with('success', 'Scheda del personaggio salvata.');
    }

    private function authorizeView(Request $request, Character $character): void
    {
        $campaign = $this->campaign($request);

        abort_unless($character->campaign_id === $campaign->id, 404);
        abort_unless($request->user()->isDungeonMaster() || $character->user_id === $request->user()->id, 403);
    }

    private function sheetPath(Character $character): string
    {
        if ($character->character_sheet_path && Storage::disk('local')->exists($character->character_sheet_path)) {
            return Storage::disk('local')->path($character->character_sheet_path);
        }

        return resource_path('pdfs/scheda-personaggio-5e.pdf');
    }
}
