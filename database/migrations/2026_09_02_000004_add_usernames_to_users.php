<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->nullable()->after('name');
        });

        $used = [];

        DB::table('users')->orderBy('id')->get()->each(function (object $user) use (&$used): void {
            $base = $user->role === 'dm'
                ? 'dm'
                : Str::before((string) $user->email, '@');
            $base = Str::of($base)
                ->lower()
                ->ascii()
                ->replaceMatches('/[^a-z0-9._-]+/', '-')
                ->trim('-._')
                ->substr(0, 32)
                ->value();

            if ($user->role !== 'dm' && mb_strlen($base) < 3) {
                $base = 'player-'.$user->id;
            }

            $username = $base;
            $suffix = 2;

            while (in_array($username, $used, true)) {
                $ending = '-'.$suffix++;
                $username = mb_substr($base, 0, 32 - mb_strlen($ending)).$ending;
            }

            $used[] = $username;
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->nullable(false)->change();
            $table->unique('username');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('users')
            ->whereNull('email')
            ->orderBy('id')
            ->eachById(function (object $user): void {
                DB::table('users')->where('id', $user->id)->update([
                    'email' => $user->username.'@scottorun.local',
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
            $table->string('email')->nullable(false)->change();
        });
    }
};
