<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Card;
use App\Jobs\GenerateCardsJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CardController extends Controller
{
    /**
     * List cards for event
     */
    public function index(Event $event, Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);

        $cards = $event->cards()
            ->with('subcards')
            ->paginate($perPage);

        return response()->json([
            'data' => $cards->map(fn($card) => [
                'id' => $card->id,
                'card_index' => $card->card_index,
                'qr_code' => $card->qr_code,
                'subcards_count' => $card->subcards->count(),
                'created_at' => $card->created_at,
            ]),
            'pagination' => [
                'total' => $cards->total(),
                'per_page' => $cards->perPage(),
                'current_page' => $cards->currentPage(),
                'last_page' => $cards->lastPage(),
            ],
        ]);
    }

    /**
     * Get card details with subcards
     */
    public function show(Card $card): JsonResponse
    {
        return response()->json([
            'card' => [
                'id' => $card->id,
                'event_id' => $card->event_id,
                'card_index' => $card->card_index,
                'qr_code' => $card->qr_code,
                'subcards' => $card->subcards->map(fn($subcard) => [
                    'id' => $subcard->id,
                    'round' => $subcard->round_number,
                    'hash' => $subcard->hash,
                    'grid' => $subcard->getGrid(),
                ]),
                'created_at' => $card->created_at,
            ],
        ]);
    }

    /**
     * Trigger card generation
     */
    public function generate(Event $event, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents() || $event->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validation
        if (!$event->canGenerateCards()) {
            return response()->json([
                'message' => 'Event must be in draft status to generate cards',
            ], 400);
        }

        try {
            // Dispatch job to queue
            GenerateCardsJob::dispatch($event);

            return response()->json([
                'message' => 'Card generation started',
                'event' => [
                    'id' => $event->id,
                    'status' => $event->status,
                ],
                'job' => [
                    'total_cards' => $event->total_cards,
                    'total_rounds' => $event->total_rounds,
                ],
            ], 202); // Accepted

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to start card generation',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get card generation status
     */
    public function generateStatus(Event $event): JsonResponse
    {
        return response()->json([
            'event' => [
                'id' => $event->id,
                'status' => $event->status,
                'cards_generated' => $event->getCardsCount(),
                'total_cards' => $event->total_cards,
                'progress' => $event->total_cards > 0
                    ? round(($event->getCardsCount() / $event->total_cards) * 100, 2)
                    : 0,
            ],
        ]);
    }

    /**
     * Download card by QR code
     */
    public function downloadByQR(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => 'required|string|exists:cards,qr_code',
        ]);

        $card = Card::where('qr_code', $validated['qr_code'])->first();

        return response()->json([
            'card' => [
                'id' => $card->id,
                'qr_code' => $card->qr_code,
                'card_index' => $card->card_index,
            ],
        ]);
    }
}
