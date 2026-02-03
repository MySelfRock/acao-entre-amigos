<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Models\SystemLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class EventService
{
    /**
     * Create a new event
     */
    public function createEvent(array $data, User $creator): Event
    {
        // Generate global seed (never exposed to client)
        $seed = Hash::make(
            Str::uuid() . ':' . now()->timestamp . ':' . Str::random(64)
        );

        $event = Event::create([
            'id' => Str::uuid(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'event_date' => $data['event_date'],
            'location' => $data['location'] ?? null,
            'total_cards' => $data['total_cards'] ?? 2000,
            'total_rounds' => 5, // Fixed
            'seed' => $seed,
            'participation_type' => $data['participation_type'] ?? Event::PARTICIPATION_HIBRIDO,
            'status' => Event::STATUS_DRAFT,
            'created_by' => $creator->id,
        ]);

        SystemLog::log(
            'event_created',
            'event',
            $event->id,
            [
                'name' => $event->name,
                'total_cards' => $event->total_cards,
                'participation_type' => $event->participation_type,
            ],
            $creator->id
        );

        return $event;
    }

    /**
     * Update event (only if draft)
     */
    public function updateEvent(Event $event, array $data): Event
    {
        if (!$event->canGenerateCards()) {
            throw new \Exception('Event cannot be updated in current status');
        }

        $event->update($data);

        SystemLog::log(
            'event_updated',
            'event',
            $event->id,
            ['changes' => array_keys($data)],
            auth()->id()
        );

        return $event;
    }

    /**
     * Mark cards as generated
     */
    public function markAsGenerated(Event $event): Event
    {
        $event->update(['status' => Event::STATUS_GENERATED]);

        SystemLog::log(
            'cards_generated',
            'event',
            $event->id,
            ['total_cards' => $event->total_cards],
            auth()->id()
        );

        return $event;
    }

    /**
     * Start event
     */
    public function startEvent(Event $event): Event
    {
        if (!$event->canStart()) {
            throw new \Exception('Event cannot be started in current status');
        }

        $event->update([
            'status' => Event::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        SystemLog::log(
            'event_started',
            'event',
            $event->id,
            [],
            auth()->id()
        );

        return $event;
    }

    /**
     * Finish event
     */
    public function finishEvent(Event $event): Event
    {
        $event->update([
            'status' => Event::STATUS_FINISHED,
            'finished_at' => now(),
        ]);

        SystemLog::log(
            'event_finished',
            'event',
            $event->id,
            [],
            auth()->id()
        );

        return $event;
    }

    /**
     * Get event with related data
     */
    public function getEventDetails(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'description' => $event->description,
            'event_date' => $event->event_date,
            'location' => $event->location,
            'total_cards' => $event->total_cards,
            'total_rounds' => $event->total_rounds,
            'participation_type' => $event->participation_type,
            'status' => $event->status,
            'cards_generated' => $event->getCardsCount(),
            'draws_count' => $event->getDrawsCount(),
            'winners_count' => $event->winners()->count(),
            'started_at' => $event->started_at,
            'finished_at' => $event->finished_at,
            'created_by' => $event->creator->name,
            'created_at' => $event->created_at,
        ];
    }
}
