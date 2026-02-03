<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Card;
use App\Models\CardLayout;
use App\Jobs\GenerateCardsJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

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
     * Generate PDF files for cards
     */
    public function generatePDFs(Event $event, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents() || $event->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validation
        $validated = $request->validate([
            'card_ids' => 'nullable|array',
            'card_ids.*' => 'uuid|exists:cards,id',
            'limit' => 'nullable|integer|min:1|max:10000',
            'layout' => 'nullable|string',
        ]);

        try {
            // Get cards (all or specific IDs)
            $query = $event->cards()->with('subcards.numbers');

            if ($validated['card_ids'] ?? null) {
                $query->whereIn('id', $validated['card_ids']);
            } else {
                $limit = $validated['limit'] ?? 100;
                $query->limit($limit);
            }

            $cards = $query->get();

            if ($cards->isEmpty()) {
                return response()->json(['message' => 'No cards found'], 404);
            }

            // Get layout configuration
            $layout = $validated['layout'] ?? 'default';
            $layoutModel = $event->cardLayout ??
                CardLayout::where('is_default', true)->first();

            // Build card data for PDF generation
            $cardsData = $cards->map(function ($card) {
                return [
                    'card_id' => $card->id,
                    'card_index' => $card->card_index,
                    'qr_code' => $card->qr_code,
                    'event_id' => $card->event_id,
                    'subcards' => $card->subcards->map(function ($subcard) {
                        return [
                            'round' => $subcard->round_number,
                            'hash' => $subcard->hash,
                            'grid' => $subcard->getGrid(),
                        ];
                    })->toArray(),
                ];
            })->toArray();

            // Call Python PDF service
            $pdfUrls = $this->callPDFService(
                $event,
                $cardsData,
                $layout,
                $layoutModel
            );

            return response()->json([
                'message' => 'PDFs generated successfully',
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                ],
                'pdfs' => [
                    'total' => count($pdfUrls),
                    'urls' => $pdfUrls,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate PDFs',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Call Python PDF generation service
     */
    private function callPDFService(
        Event $event,
        array $cardsData,
        string $layout,
        ?CardLayout $layoutModel
    ): array {
        $generatorUrl = config('services.generator.url', 'http://generator:8000');
        $apiKey = config('services.generator.api_key', env('GENERATOR_API_KEY', 'dev-api-key'));

        $payload = [
            'event_id' => $event->id,
            'event_name' => $event->name,
            'event_date' => $event->event_date?->format('Y-m-d'),
            'event_location' => $event->location,
            'cards' => $cardsData,
            'layout' => $layout,
        ];

        // Add layout config if available
        if ($layoutModel) {
            $payload['layout_config'] = $layoutModel->getMergedConfig();
        }

        $response = Http::withHeaders([
            'X-API-KEY' => $apiKey,
        ])->timeout(600) // 10 minute timeout for large PDF batches
        ->post("{$generatorUrl}/generator/pdf", $payload);

        if (!$response->successful()) {
            throw new \Exception("PDF service error ({$response->status()}): " . $response->body());
        }

        $data = $response->json();

        if ($data['status'] !== 'ok') {
            throw new \Exception("PDF service returned error: {$data['status']}");
        }

        return $data['pdf_urls'] ?? [];
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
