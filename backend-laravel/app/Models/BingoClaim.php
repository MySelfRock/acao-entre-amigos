<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BingoClaim extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'subcard_id',
        'claimed_by',
        'is_valid',
        'validated_at',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function subcard()
    {
        return $this->belongsTo(Subcard::class);
    }

    public function claimedBy()
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }
}
