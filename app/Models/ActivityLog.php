<?php

namespace App\Models;

use App\Events\CampaignChanged;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'campaign_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'properties',
    ];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        Campaign $campaign,
        ?User $user,
        string $action,
        Model $subject,
        string $description,
        array $properties = [],
    ): self {
        $activity = self::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'description' => $description,
            'properties' => $properties ?: null,
        ]);

        CampaignChanged::dispatch($campaign->id, $subject->getTable());

        return $activity;
    }
}
