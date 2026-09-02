<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $campaignId,
        public readonly string $area,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("campaign.{$this->campaignId}")];
    }

    /**
     * @return array{campaignId: int, area: string}
     */
    public function broadcastWith(): array
    {
        return [
            'campaignId' => $this->campaignId,
            'area' => $this->area,
        ];
    }
}
