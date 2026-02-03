<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Draw;
use App\Models\Winner;
use App\Models\BingoClaim;
use App\Services\DrawService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DrawController extends Controller
{
    private DrawService $drawService;

    public function __construct(DrawService $drawService)
    {
        $this->drawService = $drawService;
    }

    /**
     * Start draw for an event
     * POST /api/events/{event}/draw/start
     */
    public function start(Event $event, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents() || $event->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $result = $this->drawService->startDraw($event, $request->user()->id);

            return response()->json([
                'message' => 'Draw started successfully',
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to start draw',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Draw next number in current round
     * POST /api/events/{event}/draw/next
     */
    public function drawNext(Event $event, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents() || $event->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validation
        $validated = $request->validate([
            'round' => 'required|integer|min:1|max:5',
        ]);

        try {
            $result = $this->drawService->drawNumber(
                $event,
                $validated['round'],
                $request->user()->id
            );

            // Broadcast the drawn number to all connected clients
            broadcast(new \App\Events\NumberDrawn($event, $validated['round'], $result['number']));

            return response()->json([
                'message' => 'Number drawn successfully',
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to draw number',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get current draw status for a round
     * GET /api/events/{event}/draw/status
     */
    public function status(Event $event, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'round' => 'required|integer|min:1|max:5',
        ]);

        try {
            $status = $this->drawService->getDrawStatus($event, $validated['round']);

            return response()->json([
                'data' => $status,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get draw status',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all drawn numbers for a round
     * GET /api/events/{event}/draw/numbers
     */
    public function getNumbers(Event $event, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'round' => 'required|integer|min:1|max:5',
        ]);

        $draws = Draw::where('event_id', $event->id)
            ->where('round_number', $validated['round'])
            ->orderBy('draw_order')
            ->get();

        return response()->json([
            'round' => $validated['round'],
            'total_drawn' => $draws->count(),
            'numbers' => $draws->map(function ($draw) {
                return [
                    'number' => $draw->number,
                    'draw_order' => $draw->draw_order,
                    'drawn_at' => $draw->drawn_at,
                ];
            })->toArray(),
        ], 200);
    }

    /**
     * Get current round winner (if exists)
     * GET /api/events/{event}/draw/winner
     */
    public function getWinner(Event $event, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'round' => 'required|integer|min:1|max:5',
        ]);

        $winner = Winner::where('event_id', $event->id)
            ->where('round_number', $validated['round'])
            ->with('card', 'subcard')
            ->first();

        if (!$winner) {
            return response()->json([
                'message' => 'No winner for this round yet',
            ], 404);
        }

        return response()->json([
            'data' => [
                'round' => $winner->round_number,
                'card_id' => $winner->card_id,
                'card_index' => $winner->card->card_index,
                'subcard_id' => $winner->subcard_id,
                'awarded_at' => $winner->awarded_at,
            ],
        ], 200);
    }

    /**
     * Claim bingo (digital participation)
     * POST /api/events/{event}/draw/claim
     */
    public function claimBingo(Event $event, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subcard_id' => 'required|uuid|exists:subcards,id',
        ]);

        try {
            $claim = $this->drawService->claimBingo(
                $event,
                $validated['subcard_id'],
                $request->user()->id
            );

            // Broadcast the bingo claim
            broadcast(new \App\Events\BingoClaimed($event, $validated['subcard_id'], $request->user()->id));

            return response()->json([
                'message' => 'Bingo claimed successfully',
                'data' => $claim,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to claim bingo',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all bingo claims for an event
     * GET /api/events/{event}/draw/claims
     */
    public function getClaims(Event $event, Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 20);

        $claims = BingoClaim::where('event_id', $event->id)
            ->with('subcard', 'claimedBy')
            ->paginate($perPage);

        return response()->json([
            'data' => $claims->map(function ($claim) {
                return [
                    'id' => $claim->id,
                    'subcard_id' => $claim->subcard_id,
                    'claimed_by' => $claim->claimedBy?->name,
                    'is_valid' => $claim->is_valid,
                    'claimed_at' => $claim->created_at,
                ];
            })->toArray(),
            'pagination' => [
                'total' => $claims->total(),
                'per_page' => $claims->perPage(),
                'current_page' => $claims->currentPage(),
                'last_page' => $claims->lastPage(),
            ],
        ], 200);
    }

    /**
     * Finish the draw
     * POST /api/events/{event}/draw/finish
     */
    public function finish(Event $event, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents() || $event->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $result = $this->drawService->finishDraw($event, $request->user()->id);

            return response()->json([
                'message' => 'Draw finished successfully',
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to finish draw',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get final results for the event
     * GET /api/events/{event}/draw/results
     */
    public function getResults(Event $event): JsonResponse
    {
        try {
            $results = $this->drawService->getResults($event);

            return response()->json([
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get results',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
