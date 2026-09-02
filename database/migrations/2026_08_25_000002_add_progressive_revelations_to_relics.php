<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relics', function (Blueprint $table) {
            $table->unsignedBigInteger('character_id')->nullable()->change();
            $table->string('image_path')->nullable()->after('description');
        });

        Schema::create('relic_revelations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relic_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('skill');
            $table->string('title');
            $table->text('content');
            $table->boolean('is_unlocked')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('unlocked_at')->nullable();
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['relic_id', 'sort_order']);
        });

        DB::table('relics')
            ->whereNotNull('dm_notes')
            ->where('dm_notes', '!=', '')
            ->orderBy('id')
            ->each(function (object $relic): void {
                DB::table('relic_revelations')->insert([
                    'relic_id' => $relic->id,
                    'kind' => 'lore',
                    'title' => 'Segreto della reliquia',
                    'content' => $relic->dm_notes,
                    'is_unlocked' => false,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('relics', function (Blueprint $table) {
            $table->dropColumn(['dm_notes', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('relics', function (Blueprint $table) {
            $table->text('dm_notes')->nullable();
            $table->string('status')->default('misteriosa');
        });

        DB::table('relics')->whereNull('character_id')->delete();

        Schema::dropIfExists('relic_revelations');

        Schema::table('relics', function (Blueprint $table) {
            $table->dropColumn('image_path');
            $table->unsignedBigInteger('character_id')->nullable(false)->change();
        });
    }
};
