<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BingoClaimed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Event $event;
    public string $subcardId;
    public string $claimedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(Event $event, string $subcardId, string $claimedBy)
    {
        $this->event = $event;
        $this->subcardId = $subcardId;
        $this->claimedBy = $claimedBy;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("event.{$this->event->id}.bingo"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->event->id,
            'subcard_id' => $this->subcardId,
            'claimed_by' => $this->claimedBy,
            'claimed_at' => now(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'bingo.claimed';
    }
}
