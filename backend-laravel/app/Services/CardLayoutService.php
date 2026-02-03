<?php

namespace App\Services;

use App\Models\CardLayout;
use App\Models\Event;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CardLayoutService
{
    /**
     * Create a new layout
     */
    public function createLayout(array $data, string $userId): CardLayout
    {
        $layout = CardLayout::create([
            'id' => Str::uuid(),
            'event_id' => $data['event_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'background_file' => null, // Will be set if file uploaded
            'config' => $data['config'] ?? CardLayout::defaultConfig(),
            'is_default' => $data['is_default'] ?? false,
            'is_active' => true,
            'created_by' => $userId,
        ]);

        return $layout;
    }

    /**
     * Upload background image
     */
    public function uploadBackground(CardLayout $layout, $file): string
    {
        // Delete old file if exists
        if ($layout->background_file) {
            Storage::disk('public')->delete('layouts/' . $layout->background_file);
        }

        // Store new file
        $filename = $layout->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = Storage::disk('public')->putFileAs('layouts', $file, $filename);

        // Update layout
        $layout->update(['background_file' => $filename]);

        return $path;
    }

    /**
     * Update layout configuration
     */
    public function updateConfig(CardLayout $layout, array $config): CardLayout
    {
        $layout->update(['config' => $config]);
        return $layout;
    }

    /**
     * Get default layout for event
     */
    public function getDefaultLayout(Event $event): ?CardLayout
    {
        return CardLayout::where('event_id', $event->id)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->first() ?? CardLayout::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Set as default for event
     */
    public function setAsDefault(CardLayout $layout): void
    {
        // Unset previous default
        if ($layout->event_id) {
            CardLayout::where('event_id', $layout->event_id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $layout->update(['is_default' => true]);
    }

    /**
     * Deactivate layout
     */
    public function deactivate(CardLayout $layout): void
    {
        $layout->update(['is_active' => false]);
    }

    /**
     * Delete layout
     */
    public function delete(CardLayout $layout): void
    {
        if ($layout->background_file) {
            Storage::disk('public')->delete('layouts/' . $layout->background_file);
        }

        $layout->delete();
    }
}
