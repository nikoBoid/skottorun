<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Character;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    public function index(Request $request): Response
    {
        $campaign = $this->campaign($request);

        return Inertia::render('Dm/Players', [
            'campaign' => $campaign->only('id', 'name'),
            'players' => $campaign->characters()
                ->with('user:id,name,username,is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campaign = $this->campaign($request);
        $data = $this->validated($request, $campaign->id);

        $character = DB::transaction(function () use ($data, $campaign): Character {
            $player = User::create([
                'name' => $data['name'],
                'username' => mb_strtolower($data['username']),
                'password' => $data['password'],
                'role' => UserRole::Player,
                'is_active' => $data['is_active'],
            ]);

            return $campaign->characters()->create([
                'user_id' => $player->id,
                'name' => $data['character_name'],
                'ancestry' => $data['ancestry'] ?? null,
                'class_name' => $data['class_name'] ?? null,
                'level' => $data['level'],
            ]);
        });

        ActivityLog::record($campaign, $request->user(), 'created', $character, "ha accolto {$character->name} nella compagnia");

        return to_route('dm.players.index')->with('success', 'Player creato.');
    }

    public function update(Request $request, Character $character): RedirectResponse
    {
        $campaign = $this->campaign($request);
        abort_unless($character->campaign_id === $campaign->id, 404);
        $character->load('user');

        $data = $this->validated($request, $campaign->id, $character);
        $wasActive = $character->user->is_active;

        DB::transaction(function () use ($data, $character): void {
            $userData = [
                'name' => $data['name'],
                'username' => mb_strtolower($data['username']),
                'is_active' => $data['is_active'],
            ];

            if (! empty($data['password'])) {
                $userData['password'] = $data['password'];
            }

            $character->user->update($userData);
            $character->update([
                'name' => $data['character_name'],
                'ancestry' => $data['ancestry'] ?? null,
                'class_name' => $data['class_name'] ?? null,
                'level' => $data['level'],
            ]);

            if (! $data['is_active']) {
                DB::table('sessions')->where('user_id', $character->user_id)->delete();
            }
        });

        $action = $wasActive && ! $data['is_active'] ? 'disattivato' : 'aggiornato';
        ActivityLog::record($campaign, $request->user(), 'updated', $character, "ha {$action} il personaggio {$character->name}");

        return back()->with('success', 'Personaggio aggiornato.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, int $campaignId, ?Character $character = null): array
    {
        $request->merge([
            'username' => mb_strtolower((string) $request->input('username')),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:32',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($character?->user_id),
            ],
            'password' => [$character ? 'nullable' : 'required', Password::min(8)],
            'character_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('characters', 'name')
                    ->where('campaign_id', $campaignId)
                    ->ignore($character?->id),
            ],
            'ancestry' => ['nullable', 'string', 'max:80'],
            'class_name' => ['nullable', 'string', 'max:80'],
            'level' => ['required', 'integer', 'min:1', 'max:20'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
