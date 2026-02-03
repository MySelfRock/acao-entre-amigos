<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subcard extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'card_id',
        'event_id',
        'round_number',
        'hash',
    ];

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function numbers()
    {
        return $this->hasMany(SubcardNumber::class);
    }

    public function bingoClaimsAsSubcard()
    {
        return $this->hasMany(BingoClaim::class);
    }

    public function winnersAsSubcard()
    {
        return $this->hasMany(Winner::class);
    }

    /**
     * Get grid as 5x5 array
     */
    public function getGrid(): array
    {
        $grid = array_fill(0, 5, array_fill(0, 5, null));

        foreach ($this->numbers as $number) {
            $grid[$number->row][$number->col] = $number->value;
        }

        return $grid;
    }
}
