<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'event_date',
        'location',
        'total_cards',
        'total_rounds',
        'seed',
        'participation_type',
        'status',
        'created_by',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'event_date' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => 'json',
    ];

    /**
     * Event statuses
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_GENERATED = 'generated';
    const STATUS_RUNNING = 'running';
    const STATUS_FINISHED = 'finished';

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_GENERATED,
            self::STATUS_RUNNING,
            self::STATUS_FINISHED,
        ];
    }

    /**
     * Participation types
     */
    const PARTICIPATION_DIGITAL = 'digital';
    const PARTICIPATION_PRESENCIAL = 'presencial';
    const PARTICIPATION_HIBRIDO = 'hibrido';

    public static function participationTypes(): array
    {
        return [
            self::PARTICIPATION_DIGITAL,
            self::PARTICIPATION_PRESENCIAL,
            self::PARTICIPATION_HIBRIDO,
        ];
    }

    /**
     * Status checks
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isGenerated(): bool
    {
        return $this->status === self::STATUS_GENERATED;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function canGenerateCards(): bool
    {
        return $this->isDraft();
    }

    public function canStart(): bool
    {
        return $this->isGenerated();
    }

    /**
     * Relationships
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    public function draws()
    {
        return $this->hasMany(Draw::class);
    }

    public function bingoClaimsAsEvent()
    {
        return $this->hasMany(BingoClaim::class);
    }

    public function winners()
    {
        return $this->hasMany(Winner::class);
    }

    public function systemLogs()
    {
        return $this->hasMany(SystemLog::class, 'reference_id')
            ->where('reference_type', 'event');
    }

    /**
     * Get cards count
     */
    public function getCardsCount(): int
    {
        return $this->cards()->count();
    }

    /**
     * Get draws count for current round
     */
    public function getDrawsCount(): int
    {
        return $this->draws()->count();
    }
}
