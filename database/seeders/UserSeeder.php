<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->upsert(
            [
                [
                    'username' => 'Admin',
                    'name' => 'Admin',
                    'email' => env('ADMIN_EMAIL', 'qmsaso@lnu.edu.ph'),
                    'password' => Hash::make(env('ADMIN_PASSWORD', 'admin123')),
                    'role' => 'admin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['email'],
            ['username', 'name', 'password', 'role', 'updated_at']
        );
    }
}
