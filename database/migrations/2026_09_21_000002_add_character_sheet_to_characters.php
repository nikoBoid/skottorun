<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('character_sheet_path')->nullable()->after('avatar');
            $table->timestamp('character_sheet_updated_at')->nullable()->after('character_sheet_path');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['character_sheet_path', 'character_sheet_updated_at']);
        });
    }
};
