<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@kelana.app');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Admin Kelana'),
                'password' => env('ADMIN_PASSWORD', 'password123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
    }
}
