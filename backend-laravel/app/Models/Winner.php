<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Winner extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'subcard_id',
        'card_id',
        'round_number',
        'prize_description',
        'awarded_at',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function subcard()
    {
        return $this->belongsTo(Subcard::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }
}
