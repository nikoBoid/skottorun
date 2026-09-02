<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = ['dungeon_master_id', 'name', 'slug', 'synopsis'];

    public function dungeonMaster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dungeon_master_id');
    }

    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function relics(): HasMany
    {
        return $this->hasMany(Relic::class);
    }

    public function journalTopics(): HasMany
    {
        return $this->hasMany(JournalTopic::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
