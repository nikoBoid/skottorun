<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = env('SCOTTORUN_SEED_PASSWORD', 'password');

        $dm = User::query()->updateOrCreate(
            ['email' => 'dm@scottorun.local'],
            [
                'name' => 'Dungeon Master',
                'password' => $password,
                'role' => UserRole::DungeonMaster,
                'email_verified_at' => now(),
            ],
        );

        $campaign = Campaign::query()->updateOrCreate(
            ['slug' => 'cronache-del-velo'],
            [
                'dungeon_master_id' => $dm->id,
                'name' => 'Le Cronache del Velo',
                'synopsis' => 'Una compagnia improbabile, antiche reliquie e una nebbia che ricorda ogni nome.',
            ],
        );

        $players = [
            ['name' => 'Nicolò', 'email' => 'nico@scottorun.local', 'character' => 'Theron', 'ancestry' => 'Elfo', 'class' => 'Ranger'],
            ['name' => 'Giulia', 'email' => 'giulia@scottorun.local', 'character' => 'Mira', 'ancestry' => 'Umana', 'class' => 'Chierica'],
            ['name' => 'Marco', 'email' => 'marco@scottorun.local', 'character' => 'Brann', 'ancestry' => 'Nano', 'class' => 'Guerriero'],
        ];

        foreach ($players as $index => $playerData) {
            $player = User::query()->updateOrCreate(
                ['email' => $playerData['email']],
                [
                    'name' => $playerData['name'],
                    'password' => $password,
                    'role' => UserRole::Player,
                    'email_verified_at' => now(),
                ],
            );

            $character = $campaign->characters()->updateOrCreate(
                ['user_id' => $player->id],
                [
                    'name' => $playerData['character'],
                    'ancestry' => $playerData['ancestry'],
                    'class_name' => $playerData['class'],
                    'level' => 3 + $index,
                ],
            );

            if ($index === 0) {
                $relic = $campaign->relics()->updateOrCreate(
                    ['character_id' => $character->id, 'name' => 'Bussola di Aster'],
                    [
                        'created_by' => $dm->id,
                        'description' => 'L’ago non indica il nord, ma ciò che il portatore desidera davvero trovare.',
                    ],
                );

                $relic->revelations()->updateOrCreate(
                    ['title' => 'Richiamo della luna nuova'],
                    [
                        'kind' => 'skill',
                        'content' => 'Una volta per riposo lungo, la bussola può indicare il percorso verso una persona conosciuta.',
                        'is_unlocked' => false,
                        'sort_order' => 0,
                    ],
                );

                $topic = $campaign->journalTopics()->updateOrCreate(
                    ['title' => 'Il corvo dagli occhi d’argento'],
                    [
                        'created_by' => $player->id,
                    ],
                );

                $topic->pages()->updateOrCreate(
                    ['title' => 'La locanda senza insegna'],
                    [
                        'author_id' => $player->id,
                        'content' => 'Abbiamo incontrato il corvo dagli occhi d’argento. Conosceva i nostri nomi prima ancora che entrassimo.',
                        'occurred_on' => now()->subDays(3)->toDateString(),
                        'sort_order' => 0,
                    ],
                );
            }
        }

        foreach ([
            ['Torcia', 6, 'Illumina per circa un’ora.'],
            ['Pozione di cura', 3, 'Recupera 2d4 + 2 punti ferita.'],
            ['Corda di canapa', 1, 'Quindici metri, sorprendentemente robusta.'],
        ] as [$name, $quantity, $description]) {
            $campaign->inventoryItems()->updateOrCreate(
                ['name' => $name],
                [
                    'created_by' => $dm->id,
                    'updated_by' => $dm->id,
                    'quantity' => $quantity,
                    'description' => $description,
                    'unit' => 'pz',
                ],
            );
        }
    }
}
