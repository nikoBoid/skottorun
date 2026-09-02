<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class SetDungeonMasterCredentials extends Command
{
    protected $signature = 'scottorun:set-dm-credentials {--username=dm : Username del Dungeon Master}';

    protected $description = 'Aggiorna username e password del Dungeon Master senza esporre la password nella shell';

    public function handle(): int
    {
        $username = mb_strtolower((string) $this->option('username'));

        if (! preg_match('/^[a-z0-9._-]{2,32}$/', $username)) {
            $this->error('Lo username deve avere 2-32 caratteri: lettere minuscole, numeri, punto, trattino o underscore.');

            return self::FAILURE;
        }

        $password = $this->secret('Nuova password del Dungeon Master (minimo 8 caratteri)');

        if (! is_string($password) || mb_strlen($password) < 8) {
            $this->error('La password deve contenere almeno 8 caratteri.');

            return self::FAILURE;
        }

        $dm = User::query()->where('role', UserRole::DungeonMaster)->firstOrFail();
        $dm->update(['username' => $username, 'password' => $password, 'is_active' => true]);

        $this->info("Credenziali aggiornate per {$dm->name}.");

        return self::SUCCESS;
    }
}
