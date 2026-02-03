<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $operador = User::where('role', User::ROLE_OPERADOR)->first();

        if (!$admin || !$operador) {
            return;
        }

        // Create draft event
        Event::create([
            'id' => Str::uuid(),
            'name' => 'Bingo Beneficente 2024',
            'description' => 'Evento beneficente com bingo híbrido',
            'event_date' => now()->addDays(30),
            'location' => 'São Paulo, SP',
            'total_cards' => 2000,
            'total_rounds' => 5,
            'seed' => Hash::make(Str::random(64)),
            'participation_type' => Event::PARTICIPATION_HIBRIDO,
            'status' => Event::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);

        // Create another draft event
        Event::create([
            'id' => Str::uuid(),
            'name' => 'Bingo Digital 2024',
            'description' => 'Evento online apenas',
            'event_date' => now()->addDays(15),
            'location' => null,
            'total_cards' => 500,
            'total_rounds' => 5,
            'seed' => Hash::make(Str::random(64)),
            'participation_type' => Event::PARTICIPATION_DIGITAL,
            'status' => Event::STATUS_DRAFT,
            'created_by' => $operador->id,
        ]);

        // Create presencial event
        Event::create([
            'id' => Str::uuid(),
            'name' => 'Bingo Presencial 2024',
            'description' => 'Evento presencial em casa de bingo',
            'event_date' => now()->addDays(60),
            'location' => 'Rio de Janeiro, RJ',
            'total_cards' => 1500,
            'total_rounds' => 5,
            'seed' => Hash::make(Str::random(64)),
            'participation_type' => Event::PARTICIPATION_PRESENCIAL,
            'status' => Event::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);
    }
}
