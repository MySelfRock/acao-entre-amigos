<?php

namespace App\Http\Controllers;

use App\Services\EventService;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    private EventService $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }

    /**
     * List all events (with filters)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::query();

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('participation_type')) {
            $query->where('participation_type', $request->get('participation_type'));
        }

        if ($request->has('created_by')) {
            $query->where('created_by', $request->get('created_by'));
        }

        // Authorization: Only admins and operadores can see all events
        if (!$request->user()->canManageEvents() && !$request->user()->isAdmin()) {
            $query->where('participation_type', Event::PARTICIPATION_DIGITAL);
        }

        $events = $query->with('creator')->paginate(15);

        return response()->json([
            'data' => $events->map(fn($event) => [
                'id' => $event->id,
                'name' => $event->name,
                'event_date' => $event->event_date,
                'status' => $event->status,
                'total_cards' => $event->total_cards,
                'participation_type' => $event->participation_type,
                'created_by' => $event->creator->name,
                'created_at' => $event->created_at,
            ]),
            'pagination' => [
                'total' => $events->total(),
                'per_page' => $events->perPage(),
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new event
     */
    public function store(Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date_format:Y-m-d H:i:s|after:now',
            'location' => 'nullable|string|max:255',
            'total_cards' => 'required|integer|min:1|max:100000',
            'participation_type' => 'required|in:' . implode(',', [
                Event::PARTICIPATION_DIGITAL,
                Event::PARTICIPATION_PRESENCIAL,
                Event::PARTICIPATION_HIBRIDO,
            ]),
        ]);

        try {
            $event = $this->eventService->createEvent($validated, $request->user());

            return response()->json([
                'message' => 'Event created successfully',
                'event' => $this->eventService->getEventDetails($event),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create event',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get event details
     */
    public function show(Event $event, Request $request): JsonResponse
    {
        return response()->json([
            'event' => $this->eventService->getEventDetails($event),
        ]);
    }

    /**
     * Update event (only if draft)
     */
    public function update(Event $event, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents() || $event->created_by !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'total_cards' => 'nullable|integer|min:1|max:100000',
        ]);

        try {
            $event = $this->eventService->updateEvent($event, $validated);

            return response()->json([
                'message' => 'Event updated successfully',
                'event' => $this->eventService->getEventDetails($event),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update event',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Start event
     */
    public function start(Event $event, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents() || $event->created_by !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $event = $this->eventService->startEvent($event);

            return response()->json([
                'message' => 'Event started successfully',
                'event' => $this->eventService->getEventDetails($event),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to start event',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Finish event
     */
    public function finish(Event $event, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents() || $event->created_by !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $event = $this->eventService->finishEvent($event);

            return response()->json([
                'message' => 'Event finished successfully',
                'event' => $this->eventService->getEventDetails($event),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to finish event',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
