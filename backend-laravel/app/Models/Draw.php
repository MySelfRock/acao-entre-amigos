<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Draw extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'round_number',
        'number',
        'draw_order',
        'drawn_at',
    ];

    protected $casts = [
        'drawn_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
