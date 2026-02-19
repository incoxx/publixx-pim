<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@publixx.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'language' => 'de',
                'is_active' => true,
            ]
        );

        $admin->assignRole('Admin');
    }
}
