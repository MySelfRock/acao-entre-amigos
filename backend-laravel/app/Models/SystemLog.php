<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'reference_type',
        'reference_id',
        'metadata',
        'ip_address',
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an action
     */
    public static function log(
        string $action,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?array $metadata = null,
        ?string $userId = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
