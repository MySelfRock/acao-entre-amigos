<?php

namespace App\Services;

use App\Models\User;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    /**
     * Register a new user
     */
    public function register(array $data): User
    {
        $user = User::create([
            'id' => Str::uuid(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'] ?? User::ROLE_JOGADOR,
            'is_active' => true,
        ]);

        SystemLog::log(
            'user_registered',
            'user',
            $user->id,
            ['role' => $user->role]
        );

        return $user;
    }

    /**
     * Authenticate user with credentials
     */
    public function authenticate(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            SystemLog::log('login_failed', 'user', null, ['email' => $email]);
            return null;
        }

        if (!$user->is_active) {
            SystemLog::log('login_blocked', 'user', $user->id, ['reason' => 'inactive']);
            return null;
        }

        SystemLog::log('login_success', 'user', $user->id);
        return $user;
    }

    /**
     * Create API token for user
     */
    public function createToken(User $user, string $tokenName = 'api-token'): string
    {
        return $user->createToken(
            $tokenName,
            ['*'],
            now()->addDays(365)
        )->plainTextToken;
    }

    /**
     * Verify user email
     */
    public function verifyEmail(User $user): void
    {
        $user->update(['email_verified_at' => now()]);
        SystemLog::log('email_verified', 'user', $user->id);
    }

    /**
     * Update user password
     */
    public function updatePassword(User $user, string $newPassword): void
    {
        $user->update(['password' => Hash::make($newPassword)]);
        SystemLog::log('password_changed', 'user', $user->id);
    }
}
