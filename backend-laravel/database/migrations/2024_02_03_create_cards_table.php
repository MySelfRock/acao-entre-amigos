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
        Schema::create('cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->integer('card_index'); // Sequential in event
            $table->string('qr_code')->unique(); // Public identifier
            $table->timestamps();

            $table->unique(['event_id', 'card_index']);
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->index('event_id');
            $table->index('qr_code');
        });

        Schema::create('subcards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('card_id');
            $table->uuid('event_id'); // Intentional redundancy for query optimization
            $table->tinyInteger('round_number'); // 1-5
            $table->string('hash')->index(); // Uniqueness verification
            $table->timestamps();

            $table->unique(['event_id', 'round_number', 'hash']);
            $table->unique(['card_id', 'round_number']);
            $table->foreign('card_id')->references('id')->on('cards')->onDelete('cascade');
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->index('event_id');
        });

        Schema::create('subcard_numbers', function (Blueprint $table) {
            $table->id();
            $table->uuid('subcard_id');
            $table->tinyInteger('row'); // 0-4
            $table->tinyInteger('col'); // 0-4
            $table->string('value'); // Number or FREE
            $table->timestamps();

            $table->foreign('subcard_id')->references('id')->on('subcards')->onDelete('cascade');
            $table->unique(['subcard_id', 'row', 'col']);
            $table->index('subcard_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subcard_numbers');
        Schema::dropIfExists('subcards');
        Schema::dropIfExists('cards');
    }
};
