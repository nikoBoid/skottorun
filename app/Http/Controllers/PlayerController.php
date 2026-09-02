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

class PlayerController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isDungeonMaster(), 403);
        $campaign = $this->campaign($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'character_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('characters', 'name')->where('campaign_id', $campaign->id),
            ],
            'ancestry' => ['nullable', 'string', 'max:80'],
            'class_name' => ['nullable', 'string', 'max:80'],
            'level' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $character = DB::transaction(function () use ($data, $campaign): Character {
            $player = User::create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'password' => $data['password'],
                'role' => UserRole::Player,
                'email_verified_at' => now(),
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

        return to_route('dashboard', ['player' => $character->id])->with('success', 'Player creato.');
    }
}
