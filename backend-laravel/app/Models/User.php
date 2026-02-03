<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, HasUuids, HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * User roles
     */
    const ROLE_ADMIN = 'admin';
    const ROLE_OPERADOR = 'operador';
    const ROLE_AUDITOR = 'auditor';
    const ROLE_JOGADOR = 'jogador';

    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_OPERADOR,
            self::ROLE_AUDITOR,
            self::ROLE_JOGADOR,
        ];
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user is operador
     */
    public function isOperador(): bool
    {
        return $this->role === self::ROLE_OPERADOR;
    }

    /**
     * Check if user is auditor
     */
    public function isAuditor(): bool
    {
        return $this->role === self::ROLE_AUDITOR;
    }

    /**
     * Check if user can manage events
     */
    public function canManageEvents(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_OPERADOR]);
    }

    /**
     * Relationships
     */
    public function events()
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function bingoClaimsAsPlayer()
    {
        return $this->hasMany(BingoClaim::class, 'claimed_by');
    }

    public function systemLogs()
    {
        return $this->hasMany(SystemLog::class);
    }
}
