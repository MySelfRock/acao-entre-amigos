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

class NumberDrawn implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Event $event;
    public int $round;
    public int $number;

    /**
     * Create a new event instance.
     */
    public function __construct(Event $event, int $round, int $number)
    {
        $this->event = $event;
        $this->round = $round;
        $this->number = $number;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("event.{$this->event->id}.draw"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->event->id,
            'round' => $this->round,
            'number' => $this->number,
            'drawn_at' => now(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'number.drawn';
    }
}
