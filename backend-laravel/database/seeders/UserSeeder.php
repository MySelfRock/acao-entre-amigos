<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test users for each role
        User::create([
            'id' => Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@bingo.local',
            'password' => bcrypt('password123'),
            'phone' => '11999999999',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'id' => Str::uuid(),
            'name' => 'Operador User',
            'email' => 'operador@bingo.local',
            'password' => bcrypt('password123'),
            'phone' => '11988888888',
            'role' => User::ROLE_OPERADOR,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'id' => Str::uuid(),
            'name' => 'Auditor User',
            'email' => 'auditor@bingo.local',
            'password' => bcrypt('password123'),
            'phone' => '11977777777',
            'role' => User::ROLE_AUDITOR,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'id' => Str::uuid(),
            'name' => 'Player User',
            'email' => 'player@bingo.local',
            'password' => bcrypt('password123'),
            'phone' => '11966666666',
            'role' => User::ROLE_JOGADOR,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
