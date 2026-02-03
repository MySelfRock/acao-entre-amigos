<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubcardNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'subcard_id',
        'row',
        'col',
        'value',
    ];

    public $timestamps = false;

    public function subcard()
    {
        return $this->belongsTo(Subcard::class);
    }
}
