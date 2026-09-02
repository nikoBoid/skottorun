<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BootstrapCampaign extends Command
{
    protected $signature = 'scottorun:bootstrap
                            {--username= : Username del Dungeon Master}
                            {--name= : Nome del Dungeon Master}
                            {--campaign= : Nome della campagna}';

    protected $description = 'Crea il primo Dungeon Master e la campagna iniziale';

    public function handle(): int
    {
        if (Campaign::query()->exists()) {
            $this->error('Esiste già una campagna. Nessuna modifica eseguita.');

            return self::FAILURE;
        }

        $username = mb_strtolower((string) ($this->option('username') ?: $this->ask('Username del Dungeon Master', 'dm')));
        $name = $this->option('name') ?: $this->ask('Nome del Dungeon Master', 'Dungeon Master');
        $campaignName = $this->option('campaign') ?: $this->ask('Nome della campagna', 'La mia campagna');
        $password = $this->secret('Password iniziale del Dungeon Master (minimo 8 caratteri)');

        if (! preg_match('/^[a-z0-9._-]{2,32}$/', $username)) {
            $this->error('Lo username non è valido.');

            return self::FAILURE;
        }

        if (! is_string($password) || mb_strlen($password) < 8) {
            $this->error('La password deve contenere almeno 8 caratteri.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($username, $name, $campaignName, $password): void {
            $dm = User::query()->create([
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'role' => UserRole::DungeonMaster,
            ]);

            Campaign::query()->create([
                'dungeon_master_id' => $dm->id,
                'name' => $campaignName,
                'slug' => Str::slug($campaignName).'-'.Str::lower(Str::random(5)),
            ]);
        });

        $this->info('Campagna creata. Puoi accedere con l’account del Dungeon Master.');

        return self::SUCCESS;
    }
}
