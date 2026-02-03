<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('draws', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->tinyInteger('round_number'); // Current round
            $table->tinyInteger('number'); // 1-75
            $table->integer('draw_order'); // Sequence in round
            $table->timestamp('drawn_at');
            $table->timestamps();

            $table->unique(['event_id', 'number']); // No repetition
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->index(['event_id', 'round_number']);
            $table->index('drawn_at');
        });

        Schema::create('bingo_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('subcard_id');
            $table->uuid('claimed_by')->nullable(); // FK users (nullable for presencial)
            $table->boolean('is_valid')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('subcard_id')->references('id')->on('subcards')->onDelete('cascade');
            $table->foreign('claimed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['event_id', 'is_valid']);
            $table->index('claimed_by');
        });

        Schema::create('winners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('subcard_id');
            $table->uuid('card_id');
            $table->tinyInteger('round_number');
            $table->string('prize_description')->nullable();
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->unique(['event_id', 'round_number']); // One winner per round
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('subcard_id')->references('id')->on('subcards')->onDelete('cascade');
            $table->foreign('card_id')->references('id')->on('cards')->onDelete('cascade');
            $table->index('event_id');
        });

        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->nullable();
            $table->string('action'); // event_created, cards_generated, draw_executed, etc
            $table->string('reference_type')->nullable(); // event, card, draw
            $table->uuid('reference_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->index();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['reference_type', 'reference_id']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
        Schema::dropIfExists('winners');
        Schema::dropIfExists('bingo_claims');
        Schema::dropIfExists('draws');
    }
};
