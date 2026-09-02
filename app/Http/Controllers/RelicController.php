<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Relic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RelicController extends Controller
{
    public function index(Request $request): Response
    {
        $campaign = $this->campaign($request);

        return Inertia::render('Dm/Relics', [
            'campaign' => $campaign->only('id', 'name'),
            'players' => $campaign->characters()->orderBy('name')->get(['id', 'name']),
            'relics' => $campaign->relics()
                ->with(['character:id,name', 'revelations'])
                ->orderByRaw('character_id IS NOT NULL')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campaign = $this->campaign($request);
        $data = $request->validate([
            'character_id' => [
                'nullable',
                'integer',
                Rule::exists('characters', 'id')->where('campaign_id', $campaign->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'revelations' => ['nullable', 'array', 'max:20'],
            'revelations.*.kind' => ['required', Rule::in(['skill', 'lore'])],
            'revelations.*.title' => ['required', 'string', 'max:120'],
            'revelations.*.content' => ['required', 'string', 'max:3000'],
        ]);

        $imagePath = $request->file('image')?->store('relics', 'public');

        try {
            $relic = DB::transaction(function () use ($campaign, $data, $imagePath, $request): Relic {
                $relic = $campaign->relics()->create([
                    ...Arr::only($data, ['character_id', 'name', 'description']),
                    'image_path' => $imagePath,
                    'created_by' => $request->user()->id,
                ]);

                foreach ($data['revelations'] ?? [] as $order => $revelation) {
                    $relic->revelations()->create([
                        ...$revelation,
                        'sort_order' => $order,
                    ]);
                }

                return $relic;
            });
        } catch (Throwable $exception) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

        $destination = $relic->character_id
            ? ' e l’ha affidata a '.$relic->character()->value('name')
            : ' nell’archivio del DM';
        ActivityLog::record($campaign, $request->user(), 'created', $relic, "ha creato {$relic->name}{$destination}");

        return back()->with('success', $relic->character_id ? 'Reliquia creata e affidata.' : 'Reliquia salvata nell’archivio.');
    }

    public function update(Request $request, Relic $relic): RedirectResponse
    {
        $campaign = $this->campaign($request);
        abort_unless($relic->campaign_id === $campaign->id, 404);

        $data = $request->validate([
            'character_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('characters', 'id')->where('campaign_id', $campaign->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
            'revelations' => ['sometimes', 'array', 'max:20'],
            'revelations.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('relic_revelations', 'id')->where('relic_id', $relic->id),
            ],
            'revelations.*.kind' => ['required_with:revelations', Rule::in(['skill', 'lore'])],
            'revelations.*.title' => ['required_with:revelations', 'string', 'max:120'],
            'revelations.*.content' => ['required_with:revelations', 'string', 'max:3000'],
        ]);

        $oldImagePath = $relic->image_path;
        $newImagePath = null;
        if ($request->hasFile('image')) {
            $newImagePath = $request->file('image')->store('relics', 'public');
            $data['image_path'] = $newImagePath;
        } elseif ($data['remove_image'] ?? false) {
            $data['image_path'] = null;
        }
        $revelations = $data['revelations'] ?? null;
        unset($data['image'], $data['remove_image'], $data['revelations']);

        try {
            DB::transaction(function () use ($relic, $data, $revelations): void {
                $relic->update($data);

                if ($revelations === null) {
                    return;
                }

                $keptIds = collect($revelations)->pluck('id')->filter()->values();
                $relic->revelations()->whereNotIn('id', $keptIds)->delete();

                foreach ($revelations as $order => $revelation) {
                    $attributes = [
                        ...Arr::only($revelation, ['kind', 'title', 'content']),
                        'sort_order' => $order,
                    ];

                    if (! empty($revelation['id'])) {
                        $relic->revelations()->whereKey($revelation['id'])->update($attributes);
                    } else {
                        $relic->revelations()->create($attributes);
                    }
                }
            });
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }

        if (array_key_exists('image_path', $data) && $oldImagePath && $oldImagePath !== $data['image_path']) {
            Storage::disk('public')->delete($oldImagePath);
        }

        if (array_key_exists('character_id', $data)) {
            $destination = $relic->character_id
                ? 'a '.$relic->character()->value('name')
                : 'all’archivio del DM';
            ActivityLog::record($campaign, $request->user(), 'assigned', $relic, "ha assegnato {$relic->name} {$destination}");
        } else {
            ActivityLog::record($campaign, $request->user(), 'updated', $relic, "ha aggiornato la reliquia {$relic->name}");
        }

        return back()->with('success', 'Reliquia aggiornata.');
    }

    public function destroy(Request $request, Relic $relic): RedirectResponse
    {
        $campaign = $this->campaign($request);
        abort_unless($relic->campaign_id === $campaign->id, 404);

        $imagePath = $relic->image_path;
        ActivityLog::record($campaign, $request->user(), 'deleted', $relic, "ha rimosso la reliquia {$relic->name}");
        $relic->delete();

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        return back()->with('success', 'Reliquia rimossa.');
    }
}
