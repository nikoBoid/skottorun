<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalPage extends Model
{
    protected $fillable = [
        'journal_topic_id',
        'author_id',
        'title',
        'content',
        'occurred_on',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(JournalTopic::class, 'journal_topic_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
