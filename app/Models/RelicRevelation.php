<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelicRevelation extends Model
{
    protected $fillable = [
        'relic_id',
        'kind',
        'title',
        'content',
        'is_unlocked',
        'sort_order',
        'unlocked_at',
        'unlocked_by',
    ];

    protected function casts(): array
    {
        return [
            'is_unlocked' => 'boolean',
            'sort_order' => 'integer',
            'unlocked_at' => 'datetime',
        ];
    }

    public function relic(): BelongsTo
    {
        return $this->belongsTo(Relic::class);
    }

    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }
}
