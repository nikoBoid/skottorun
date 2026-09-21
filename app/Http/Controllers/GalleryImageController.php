<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\GalleryImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryImageController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $campaign = $this->campaign($request);
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ]);

        $imagePath = $request->file('image')->store("gallery/{$campaign->id}", 'public');
        $galleryImage = $campaign->galleryImages()->create([
            'uploaded_by' => $request->user()->id,
            'image_path' => $imagePath,
        ]);

        ActivityLog::record($campaign, $request->user(), 'created', $galleryImage, 'ha aggiunto un’immagine alla galleria');

        return back()->with('success', 'Immagine aggiunta alla galleria.');
    }

    public function destroy(Request $request, GalleryImage $galleryImage): RedirectResponse
    {
        $campaign = $this->campaign($request);
        abort_unless($galleryImage->campaign_id === $campaign->id, 404);

        $imagePath = $galleryImage->image_path;
        ActivityLog::record($campaign, $request->user(), 'deleted', $galleryImage, 'ha rimosso un’immagine dalla galleria');
        $galleryImage->delete();
        Storage::disk('public')->delete($imagePath);

        return back()->with('success', 'Immagine rimossa dalla galleria.');
    }
}
