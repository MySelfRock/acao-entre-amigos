<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Draw;
use App\Models\BingoClaim;
use App\Models\Winner;
use App\Models\Subcard;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DrawService
{
    /**
     * Start a new draw for the event
     */
    public function startDraw(Event $event, int $userId): array
    {
        if (!$event->isGenerated()) {
            throw new \Exception("Event must be in generated status to start draw");
        }

        DB::beginTransaction();

        try {
            $event->update([
                'status' => Event::STATUS_RUNNING,
                'started_at' => now(),
            ]);

            SystemLog::log(
                'draw_started',
                'event',
                $event->id,
                ['total_rounds' => $event->total_rounds],
                $userId
            );

            Log::info("🎯 Draw started for event: {$event->id}");

            DB::commit();

            return [
                'event_id' => $event->id,
                'status' => $event->status,
                'started_at' => $event->started_at,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Failed to start draw: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Draw the next number in the current round
     */
    public function drawNumber(Event $event, int $currentRound, int $userId): array
    {
        if (!$event->isRunning()) {
            throw new \Exception("Event is not in running status");
        }

        if ($currentRound < 1 || $currentRound > $event->total_rounds) {
            throw new \Exception("Invalid round number");
        }

        DB::beginTransaction();

        try {
            // Get the next available number (1-75, excluding already drawn in this round)
            $drawnNumbers = Draw::where('event_id', $event->id)
                ->where('round_number', $currentRound)
                ->pluck('number')
                ->toArray();

            $availableNumbers = array_diff(range(1, 75), $drawnNumbers);

            if (empty($availableNumbers)) {
                DB::rollBack();
                throw new \Exception("All 75 numbers have been drawn in this round");
            }

            // Select a random number from available ones
            $drawnNumber = $availableNumbers[array_rand($availableNumbers)];
            $drawOrder = count($drawnNumbers) + 1;

            // Record the draw
            $draw = Draw::create([
                'event_id' => $event->id,
                'round_number' => $currentRound,
                'number' => $drawnNumber,
                'draw_order' => $drawOrder,
                'drawn_at' => now(),
            ]);

            // Check for bingo claims on this number
            $this->checkForBingoClaims($event, $currentRound, $drawnNumber);

            SystemLog::log(
                'number_drawn',
                'draw',
                $event->id,
                [
                    'round' => $currentRound,
                    'number' => $drawnNumber,
                    'draw_order' => $drawOrder,
                ],
                $userId
            );

            Log::info("🎰 Drew number {$drawnNumber} in round {$currentRound} for event {$event->id}");

            DB::commit();

            return [
                'event_id' => $event->id,
                'round' => $currentRound,
                'number' => $drawnNumber,
                'draw_order' => $drawOrder,
                'drawn_at' => $draw->drawn_at,
                'total_drawn' => count($drawnNumbers) + 1,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Failed to draw number: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Check for bingo claims when a number is drawn
     */
    private function checkForBingoClaims(Event $event, int $round, int $drawnNumber): void
    {
        // Find all subcards with this number in this round
        $subcards = Subcard::where('event_id', $event->id)
            ->where('round_number', $round)
            ->whereHas('numbers', function ($query) use ($drawnNumber) {
                $query->where('number', $drawnNumber);
            })
            ->get();

        foreach ($subcards as $subcard) {
            // Mark the number as drawn (marked) on this subcard
            $subcard->numbers()
                ->where('number', $drawnNumber)
                ->update(['marked' => true, 'marked_at' => now()]);

            // Check if this subcard now has a complete bingo
            $this->checkSubcardForBingo($event, $subcard, $round);
        }
    }

    /**
     * Check if a subcard has all numbers marked (bingo)
     */
    private function checkSubcardForBingo(Event $event, Subcard $subcard, int $round): void
    {
        $unmarkedCount = $subcard->numbers()
            ->where('marked', false)
            ->count();

        // If all numbers are marked (including FREE which starts marked), it's a bingo!
        if ($unmarkedCount === 0) {
            Log::info("🎉 BINGO! Subcard {$subcard->id} completed in round {$round}");

            // Check if there's already a winner for this round
            $existingWinner = Winner::where('event_id', $event->id)
                ->where('round_number', $round)
                ->first();

            if (!$existingWinner) {
                // Create winner record
                Winner::create([
                    'event_id' => $event->id,
                    'subcard_id' => $subcard->id,
                    'card_id' => $subcard->card_id,
                    'round_number' => $round,
                    'awarded_at' => now(),
                ]);

                SystemLog::log(
                    'bingo_winner',
                    'subcard',
                    $subcard->id,
                    [
                        'round' => $round,
                        'event_id' => $event->id,
                    ],
                    null
                );
            }
        }
    }

    /**
     * Claim bingo for a subcard (digital participation)
     */
    public function claimBingo(Event $event, string $subcardId, int $userId): array
    {
        if (!$event->isRunning()) {
            throw new \Exception("Event is not in running status");
        }

        DB::beginTransaction();

        try {
            $subcard = Subcard::findOrFail($subcardId);

            if ($subcard->event_id !== $event->id) {
                throw new \Exception("Subcard does not belong to this event");
            }

            // Check if subcard actually has a bingo
            $unmarkedCount = $subcard->numbers()
                ->where('marked', false)
                ->count();

            if ($unmarkedCount > 0) {
                DB::rollBack();
                throw new \Exception("This subcard does not have a complete bingo yet");
            }

            // Check if claim already exists
            $existingClaim = BingoClaim::where('event_id', $event->id)
                ->where('subcard_id', $subcardId)
                ->first();

            if ($existingClaim) {
                DB::rollBack();
                throw new \Exception("Bingo claim already exists for this subcard");
            }

            // Create claim
            $claim = BingoClaim::create([
                'event_id' => $event->id,
                'subcard_id' => $subcardId,
                'claimed_by' => $userId,
                'is_valid' => true,
                'validated_at' => now(),
            ]);

            SystemLog::log(
                'bingo_claimed',
                'subcard',
                $subcardId,
                [
                    'event_id' => $event->id,
                    'claimed_by' => $userId,
                ],
                $userId
            );

            Log::info("✅ Bingo claimed for subcard {$subcardId} by user {$userId}");

            DB::commit();

            return [
                'claim_id' => $claim->id,
                'subcard_id' => $subcardId,
                'is_valid' => $claim->is_valid,
                'claimed_at' => $claim->created_at,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Failed to claim bingo: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Finish the event after all rounds
     */
    public function finishDraw(Event $event, int $userId): array
    {
        DB::beginTransaction();

        try {
            $event->update([
                'status' => Event::STATUS_FINISHED,
                'finished_at' => now(),
            ]);

            SystemLog::log(
                'draw_finished',
                'event',
                $event->id,
                [],
                $userId
            );

            Log::info("✅ Draw finished for event: {$event->id}");

            // Get final results
            $winners = Winner::where('event_id', $event->id)
                ->with('subcard.card', 'card')
                ->get();

            DB::commit();

            return [
                'event_id' => $event->id,
                'status' => $event->status,
                'finished_at' => $event->finished_at,
                'total_winners' => $winners->count(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Failed to finish draw: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Get current draw status
     */
    public function getDrawStatus(Event $event, int $currentRound): array
    {
        $draws = Draw::where('event_id', $event->id)
            ->where('round_number', $currentRound)
            ->orderBy('draw_order')
            ->get();

        $winner = Winner::where('event_id', $event->id)
            ->where('round_number', $currentRound)
            ->first();

        return [
            'event_id' => $event->id,
            'round' => $currentRound,
            'total_drawn' => $draws->count(),
            'drawn_numbers' => $draws->pluck('number')->toArray(),
            'available_numbers' => array_diff(range(1, 75), $draws->pluck('number')->toArray()),
            'has_winner' => $winner !== null,
            'winner' => $winner ? [
                'round' => $winner->round_number,
                'card_id' => $winner->card_id,
                'subcard_id' => $winner->subcard_id,
            ] : null,
        ];
    }

    /**
     * Get event results
     */
    public function getResults(Event $event): array
    {
        $winners = Winner::where('event_id', $event->id)
            ->with('subcard.card', 'card')
            ->orderBy('round_number')
            ->get();

        $totalDraws = Draw::where('event_id', $event->id)->count();

        return [
            'event_id' => $event->id,
            'event_name' => $event->name,
            'total_rounds' => $event->total_rounds,
            'total_draws' => $totalDraws,
            'total_winners' => $winners->count(),
            'winners' => $winners->map(function ($winner) {
                return [
                    'round' => $winner->round_number,
                    'card_id' => $winner->card_id,
                    'card_index' => $winner->card->card_index,
                    'subcard_id' => $winner->subcard_id,
                    'awarded_at' => $winner->awarded_at,
                ];
            })->toArray(),
        ];
    }
}
