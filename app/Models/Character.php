<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'user_id',
        'name',
        'ancestry',
        'class_name',
        'level',
        'avatar',
        'character_sheet_path',
        'character_sheet_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'character_sheet_updated_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relics(): HasMany
    {
        return $this->hasMany(Relic::class);
    }
}
