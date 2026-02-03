<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Card;
use App\Models\Subcard;
use App\Models\SubcardNumber;
use App\Models\SystemLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateCardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Event $event;
    private int $tries = 3;
    private int $timeout = 3600; // 1 hour

    /**
     * Create a new job instance.
     */
    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("🎰 Starting card generation for event: {$this->event->id}");

            // Call Python generator service
            $generatedData = $this->callGeneratorService();

            if (!$generatedData) {
                throw new \Exception('Generator service returned empty data');
            }

            // Persist cards and subcards in database
            $this->persistCards($generatedData);

            // Update event status
            $this->event->update(['status' => Event::STATUS_GENERATED]);

            // Log success
            SystemLog::log(
                'cards_generated',
                'event',
                $this->event->id,
                [
                    'total_cards' => $this->event->total_cards,
                    'total_rounds' => $this->event->total_rounds,
                ],
                auth()->id()
            );

            Log::info("✅ Card generation completed for event: {$this->event->id}");

        } catch (\Exception $e) {
            Log::error("❌ Card generation failed for event {$this->event->id}: {$e->getMessage()}");

            SystemLog::log(
                'cards_generation_failed',
                'event',
                $this->event->id,
                ['error' => $e->getMessage()],
                auth()->id()
            );

            throw $e;
        }
    }

    /**
     * Call Python generator service
     */
    private function callGeneratorService(): array
    {
        $payload = [
            'event_id' => $this->event->id,
            'seed' => $this->event->seed,
            'total_cards' => $this->event->total_cards,
            'rounds' => $this->event->total_rounds,
        ];

        Log::debug("📞 Calling generator service with payload:", $payload);

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => config('services.generator.api_key'),
            ])->timeout(300)
              ->post(config('services.generator.url') . '/generator/generate', $payload);

            if (!$response->successful()) {
                throw new \Exception("Generator API returned: {$response->status()} - {$response->body()}");
            }

            $data = $response->json();

            if ($data['status'] !== 'ok') {
                throw new \Exception("Generator returned error status: {$data['status']}");
            }

            Log::info("✅ Generator returned: {$data['generated']} subcards");

            return $data;

        } catch (\Exception $e) {
            Log::error("❌ Generator service error: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Persist cards and subcards to database from generator response
     */
    private function persistCards(array $generatedData): void
    {
        if (!isset($generatedData['cards']) || !is_array($generatedData['cards'])) {
            throw new \Exception('Invalid generator response: missing cards array');
        }

        $cards = $generatedData['cards'];
        Log::info("💾 Persisting {$this->event->total_cards} cards to database");

        $cardsCreated = 0;
        $subcardsCreated = 0;

        foreach ($cards as $cardData) {
            try {
                // Create card with data from generator
                $card = Card::create([
                    'id' => $cardData['card_id'] ?? Str::uuid(),
                    'event_id' => $this->event->id,
                    'card_index' => $cardData['card_index'] ?? ($cardsCreated + 1),
                    'qr_code' => $cardData['qr_code'] ?? '',
                ]);

                $cardsCreated++;

                // Create subcards from the provided subcard data
                if (!isset($cardData['subcards']) || !is_array($cardData['subcards'])) {
                    Log::warning("Card {$card->id} has no subcards in generator response");
                    continue;
                }

                foreach ($cardData['subcards'] as $subcardData) {
                    if (!isset($subcardData['round']) || !isset($subcardData['hash']) || !isset($subcardData['grid'])) {
                        Log::warning("Skipping invalid subcard data for card {$card->id}");
                        continue;
                    }

                    $subcard = Subcard::create([
                        'id' => Str::uuid(),
                        'card_id' => $card->id,
                        'event_id' => $this->event->id,
                        'round_number' => (int)$subcardData['round'],
                        'hash' => $subcardData['hash'],
                    ]);

                    $subcardsCreated++;

                    // Store grid numbers from the provided grid
                    $grid = $subcardData['grid'];
                    foreach ($grid as $rowIdx => $row) {
                        foreach ($row as $colIdx => $value) {
                            SubcardNumber::create([
                                'id' => Str::uuid(),
                                'subcard_id' => $subcard->id,
                                'number' => $value === 'FREE' ? 0 : (int)$value,
                                'row' => $rowIdx,
                                'column' => $colIdx,
                                'marked' => $value === 'FREE', // FREE is always marked
                            ]);
                        }
                    }
                }

                if ($cardsCreated % 100 === 0) {
                    Log::info("Progress: {$cardsCreated}/{$this->event->total_cards} cards persisted");
                }

            } catch (\Exception $e) {
                Log::error("Failed to persist card: {$e->getMessage()}");
                throw $e;
            }
        }

        Log::info("✅ Persisted: {$cardsCreated} cards, {$subcardsCreated} subcards");

        if ($cardsCreated !== $this->event->total_cards) {
            Log::warning("Expected {$this->event->total_cards} cards but persisted {$cardsCreated}");
        }
    }

}
