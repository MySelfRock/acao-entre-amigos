<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'card_index',
        'qr_code',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function subcards()
    {
        return $this->hasMany(Subcard::class);
    }

    public function winners()
    {
        return $this->hasMany(Winner::class);
    }
}
