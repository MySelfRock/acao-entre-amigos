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
        Schema::create('card_layouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->nullable(); // null = layout global
            $table->string('name'); // Nome do layout
            $table->text('description')->nullable();
            $table->string('background_file')->nullable(); // Path do arquivo PNG/JPG
            $table->json('config')->nullable(); // Configurações: cores, fontes, posições
            /*
            {
                "bg_color": "#FFFFFF",
                "text_color": "#000000",
                "header_color": "#3498DB",
                "font_size_number": 14,
                "font_size_label": 12,
                "logo_url": "https://...",
                "footer_text": "Ação entre Amigos",
                "header_height": 80,
                "footer_height": 40
            }
            */
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->index(['event_id', 'is_active']);
        });

        // Add layout_id to events
        Schema::table('events', function (Blueprint $table) {
            $table->uuid('card_layout_id')->nullable()->after('metadata');
            $table->foreign('card_layout_id')->references('id')->on('card_layouts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeignIdFor('card_layout_id');
            $table->dropColumn('card_layout_id');
        });

        Schema::dropIfExists('card_layouts');
    }
};
