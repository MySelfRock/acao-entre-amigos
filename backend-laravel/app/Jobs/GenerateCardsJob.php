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
     * Persist cards and subcards to database
     */
    private function persistCards(array $generatedData): void
    {
        Log::info("💾 Persisting {$this->event->total_cards} cards to database");

        $cardsCreated = 0;
        $subcardsCreated = 0;

        // Get generator data for all cards
        $generatorResponse = $this->getGeneratorData();

        for ($cardIndex = 0; $cardIndex < $this->event->total_cards; $cardIndex++) {
            // Create card
            $card = Card::create([
                'id' => Str::uuid(),
                'event_id' => $this->event->id,
                'card_index' => $cardIndex + 1,
                'qr_code' => $this->generateQRCode($cardIndex),
            ]);

            $cardsCreated++;

            // Create subcards for each round
            for ($round = 1; $round <= $this->event->total_rounds; $round++) {
                // Get subcard data from generator (reconstruct from seed if needed)
                $subcardData = $this->getSubcardData($cardIndex, $round);

                $subcard = Subcard::create([
                    'id' => Str::uuid(),
                    'card_id' => $card->id,
                    'event_id' => $this->event->id,
                    'round_number' => $round,
                    'hash' => $subcardData['hash'],
                ]);

                $subcardsCreated++;

                // Store grid numbers
                foreach ($subcardData['grid'] as $row => $cols) {
                    foreach ($cols as $col => $value) {
                        SubcardNumber::create([
                            'subcard_id' => $subcard->id,
                            'row' => $row,
                            'col' => $col,
                            'value' => $value,
                        ]);
                    }
                }

                if ($round % 100 === 0 && $cardIndex % 10 === 0) {
                    Log::debug("Progress: Card {$cardIndex}/{$this->event->total_cards}, Round {$round}/{$this->event->total_rounds}");
                }
            }

            if (($cardIndex + 1) % 100 === 0) {
                Log::info("Progress: {$cardIndex + 1}/{$this->event->total_cards} cards created");
            }
        }

        Log::info("✅ Persisted: {$cardsCreated} cards, {$subcardsCreated} subcards");
    }

    /**
     * Get subcard data (will be enhanced when Python returns batch data)
     */
    private function getSubcardData(int $cardIndex, int $round): array
    {
        // For now, return a placeholder
        // In production, this will be fetched from the Python service batch response
        return [
            'hash' => hash('sha256', "{$this->event->id}:{$round}:{$cardIndex}"),
            'grid' => $this->generateDefaultGrid($round),
        ];
    }

    /**
     * Generate default grid for demonstration
     */
    private function generateDefaultGrid(int $round): array
    {
        $grid = [];
        $ranges = [
            [1, 15],   // B
            [16, 30],  // I
            [31, 45],  // N
            [46, 60],  // G
            [61, 75],  // O
        ];

        for ($row = 0; $row < 5; $row++) {
            for ($col = 0; $col < 5; $col++) {
                if ($row === 2 && $col === 2) {
                    $grid[$row][$col] = 'FREE';
                } else {
                    $min = $ranges[$col][0];
                    $max = $ranges[$col][1];
                    $grid[$row][$col] = (string)rand($min, $max);
                }
            }
        }

        return $grid;
    }

    /**
     * Generate QR code identifier
     */
    private function generateQRCode(int $cardIndex): string
    {
        return $this->event->id . '-' . str_pad($cardIndex + 1, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get generator data (placeholder for batch response)
     */
    private function getGeneratorData(): array
    {
        return [];
    }
}
