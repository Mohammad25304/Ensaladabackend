<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@ensalada.com'],
            [
                'name' => 'Ensalada Admin',
                'password' => Hash::make('changeme123'), // change this after first login
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}