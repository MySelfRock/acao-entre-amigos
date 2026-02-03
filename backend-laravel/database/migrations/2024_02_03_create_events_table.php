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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->dateTime('event_date');
            $table->string('location')->nullable();
            $table->integer('total_cards')->default(2000);
            $table->integer('total_rounds')->default(5);
            $table->string('seed')->nullable(); // Never exposed to client
            $table->enum('participation_type', ['digital', 'presencial', 'hibrido'])->default('hibrido');
            $table->enum('status', ['draft', 'generated', 'running', 'finished'])->default('draft');
            $table->uuid('created_by'); // FK users
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_by');
            $table->index('event_date');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
