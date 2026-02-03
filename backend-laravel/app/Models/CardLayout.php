<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardLayout extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'background_file',
        'config',
        'is_default',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'config' => 'json',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Default configuration
     */
    public static function defaultConfig(): array
    {
        return [
            'bg_color' => '#FFFFFF',
            'text_color' => '#000000',
            'header_color' => '#3498DB',
            'font_size_number' => 14,
            'font_size_label' => 12,
            'footer_text' => 'Ação entre Amigos',
            'header_height' => 80,
            'footer_height' => 40,
            'free_space_color' => '#FFD700',
        ];
    }

    /**
     * Relationships
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get background file URL
     */
    public function getBackgroundUrl(): ?string
    {
        if (!$this->background_file) {
            return null;
        }

        return asset('storage/layouts/' . $this->background_file);
    }

    /**
     * Get merged configuration with defaults
     */
    public function getMergedConfig(): array
    {
        return array_merge(self::defaultConfig(), $this->config ?? []);
    }
}
