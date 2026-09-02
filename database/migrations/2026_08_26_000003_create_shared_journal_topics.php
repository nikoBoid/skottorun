<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->timestamps();

            $table->index(['campaign_id', 'title']);
        });

        Schema::create('journal_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->date('occurred_on')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['journal_topic_id', 'sort_order']);
        });

        DB::table('journal_entries')
            ->orderBy('id')
            ->each(function (object $entry): void {
                $topicId = DB::table('journal_topics')->insertGetId([
                    'campaign_id' => $entry->campaign_id,
                    'created_by' => $entry->author_id,
                    'title' => $entry->title,
                    'created_at' => $entry->created_at,
                    'updated_at' => $entry->updated_at,
                ]);

                DB::table('journal_pages')->insert([
                    'journal_topic_id' => $topicId,
                    'author_id' => $entry->author_id,
                    'title' => 'Pagina iniziale',
                    'content' => $entry->content,
                    'occurred_on' => $entry->occurred_on,
                    'sort_order' => 0,
                    'created_at' => $entry->created_at,
                    'updated_at' => $entry->updated_at,
                ]);
            });

        Schema::dropIfExists('journal_entries');
    }

    public function down(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->string('visibility')->default('shared');
            $table->date('occurred_on')->nullable();
            $table->timestamps();
        });

        DB::table('journal_pages')
            ->join('journal_topics', 'journal_topics.id', '=', 'journal_pages.journal_topic_id')
            ->select('journal_pages.*', 'journal_topics.campaign_id', 'journal_topics.title as topic_title')
            ->orderBy('journal_pages.id')
            ->each(function (object $page): void {
                DB::table('journal_entries')->insert([
                    'campaign_id' => $page->campaign_id,
                    'character_id' => DB::table('characters')->where('campaign_id', $page->campaign_id)->value('id'),
                    'author_id' => $page->author_id,
                    'title' => $page->topic_title.' — '.$page->title,
                    'content' => $page->content,
                    'visibility' => 'shared',
                    'occurred_on' => $page->occurred_on,
                    'created_at' => $page->created_at,
                    'updated_at' => $page->updated_at,
                ]);
            });

        Schema::dropIfExists('journal_pages');
        Schema::dropIfExists('journal_topics');
    }
};
